<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

/**
 * Turns an EXT:form form-definition array into a JSON input schema for a WebMCP
 * tool. It reads only the stable array representation of a form (the same shape
 * the form editor persists: nested ``renderables`` with ``type``, ``identifier``,
 * ``label``, ``properties``, ``validators``, ``defaultValue``) — no EXT:form
 * runtime or ``@internal`` classes are touched here, so the mapping is isolated
 * and unit-testable on its own.
 *
 * Support policy is strict (see {@see FieldSupport}): a single unsupported field,
 * or a multi-step form, refuses the whole thing.
 */
final class FormSchemaBuilder
{
    /**
     * @param array<string, mixed> $formDefinition the form definition as an array
     */
    public function build(array $formDefinition): FormSchemaResult
    {
        $renderables = $this->renderables($formDefinition);

        // Multi-step forms are out of scope for v1: more than one page cannot be
        // completed in a single tool call.
        $pages = array_filter($renderables, static fn ($r): bool => is_array($r) && ($r['type'] ?? '') === 'Page');
        if (count($pages) > 1) {
            return FormSchemaResult::unsupported([[
                'identifier' => (string) ($formDefinition['identifier'] ?? ''),
                'type' => 'multi-step form (' . count($pages) . ' pages)',
            ]]);
        }

        $properties = [];
        $required = [];
        $unsupported = [];
        $hiddenDefaults = [];
        $this->walk($renderables, $properties, $required, $unsupported, $hiddenDefaults);

        if ([] !== $unsupported) {
            return FormSchemaResult::unsupported($unsupported);
        }

        $schema = ['type' => 'object', 'properties' => [] === $properties ? new \stdClass() : $properties];
        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return FormSchemaResult::supported($schema, $hiddenDefaults);
    }

    /**
     * @param list<mixed>                                   $renderables
     * @param array<string, mixed>                          $properties
     * @param list<string>                                  $required
     * @param list<array{identifier: string, type: string}> $unsupported
     * @param array<string, mixed>                          $hiddenDefaults
     */
    private function walk(array $renderables, array &$properties, array &$required, array &$unsupported, array &$hiddenDefaults): void
    {
        foreach ($renderables as $element) {
            if (!is_array($element)) {
                continue;
            }
            $type = (string) ($element['type'] ?? '');
            $identifier = (string) ($element['identifier'] ?? '');

            switch (FieldSupport::forType($type)) {
                case FieldSupport::Structural:
                    $this->walk($this->renderables($element), $properties, $required, $unsupported, $hiddenDefaults);
                    break;
                case FieldSupport::Fillable:
                    if ('' === $identifier) {
                        break;
                    }
                    $properties[$identifier] = $this->schemaFor($element);
                    if ($this->isRequired($element)) {
                        $required[] = $identifier;
                    }
                    break;
                case FieldSupport::Hidden:
                    if ('' !== $identifier) {
                        $hiddenDefaults[$identifier] = $element['defaultValue'] ?? '';
                    }
                    break;
                case FieldSupport::Unsupported:
                    $unsupported[] = ['identifier' => $identifier, 'type' => $type];
                    break;
                case FieldSupport::DisplayOnly:
                case FieldSupport::Ignored:
                    break;
            }
        }
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return array<string, mixed>
     */
    private function schemaFor(array $element): array
    {
        $type = (string) ($element['type'] ?? '');
        $options = $this->options($element);

        $fragment = match ($type) {
            'Email' => ['type' => 'string', 'format' => 'email'],
            'Url' => ['type' => 'string', 'format' => 'uri'],
            'Number' => ['type' => 'number'],
            'Checkbox' => ['type' => 'boolean'],
            'Date', 'DatePicker' => ['type' => 'string', 'format' => 'date'],
            'SingleSelect', 'RadioButton' => [] === $options
                ? ['type' => 'string']
                : ['type' => 'string', 'enum' => $options],
            'MultiSelect', 'MultiCheckbox' => [
                'type' => 'array',
                'items' => [] === $options ? ['type' => 'string'] : ['type' => 'string', 'enum' => $options],
            ],
            // Text, Textarea, Password, AdvancedPassword, Telephone, CountrySelect:
            default => ['type' => 'string'],
        };

        // Only an explicit label becomes a description; falling back to the
        // identifier would just duplicate the property key.
        $label = (string) ($element['label'] ?? '');
        if ('' !== $label) {
            $fragment['description'] = $label;
        }

        return $fragment;
    }

    /**
     * Selectable option values, taken from the element's ``properties.options``
     * map (keyed value => label).
     *
     * @param array<string, mixed> $element
     *
     * @return list<string>
     */
    private function options(array $element): array
    {
        $options = $element['properties']['options'] ?? null;
        if (!is_array($options)) {
            return [];
        }

        return array_map(strval(...), array_keys($options));
    }

    /**
     * A field is required when it carries the EXT:form ``NotEmpty`` validator.
     *
     * @param array<string, mixed> $element
     */
    private function isRequired(array $element): bool
    {
        foreach ($element['validators'] ?? [] as $validator) {
            if (is_array($validator) && 'NotEmpty' === ($validator['identifier'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return list<mixed>
     */
    private function renderables(array $node): array
    {
        $renderables = $node['renderables'] ?? [];

        return is_array($renderables) ? array_values($renderables) : [];
    }
}
