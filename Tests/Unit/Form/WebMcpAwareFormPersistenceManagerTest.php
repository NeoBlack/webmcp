<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Form;

use Neoblack\Webmcp\Form\FormSchemaBuilder;
use Neoblack\Webmcp\Form\WebMcpAwareFormPersistenceManager;
use TYPO3\CMS\Form\Domain\ValueObject\FormIdentifier;
use TYPO3\CMS\Form\Mvc\Persistence\Exception\PersistenceManagerException;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class WebMcpAwareFormPersistenceManagerTest extends UnitTestCase
{
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
            'renderingOptions' => ['webmcp' => $webmcp],
            'renderables' => [['type' => 'Page', 'identifier' => 'p1', 'renderables' => $elements]],
        ];
    }

    public function testRefusesSavingEnabledFormWithUnsupportedField(): void
    {
        $inner = $this->createMock(FormPersistenceManagerInterface::class);
        $inner->expects(self::never())->method('save');

        $manager = new WebMcpAwareFormPersistenceManager($inner, new FormSchemaBuilder());

        $this->expectException(PersistenceManagerException::class);
        $this->expectExceptionMessage('cv (FileUpload)');

        $manager->save('1:/form/contact.form.yaml', $this->definition(
            ['enable' => true],
            [['type' => 'FileUpload', 'identifier' => 'cv']],
        ), []);
    }

    public function testDelegatesSaveForSupportedEnabledForm(): void
    {
        $identifier = new FormIdentifier('contact');
        $inner = $this->createMock(FormPersistenceManagerInterface::class);
        $inner->expects(self::once())->method('save')->willReturn($identifier);

        $manager = new WebMcpAwareFormPersistenceManager($inner, new FormSchemaBuilder());

        $result = $manager->save('1:/form/contact.form.yaml', $this->definition(
            ['enable' => true],
            [['type' => 'Text', 'identifier' => 'name']],
        ), []);

        self::assertSame($identifier, $result);
    }

    public function testDelegatesSaveForFormNotEnabledForWebMcp(): void
    {
        $inner = $this->createMock(FormPersistenceManagerInterface::class);
        $inner->expects(self::once())->method('save')->willReturn(new FormIdentifier('contact'));

        $manager = new WebMcpAwareFormPersistenceManager($inner, new FormSchemaBuilder());

        // An unsupported field is fine as long as the form is not opted in.
        $manager->save('1:/form/contact.form.yaml', $this->definition(
            ['enable' => false],
            [['type' => 'FileUpload', 'identifier' => 'cv']],
        ), []);
    }

    public function testDelegatesOtherMethods(): void
    {
        $inner = $this->createMock(FormPersistenceManagerInterface::class);
        $inner->expects(self::once())->method('load')->with('1:/x.yaml')->willReturn(['identifier' => 'x']);

        $manager = new WebMcpAwareFormPersistenceManager($inner, new FormSchemaBuilder());

        self::assertSame(['identifier' => 'x'], $manager->load('1:/x.yaml'));
    }
}
