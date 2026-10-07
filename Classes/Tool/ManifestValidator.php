<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tool;

/**
 * Checks a Manifest against the WebMCP name rules and Chrome's "Secure tools"
 * size recommendations. Every finding is advisory: the caller logs it, the tool
 * is still emitted. Only the spec's name pattern is normative; the length limits
 * are Google guidance, so a long but valid tool keeps working.
 */
final class ManifestValidator
{
    /** Spec: tool names are 1–128 characters from [A-Za-z0-9_.-]. */
    public const NAME_PATTERN = '/^[A-Za-z0-9_.-]{1,128}$/';

    /** Chrome "Secure tools" recommendations; the name limit applies to tool and parameter names. */
    public const RECOMMENDED_NAME_LENGTH = 30;
    public const RECOMMENDED_DESCRIPTION_LENGTH = 500;
    public const RECOMMENDED_PARAMETER_DESCRIPTION_LENGTH = 150;

    /**
     * @return list<string> human-readable findings, empty when the manifest is fine
     */
    public function validate(Manifest $manifest): array
    {
        $name = $manifest->name;
        $findings = [];

        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            $findings[] = \sprintf(
                'Tool name "%s" does not match the WebMCP name pattern [A-Za-z0-9_.-]{1,128}; browsers may reject it.',
                $name,
            );
        } elseif (mb_strlen($name) > self::RECOMMENDED_NAME_LENGTH) {
            $findings[] = \sprintf(
                'Tool name "%s" is %d characters long; at most %d are recommended.',
                $name,
                mb_strlen($name),
                self::RECOMMENDED_NAME_LENGTH,
            );
        }

        $length = mb_strlen($manifest->description);
        if ($length > self::RECOMMENDED_DESCRIPTION_LENGTH) {
            $findings[] = \sprintf(
                'Tool "%s": description is %d characters long; at most %d are recommended.',
                $name,
                $length,
                self::RECOMMENDED_DESCRIPTION_LENGTH,
            );
        }

        foreach ($this->parameters($manifest->inputSchema) as [$path, $parameterName, $description]) {
            $length = mb_strlen($parameterName);
            if ($length > self::RECOMMENDED_NAME_LENGTH) {
                $findings[] = \sprintf(
                    'Tool "%s": parameter name "%s" is %d characters long; at most %d are recommended.',
                    $name,
                    $path,
                    $length,
                    self::RECOMMENDED_NAME_LENGTH,
                );
            }
            $length = mb_strlen($description ?? '');
            if ($length > self::RECOMMENDED_PARAMETER_DESCRIPTION_LENGTH) {
                $findings[] = \sprintf(
                    'Tool "%s": description of parameter "%s" is %d characters long; at most %d are recommended.',
                    $name,
                    $path,
                    $length,
                    self::RECOMMENDED_PARAMETER_DESCRIPTION_LENGTH,
                );
            }
        }

        return $findings;
    }

    /**
     * Collect every property of a JSON schema as [dotted path, name, description
     * or null], descending into nested objects and array items.
     *
     * @param array<mixed> $schema
     *
     * @return list<array{string, string, string|null}>
     */
    private function parameters(array $schema, string $prefix = ''): array
    {
        $found = [];
        $properties = $schema['properties'] ?? null;
        if (\is_array($properties)) {
            foreach ($properties as $key => $property) {
                if (!\is_array($property)) {
                    continue;
                }
                $path = $prefix . $key;
                $description = \is_string($property['description'] ?? null) ? $property['description'] : null;
                $found = [...$found, [$path, (string) $key, $description], ...$this->parameters($property, $path . '.')];
            }
        }
        if (\is_array($schema['items'] ?? null)) {
            $found = [...$found, ...$this->parameters($schema['items'], $prefix)];
        }

        return $found;
    }
}
