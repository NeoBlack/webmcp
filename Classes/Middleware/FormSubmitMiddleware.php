<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Middleware;

use Neoblack\Webmcp\Form\FormSubmissionServiceInterface;
use Neoblack\Webmcp\Form\FormToken;
use Neoblack\Webmcp\Form\RegisterWebMcpForm;
use Neoblack\Webmcp\Security\RateLimiter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;

/**
 * Submit endpoint for WebMCP form tools. A form tool POSTs the agent's field
 * values plus its signed token here; the token identifies the form (an agent can
 * neither forge it nor retarget another form), and the submission service runs
 * the form's own validation and finishers.
 *
 * Responses are JSON, matching what the ``form`` runtime primitive expects:
 * success is ``{ok: true, message}``; a validation failure is
 * ``{ok: false, message, errors}`` so the agent can correct and retry.
 */
final class FormSubmitMiddleware implements MiddlewareInterface
{
    private const RATE_LIMIT = 30;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly FormToken $formToken,
        private readonly FormSubmissionServiceInterface $submissionService,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ('POST' !== $request->getMethod() || RegisterWebMcpForm::ENDPOINT !== $request->getUri()->getPath()) {
            return $handler->handle($request);
        }

        // Same-origin guard: the runtime submits with fetch, which sets
        // Sec-Fetch-Site. Reject obvious cross-site posts; tolerate an empty header.
        $fetchSite = $request->getHeaderLine('sec-fetch-site');
        if ('' !== $fetchSite && 'same-origin' !== $fetchSite && 'same-site' !== $fetchSite) {
            return $this->json(['ok' => false, 'message' => 'Cross-origin submission rejected.'], 403);
        }

        $normalizedParams = $request->getAttribute('normalizedParams');
        $clientId = $normalizedParams instanceof NormalizedParams ? $normalizedParams->getRemoteAddress() : '';
        if (!$this->rateLimiter->allow('form:' . $clientId, self::RATE_LIMIT)) {
            return $this->json(['ok' => false, 'message' => 'Too many submissions, please try again shortly.'], 429);
        }

        $data = json_decode((string) $request->getBody(), true);
        $token = is_array($data) ? (string) ($data['token'] ?? '') : '';
        $values = is_array($data) && isset($data['values']) && is_array($data['values']) ? $data['values'] : [];

        $persistenceIdentifier = $this->formToken->verify($token);
        if (null === $persistenceIdentifier) {
            return $this->json(['ok' => false, 'message' => 'Invalid or missing form token.'], 403);
        }

        $result = $this->submissionService->submit($request, $persistenceIdentifier, $values);
        if ($result->success) {
            return $this->json(['ok' => true, 'message' => $result->message]);
        }

        $payload = ['ok' => false, 'message' => $result->message];
        if ([] !== $result->errors) {
            $payload['errors'] = $result->errors;
        }

        return $this->json($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}');

        return $response;
    }
}
