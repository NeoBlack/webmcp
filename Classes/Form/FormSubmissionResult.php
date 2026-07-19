<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

/**
 * The outcome of a form submission the agent triggered: a success message, a
 * validation failure with per-field messages the agent can act on, or a generic
 * failure. Shaped for the JSON contract the ``form`` runtime primitive expects.
 */
final class FormSubmissionResult
{
    /**
     * @param array<string, string> $errors field identifier => message (validation failures only)
     */
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $errors,
    ) {
    }

    public static function ok(string $message): self
    {
        return new self(true, $message, []);
    }

    /**
     * @param array<string, string> $errors
     */
    public static function invalid(string $message, array $errors): self
    {
        return new self(false, $message, $errors);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message, []);
    }
}
