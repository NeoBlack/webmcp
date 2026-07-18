<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Middleware;

use Neoblack\Webmcp\Domain\Repository\EventRepository;
use Neoblack\Webmcp\Middleware\EventMiddleware;
use Neoblack\Webmcp\Registry\ToolRegistry;
use Neoblack\Webmcp\Security\RateLimiter;
use Neoblack\Webmcp\Tool\Manifest;
use Neoblack\Webmcp\Tool\ToolProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class EventMiddlewareTest extends UnitTestCase
{
    private const NOW = 1700000000;

    /** Sentinel status so a pass-through (handler) is distinguishable from a 204. */
    private const PASS_THROUGH = 418;

    public function testPassesThroughNonMatchingRequests(): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects(self::never())->method('log');

        $response = $this->process(
            $this->request('GET', '/webmcp-event'),
            $repository,
            analyticsEnabled: true,
            toolNames: ['filter_blog'],
        );

        self::assertSame(self::PASS_THROUGH, $response->getStatusCode());
    }

    public function testPassesThroughWhenAnalyticsDisabled(): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects(self::never())->method('log');

        $response = $this->process(
            $this->request('POST', '/webmcp-event', ['tool' => 'filter_blog']),
            $repository,
            analyticsEnabled: false,
            toolNames: ['filter_blog'],
        );

        self::assertSame(self::PASS_THROUGH, $response->getStatusCode());
    }

    public function testRejectsCrossSiteWithoutLogging(): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects(self::never())->method('log');

        $request = $this->request('POST', '/webmcp-event', ['tool' => 'filter_blog'])
            ->withHeader('sec-fetch-site', 'cross-site');

        $response = $this->process($request, $repository, true, ['filter_blog']);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testLogsKnownToolAndReturns204(): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects(self::once())->method('log')
            ->with('filter_blog', 'Claude', self::NOW);

        $request = $this->request('POST', '/webmcp-event', ['tool' => 'filter_blog', 'client' => 'Claude'])
            ->withHeader('sec-fetch-site', 'same-origin');

        $response = $this->process($request, $repository, true, ['filter_blog']);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testPassesThroughUnknownTool(): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects(self::never())->method('log');

        $response = $this->process(
            $this->request('POST', '/webmcp-event', ['tool' => 'not_registered']),
            $repository,
            true,
            ['filter_blog'],
        );

        self::assertSame(self::PASS_THROUGH, $response->getStatusCode());
    }

    public function testRejectsWhenRateLimited(): void
    {
        $repository = $this->createMock(EventRepository::class);
        $repository->expects(self::never())->method('log');

        $request = $this->request('POST', '/webmcp-event', ['tool' => 'filter_blog', 'client' => 'Claude'])
            ->withHeader('sec-fetch-site', 'same-origin');

        $response = $this->process($request, $repository, true, ['filter_blog'], rateLimitAllows: false);

        self::assertSame(429, $response->getStatusCode());
    }

    /**
     * @param list<string> $toolNames
     */
    private function process(
        ServerRequestInterface $request,
        EventRepository $repository,
        bool $analyticsEnabled,
        array $toolNames,
        bool $rateLimitAllows = true,
    ): ResponseInterface {
        // Real registry with fake providers; toolNames() derives from provider names.
        $providers = array_map(
            static fn (string $name): ToolProviderInterface => new class($name) implements ToolProviderInterface {
                public function __construct(private string $toolName)
                {
                }

                public function name(): string
                {
                    return $this->toolName;
                }

                public function manifest(ContentObjectRenderer $cObj, array $processedData): ?Manifest
                {
                    return null;
                }
            },
            $toolNames,
        );
        $registry = new ToolRegistry($providers);

        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($analyticsEnabled ? '1' : '0');

        $context = new Context();
        $context->setAspect('date', new DateTimeAspect((new \DateTimeImmutable())->setTimestamp(self::NOW)));

        $rateLimiter = $this->createStub(RateLimiter::class);
        $rateLimiter->method('allow')->willReturn($rateLimitAllows);

        $middleware = new EventMiddleware(
            new ResponseFactory(),
            $registry,
            $extensionConfiguration,
            $repository,
            $context,
            $rateLimiter,
        );

        $handler = new class(self::PASS_THROUGH) implements RequestHandlerInterface {
            public function __construct(private int $status)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(null, $this->status);
            }
        };

        return $middleware->process($request, $handler);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(string $method, string $path, array $body = []): ServerRequestInterface
    {
        // A writable stream for the body (the default php://input is read-only).
        $stream = new Stream('php://temp', 'wb+');
        if ([] !== $body) {
            $stream->write((string) json_encode($body));
            $stream->rewind();
        }

        return new ServerRequest('https://example.org' . $path, $method, $stream);
    }
}
