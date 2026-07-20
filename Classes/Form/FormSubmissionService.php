<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\RedirectFinisher;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Domain\Runtime\FormState;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManagerInterface;

/**
 * Runs an opted-in form's own validation and finishers for an agent submission,
 * without a browser request.
 *
 * ⚠ This is the one place that depends on EXT:form's ``@internal`` API — there is
 * no stable public API for programmatic submission. It is deliberately isolated
 * here so a breaking TYPO3 change stays contained and can be spotted by a single
 * failing test. The approach (verified against the code, not the docs):
 *
 *   1. load the definition array and rebuild the {@see FormDefinition} — this wires
 *      the validators into the form's processing rules;
 *   2. validate each field through its processing rule (no runtime needed);
 *   3. run the finishers via a hand-built {@see FinisherContext}, feeding the
 *      values through a {@see FormRuntime} whose state we inject directly, so we
 *      never enter the runtime's request-bound submission path.
 *
 * Redirect finishers are skipped (they need the front-end response lifecycle);
 * the agent gets the success message instead.
 */
final class FormSubmissionService implements FormSubmissionServiceInterface
{
    public function __construct(
        private readonly FormSchemaBuilder $schemaBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function submit(ServerRequestInterface $request, string $persistenceIdentifier, array $values): FormSubmissionResult
    {
        if (!interface_exists(FormPersistenceManagerInterface::class)) {
            return FormSubmissionResult::failed('Form submission is not available.');
        }

        try {
            return $this->doSubmit($request, $persistenceIdentifier, $values);
        } catch (\Throwable $exception) {
            $this->logger->error('WebMCP form submission failed for "{form}": {message}', [
                'form' => $persistenceIdentifier,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return FormSubmissionResult::failed('The form could not be submitted.');
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function doSubmit(ServerRequestInterface $request, string $persistenceIdentifier, array $values): FormSubmissionResult
    {
        $persistenceManager = GeneralUtility::makeInstance(FormPersistenceManagerInterface::class);
        $definitionArray = $persistenceManager->load($persistenceIdentifier, [], $request);

        // Defence in depth: only submit forms that are still opted in and fully
        // supported, regardless of a (valid) token.
        $options = $definitionArray['renderingOptions']['webmcp'] ?? null;
        if (!is_array($options) || true !== ($options['enable'] ?? false) || !$this->schemaBuilder->build($definitionArray)->supported) {
            return FormSubmissionResult::failed('This form is not available for submission.');
        }

        // EXT:form builds the definition through the extbase ConfigurationManager
        // (it reads the prototype's TypoScript). We run outside an extbase request,
        // so that singleton has no request and would throw; prime it with ours.
        GeneralUtility::makeInstance(ConfigurationManagerInterface::class)->setRequest($request);

        $factory = GeneralUtility::makeInstance(ArrayFormFactory::class);
        $formDefinition = $factory->build($definitionArray, null, $request);
        $extbaseRequest = $this->extbaseRequest($request);

        [$mapped, $errors] = $this->validate($formDefinition, $values);
        if ([] !== $errors) {
            return FormSubmissionResult::invalid('The form contains invalid values.', $errors);
        }

        $this->runFinishers($formDefinition, $extbaseRequest, $mapped);

        $message = (string) ($options['successMessage'] ?? '');

        return FormSubmissionResult::ok('' !== $message ? $message : 'Thank you — your submission has been received.');
    }

    /**
     * Validate every field through its processing rule (property mapping plus the
     * form's own validators). Returns the mapped values and any per-field errors.
     *
     * @param array<string, mixed> $values
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(FormDefinition $formDefinition, array $values): array
    {
        // Plural getter on purpose: the singular getProcessingRule() would create
        // and cache an empty rule for fields that have none.
        $rules = $formDefinition->getProcessingRules();
        $mapped = [];
        $errors = [];

        foreach ($formDefinition->getElements() as $identifier => $element) {
            $value = $values[$identifier] ?? null;
            if (!isset($rules[$identifier])) {
                $mapped[$identifier] = $value;
                continue;
            }

            $rule = $rules[$identifier];
            $mapped[$identifier] = $rule->process($value);
            $result = $rule->getProcessingMessages();
            if ($result->hasErrors()) {
                $first = $result->getFirstError();
                $errors[$identifier] = $first instanceof Error ? $first->getMessage() : 'Invalid value.';
            }
        }

        return [$mapped, $errors];
    }

    /**
     * @param array<string, mixed> $mapped
     */
    private function runFinishers(FormDefinition $formDefinition, ExtbaseRequest $request, array $mapped): void
    {
        $runtime = $this->runtimeWithValues($formDefinition, $request, $mapped);
        $context = GeneralUtility::makeInstance(FinisherContext::class, $runtime, $request);

        foreach ($formDefinition->getFinishers() as $finisher) {
            // Redirect finishers need the front-end response/content-object path we
            // deliberately avoid; skip them rather than crash.
            if ($finisher instanceof RedirectFinisher) {
                continue;
            }
            $finisher->execute($context);
            if ($context->isCancelled()) {
                break;
            }
        }
    }

    /**
     * A {@see FormRuntime} carrying the submitted values in its state, without
     * running initialize() (which would demand a full browser submission). The
     * state is injected directly; the property access is the sharpest ``@internal``
     * edge, so it is guarded and fails cleanly if a TYPO3 version removes it.
     *
     * @param array<string, mixed> $mapped
     */
    private function runtimeWithValues(FormDefinition $formDefinition, ExtbaseRequest $request, array $mapped): FormRuntime
    {
        if (!property_exists(FormRuntime::class, 'formState')) {
            throw new \RuntimeException('FormRuntime::$formState is unavailable in this TYPO3 version; the WebMCP form bridge needs an update.', 1721400000);
        }

        $runtime = GeneralUtility::makeInstance(FormRuntime::class);
        $runtime->setFormDefinition($formDefinition);
        $runtime->setRequest($request);

        $state = GeneralUtility::makeInstance(FormState::class);
        (new \ReflectionProperty(FormRuntime::class, 'formState'))->setValue($runtime, $state);

        foreach ($mapped as $identifier => $value) {
            $runtime[$identifier] = $value;
        }

        return $runtime;
    }

    private function extbaseRequest(ServerRequestInterface $request): ExtbaseRequest
    {
        if (!$request->getAttribute('extbase') instanceof ExtbaseRequestParameters) {
            $request = $request->withAttribute('extbase', new ExtbaseRequestParameters());
        }

        return new ExtbaseRequest($request);
    }
}
