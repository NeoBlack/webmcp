<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

/**
 * The outcome of {@see FormSchemaBuilder::build()}: either a usable JSON input
 * schema for the form, or a refusal listing the fields that made the form
 * unofferable. A form is only ever exposed to an agent when {@see $supported} is
 * true; otherwise the reasons drive the save-time block and the registration-time
 * warning.
 */
final class FormSchemaResult
{
    /**
     * @param array<string, mixed>                          $inputSchema    JSON schema for the agent (empty when unsupported)
     * @param list<array{identifier: string, type: string}> $unsupported    the offending fields, empty when supported
     * @param array<string, mixed>                          $hiddenDefaults hidden field values to submit verbatim
     */
    private function __construct(
        public readonly bool $supported,
        public readonly array $inputSchema,
        public readonly array $unsupported,
        public readonly array $hiddenDefaults,
    ) {
    }

    /**
     * @param array<string, mixed> $inputSchema
     * @param array<string, mixed> $hiddenDefaults
     */
    public static function supported(array $inputSchema, array $hiddenDefaults): self
    {
        return new self(true, $inputSchema, [], $hiddenDefaults);
    }

    /**
     * @param list<array{identifier: string, type: string}> $unsupported
     */
    public static function unsupported(array $unsupported): self
    {
        return new self(false, [], $unsupported, []);
    }
}
