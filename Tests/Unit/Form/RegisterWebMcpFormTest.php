<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Form;

use Neoblack\Webmcp\Form\FormRegistry;
use Neoblack\Webmcp\Form\FormSchemaBuilder;
use Neoblack\Webmcp\Form\FormToken;
use Neoblack\Webmcp\Form\RegisterWebMcpForm;
use Neoblack\Webmcp\Tool\Primitive;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Form\Mvc\Persistence\Event\AfterFormDefinitionLoadedEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RegisterWebMcpFormTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 96);
        $this->setApplicationType(SystemEnvironmentBuilder::REQUESTTYPE_FE);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST'], $GLOBALS['TYPO3_CONF_VARS']);
        parent::tearDown();
    }

    private function setApplicationType(int $type): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', $type);
    }

    /**
     * @param array<string, mixed>       $webmcp
     * @param list<array<string, mixed>> $elements
     *
     * @return array<string, mixed>
     */
    private function definition(array $webmcp, array $elements): array
    {
        return [
            'type' => 'Form',
            'identifier' => 'contact',
            'label' => 'Contact form',
            'renderingOptions' => ['webmcp' => $webmcp],
            'renderables' => [['type' => 'Page', 'identifier' => 'p1', 'renderables' => $elements]],
        ];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function event(array $definition): AfterFormDefinitionLoadedEvent
    {
        return new AfterFormDefinitionLoadedEvent($definition, '1:/form/contact.form.yaml', 'cache-key');
    }

    private function listener(FormRegistry $registry, ?LoggerInterface $logger = null): RegisterWebMcpForm
    {
        return new RegisterWebMcpForm(new FormSchemaBuilder(), $registry, new FormToken(new HashService()), $logger ?? new NullLogger());
    }

    public function testRegistersSupportedOptedInForm(): void
    {
        $registry = new FormRegistry();
        $listener = $this->listener($registry);

        $listener($this->event($this->definition(
            ['enable' => true, 'description' => 'Contact us', 'confirm' => 'Send?'],
            [['type' => 'Text', 'identifier' => 'name']],
        )));

        $manifests = $registry->all();
        self::assertCount(1, $manifests);
        self::assertSame('contact', $manifests[0]->name);
        self::assertSame('Contact us', $manifests[0]->description);
        self::assertSame(Primitive::Form, $manifests[0]->primitive);
        self::assertSame(RegisterWebMcpForm::ENDPOINT, $manifests[0]->data['endpoint']);
        self::assertSame('Send?', $manifests[0]->data['confirm']);
        // The identifier travels only inside the signed token, not as a forgeable field.
        self::assertArrayNotHasKey('persistenceIdentifier', $manifests[0]->data);
        self::assertSame(
            '1:/form/contact.form.yaml',
            (new FormToken(new HashService()))->verify($manifests[0]->data['token']),
        );
    }

    public function testIgnoresFormWithoutFlag(): void
    {
        $registry = new FormRegistry();
        $this->listener($registry)($this->event($this->definition(
            ['enable' => false],
            [['type' => 'Text', 'identifier' => 'name']],
        )));

        self::assertSame([], $registry->all());
    }

    public function testSkipsAndLogsUnsupportedForm(): void
    {
        $registry = new FormRegistry();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $this->listener($registry, $logger)($this->event($this->definition(
            ['enable' => true],
            [['type' => 'FileUpload', 'identifier' => 'cv']],
        )));

        self::assertSame([], $registry->all());
    }

    public function testDoesNothingOutsideFrontend(): void
    {
        $this->setApplicationType(SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $registry = new FormRegistry();

        $this->listener($registry)($this->event($this->definition(
            ['enable' => true],
            [['type' => 'Text', 'identifier' => 'name']],
        )));

        self::assertSame([], $registry->all());
    }
}
