<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Functional\Form;

use Neoblack\Webmcp\Form\FormSchemaBuilder;
use Neoblack\Webmcp\Form\FormSubmissionService;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Exercises the one place that drives EXT:form's @internal machinery: rebuilding a
 * form from its definition array, validating the agent's values through the form's
 * own processing rules, and running the finisher loop (which relies on injecting a
 * FormState into a FormRuntime by reflection). This is the test that would catch a
 * TYPO3 update breaking that internal contract.
 *
 * The persistence layer is faked with GeneralUtility::addInstance so no form
 * storage needs to be configured — only the definition array matters here.
 */
final class FormSubmissionServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['neoblack/webmcp'];
    protected array $coreExtensionsToLoad = ['backend', 'extbase', 'fluid', 'form'];

    /**
     * A minimal single-page, WebMCP-enabled form with one required text field.
     *
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return [
            'type' => 'Form',
            'identifier' => 'test-contact',
            'label' => 'Test contact',
            'prototypeName' => 'standard',
            'renderingOptions' => ['webmcp' => ['enable' => true]],
            'renderables' => [
                [
                    'type' => 'Page',
                    'identifier' => 'page-1',
                    'label' => 'Page',
                    'renderables' => [
                        [
                            'type' => 'Text',
                            'identifier' => 'name',
                            'label' => 'Name',
                            'validators' => [['identifier' => 'NotEmpty']],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function request(): ServerRequest
    {
        return (new ServerRequest('https://example.org/', 'POST'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function service(array $definition): FormSubmissionService
    {
        $manager = $this->createMock(FormPersistenceManagerInterface::class);
        $manager->method('load')->willReturn($definition);
        // The service resolves the persistence manager via makeInstance; prime it.
        GeneralUtility::addInstance(FormPersistenceManagerInterface::class, $manager);

        return new FormSubmissionService(new FormSchemaBuilder(), new NullLogger());
    }

    public function testReturnsPerFieldErrorForMissingRequiredValue(): void
    {
        $result = $this->service($this->definition())->submit($this->request(), 'test-contact', ['name' => '']);

        self::assertFalse($result->success);
        self::assertArrayHasKey('name', $result->errors);
    }

    public function testAcceptsValidSubmission(): void
    {
        $result = $this->service($this->definition())->submit($this->request(), 'test-contact', ['name' => 'Ada Lovelace']);

        self::assertTrue($result->success);
        self::assertSame([], $result->errors);
    }
}
