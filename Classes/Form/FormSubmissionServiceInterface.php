<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Runs a form's own server-side validation and finishers for an agent-triggered
 * submission. The concrete implementation is where all of EXT:form's
 * ``@internal`` machinery is isolated; the middleware depends only on this seam,
 * so it stays unit-testable.
 */
interface FormSubmissionServiceInterface
{
    /**
     * @param array<string, mixed> $values agent-supplied field values (element identifier => value)
     */
    public function submit(ServerRequestInterface $request, string $persistenceIdentifier, array $values): FormSubmissionResult;
}
