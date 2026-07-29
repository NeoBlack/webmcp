<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Form;

use Neoblack\Webmcp\Form\FormSchemaBuilder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FormSchemaBuilderTest extends UnitTestCase
{
    /**
     * A minimal single-page form definition, as EXT:form persists it.
     *
     * @param list<array<string, mixed>> $elements
     *
     * @return array<string, mixed>
     */
    private static function form(array $elements, int $pages = 1): array
    {
        $renderables = [];
        for ($i = 0; $i < $pages; ++$i) {
            $renderables[] = ['type' => 'Page', 'identifier' => 'page-' . $i, 'renderables' => 0 === $i ? $elements : []];
        }

        return ['type' => 'Form', 'identifier' => 'contact', 'label' => 'Contact', 'renderables' => $renderables];
    }

    public function testMapsCommonFieldTypesToSchema(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([
            ['type' => 'Text', 'identifier' => 'name', 'label' => 'Name', 'validators' => [['identifier' => 'NotEmpty']]],
            ['type' => 'Email', 'identifier' => 'email', 'label' => 'E-mail'],
            ['type' => 'Number', 'identifier' => 'age'],
            ['type' => 'Checkbox', 'identifier' => 'agree'],
        ]));

        self::assertTrue($result->supported);
        self::assertSame('object', $result->inputSchema['type']);
        self::assertSame(['type' => 'string', 'description' => 'Name'], $result->inputSchema['properties']['name']);
        self::assertSame(['type' => 'string', 'format' => 'email', 'description' => 'E-mail'], $result->inputSchema['properties']['email']);
        self::assertSame(['type' => 'number'], $result->inputSchema['properties']['age']);
        self::assertSame(['type' => 'boolean'], $result->inputSchema['properties']['agree']);
        // Only the NotEmpty-guarded field is required.
        self::assertSame(['name'], $result->inputSchema['required']);
    }

    public function testDerivesEnumFromSelectOptions(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([
            ['type' => 'SingleSelect', 'identifier' => 'topic', 'properties' => ['options' => ['sales' => 'Sales', 'support' => 'Support']]],
            ['type' => 'MultiSelect', 'identifier' => 'tags', 'properties' => ['options' => ['a' => 'A', 'b' => 'B']]],
        ]));

        self::assertSame(['sales', 'support'], $result->inputSchema['properties']['topic']['enum']);
        self::assertSame('array', $result->inputSchema['properties']['tags']['type']);
        self::assertSame(['a', 'b'], $result->inputSchema['properties']['tags']['items']['enum']);
    }

    public function testWalksStructuralContainersAndSkipsDisplayOnly(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([
            ['type' => 'StaticText', 'identifier' => 'intro'],
            ['type' => 'Fieldset', 'identifier' => 'group', 'renderables' => [
                ['type' => 'Text', 'identifier' => 'nested'],
            ]],
        ]));

        self::assertTrue($result->supported);
        self::assertArrayHasKey('nested', $result->inputSchema['properties']);
        self::assertArrayNotHasKey('intro', $result->inputSchema['properties']);
    }

    public function testCollectsHiddenDefaultsOutsideTheSchema(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([
            ['type' => 'Text', 'identifier' => 'name'],
            ['type' => 'Hidden', 'identifier' => 'source', 'defaultValue' => 'landingpage'],
        ]));

        self::assertArrayNotHasKey('source', $result->inputSchema['properties']);
        self::assertSame(['source' => 'landingpage'], $result->hiddenDefaults);
    }

    public function testIgnoresHoneypot(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([
            ['type' => 'Text', 'identifier' => 'name'],
            ['type' => 'Honeypot', 'identifier' => 'hp'],
        ]));

        self::assertTrue($result->supported);
        self::assertArrayNotHasKey('hp', $result->inputSchema['properties']);
    }

    public function testRefusesFormWithUnsupportedField(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([
            ['type' => 'Text', 'identifier' => 'name'],
            ['type' => 'FileUpload', 'identifier' => 'cv'],
        ]));

        self::assertFalse($result->supported);
        self::assertSame([['identifier' => 'cv', 'type' => 'FileUpload']], $result->unsupported);
        self::assertSame([], $result->inputSchema);
    }

    public function testRefusesUnknownCustomField(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([
            ['type' => 'MyCustomElement', 'identifier' => 'x'],
        ]));

        self::assertFalse($result->supported);
        self::assertSame('MyCustomElement', $result->unsupported[0]['type']);
    }

    public function testRefusesMultiStepForm(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([
            ['type' => 'Text', 'identifier' => 'name'],
        ], pages: 2));

        self::assertFalse($result->supported);
        self::assertStringContainsString('multi-step', $result->unsupported[0]['type']);
    }

    public function testEmptyFormSerialisesPropertiesAsObject(): void
    {
        $result = (new FormSchemaBuilder())->build(self::form([]));

        self::assertTrue($result->supported);
        // No fillable fields: properties must still encode as {} not [].
        self::assertInstanceOf(\stdClass::class, $result->inputSchema['properties']);
    }
}
