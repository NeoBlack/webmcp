<?php

declare(strict_types=1);

namespace Neoblack\Webmcp\Middleware;

use Neoblack\Webmcp\Domain\Repository\EventRepository;
use Neoblack\Webmcp\Registry\ToolRegistry;
use Neoblack\Webmcp\Security\RateLimiter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\NormalizedParams;

/**
 * First-party ingest endpoint for WebMCP tool usage events. Receives small
 * same-origin beacons (navigator.sendBeacon) fired by the generic runtime and
 * appends one anonymised row per tool call to tx_neoblackwebmcp_event, read by
 * the backend dashboard.
 *
 * Deliberately minimal and privacy-preserving: only a registered tool name and
 * a short, sanitised client hint are stored — never free text (search terms,
 * messages, names) and no cookies/IP.
 *
 * The accepted tool names come from the ToolRegistry (every registered
 * provider), so the whitelist follows the tools automatically. An event for an
 * unknown tool is passed down the stack rather than swallowed, so it can
 * coexist with any other ingest handler. When analytics is disabled via
 * extension configuration the endpoint is inert (request passed through).
 */
final class EventMiddleware implements MiddlewareInterface
{
    private const PATH = '/webmcp-event';

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ToolRegistry $registry,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly EventRepository $repository,
        private readonly Context $context,
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST' || $request->getUri()->getPath() !== self::PATH) {
            return $handler->handle($request);
        }
        if (!$this->analyticsEnabled()) {
            return $handler->handle($request);
        }

        // Same-origin guard: sendBeacon sets Sec-Fetch-Site; reject obvious
        // cross-site posts. Empty header (older clients) is tolerated.
        $fetchSite = $request->getHeaderLine('sec-fetch-site');
        if ($fetchSite !== '' && $fetchSite !== 'same-origin' && $fetchSite !== 'same-site') {
            return $this->noContent();
        }

        // Rate limit per client IP to protect against flooding / stats pollution.
        $normalizedParams = $request->getAttribute('normalizedParams');
        $clientId = $normalizedParams instanceof NormalizedParams ? $normalizedParams->getRemoteAddress() : '';
        if (!$this->rateLimiter->allow($clientId, $this->rateLimit())) {
            return $this->responseFactory->createResponse(429);
        }

        $data = json_decode((string)$request->getBody(), true);
        $tool = is_array($data) ? (string)($data['tool'] ?? '') : '';

        if ($tool !== '' && in_array($tool, $this->registry->toolNames(), true)) {
            $now = (int)$this->context->getPropertyFromAspect('date', 'timestamp', 0) ?: time();
            $this->repository->log(
                $tool,
                $this->sanitizeClient(is_array($data) ? (string)($data['client'] ?? '') : ''),
                $now,
            );
            // The beacon does not read the response; 204 keeps it cheap.
            return $this->noContent();
        }

        // Unknown tool: hand on so a coexisting legacy ingest can still handle it.
        return $handler->handle($request);
    }

    private function analyticsEnabled(): bool
    {
        try {
            return (bool)($this->extensionConfiguration->get('neoblack_webmcp', 'analyticsEnabled') ?? true);
        } catch (\Throwable) {
            return true;
        }
    }

    private function rateLimit(): int
    {
        try {
            return (int)($this->extensionConfiguration->get('neoblack_webmcp', 'analyticsRateLimit') ?? 60);
        } catch (\Throwable) {
            return 60;
        }
    }

    /**
     * Reduce the (self-reported) client hint to a short, safe token. Keeps
     * letters, digits, space and .-_/(); collapses the rest; caps the length.
     */
    private function sanitizeClient(string $client): string
    {
        $client = trim(preg_replace('/[^\p{L}\p{N} ._\-\/()]+/u', ' ', $client) ?? '');
        $client = trim((string)preg_replace('/\s+/', ' ', $client));
        if ($client === '') {
            return 'unbekannt';
        }
        return mb_substr($client, 0, 64);
    }

    private function noContent(): ResponseInterface
    {
        return $this->responseFactory->createResponse(204);
    }
}
