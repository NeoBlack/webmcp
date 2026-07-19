<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

use Neoblack\Webmcp\Tool\Manifest;
use Neoblack\Webmcp\Tool\Primitive;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Form\Mvc\Persistence\Event\AfterFormDefinitionLoadedEvent;

/**
 * Turns an opted-in EXT:form form into a WebMCP tool as it is loaded for the
 * frontend. A form opts in with the ``webmcp`` rendering option; the strict
 * support policy (see {@see FormSchemaBuilder}) decides whether it is exposed at
 * all. Unsupported forms are logged and skipped, never partially offered.
 *
 * This is intentionally the loaded-definition array hook, not the built
 * FormDefinition object: the array is the stable representation the schema
 * builder consumes.
 */
final class RegisterWebMcpForm
{
    /** Same-origin path the form tool submits to; served by the submit middleware. */
    public const ENDPOINT = '/webmcp-form';

    public function __construct(
        private readonly FormSchemaBuilder $schemaBuilder,
        private readonly FormRegistry $registry,
        private readonly FormToken $formToken,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsEventListener('neoblack-webmcp/register-form')]
    public function __invoke(AfterFormDefinitionLoadedEvent $event): void
    {
        // Frontend only: form-editor and form-manager loads in the backend must
        // never register a tool.
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface || !ApplicationType::fromRequest($request)->isFrontend()) {
            return;
        }

        $definition = $event->getFormDefinition();
        $options = $definition['renderingOptions']['webmcp'] ?? null;
        if (!is_array($options) || true !== ($options['enable'] ?? false)) {
            return;
        }

        $identifier = (string) ($definition['identifier'] ?? '');
        if ('' === $identifier) {
            return;
        }

        $result = $this->schemaBuilder->build($definition);
        if (!$result->supported) {
            $this->logger->warning(
                'WebMCP-enabled form "{form}" is not exposed: unsupported field(s): {fields}.',
                [
                    'form' => $event->getPersistenceIdentifier(),
                    'fields' => implode(', ', array_map(
                        static fn (array $field): string => $field['identifier'] . ' (' . $field['type'] . ')',
                        $result->unsupported,
                    )),
                ],
            );

            return;
        }

        $description = (string) ($options['description'] ?? $definition['label'] ?? $identifier);

        $this->registry->add(new Manifest(
            name: $identifier,
            description: $description,
            inputSchema: $result->inputSchema,
            primitive: Primitive::Form,
            data: [
                'endpoint' => self::ENDPOINT,
                // A signed token identifies the form server-side; the agent cannot
                // forge it or retarget the endpoint at another form.
                'token' => $this->formToken->sign($event->getPersistenceIdentifier()),
                'confirm' => (string) ($options['confirm'] ?? ''),
            ],
        ));
    }
}
