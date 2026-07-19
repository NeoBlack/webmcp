<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Middleware;

use Neoblack\Webmcp\Form\FormSubmissionResult;
use Neoblack\Webmcp\Form\FormSubmissionServiceInterface;
use Neoblack\Webmcp\Form\FormToken;
use Neoblack\Webmcp\Middleware\FormSubmitMiddleware;
use Neoblack\Webmcp\Security\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FormSubmitMiddlewareTest extends UnitTestCase
{
    private FormToken $formToken;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 96);
        $this->formToken = new FormToken(new HashService());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
        parent::tearDown();
    }

    private function middleware(FormSubmissionServiceInterface $service, ?RateLimiter $rateLimiter = null): FormSubmitMiddleware
    {
        return new FormSubmitMiddleware(new ResponseFactory(), $this->formToken, $service, $rateLimiter ?? $this->permissiveLimiter());
    }

    private function permissiveLimiter(): RateLimiter
    {
        $limiter = $this->createStub(RateLimiter::class);
        $limiter->method('allow')->willReturn(true);

        return $limiter;
    }

    private function request(string $body, string $method = 'POST', string $path = '/webmcp-form'): ServerRequestInterface
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        return (new ServerRequest('https://example.org' . $path, $method, $stream))
            ->withHeader('sec-fetch-site', 'same-origin');
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // Marker so a pass-through is distinguishable from a handled response.
                return (new Response())->withHeader('X-Passed-Through', '1');
            }
        };
    }

    private function service(FormSubmissionResult $result): FormSubmissionServiceInterface
    {
        return new class($result) implements FormSubmissionServiceInterface {
            public function __construct(private FormSubmissionResult $result)
            {
            }

            public function submit(ServerRequestInterface $request, string $persistenceIdentifier, array $values): FormSubmissionResult
            {
                return $this->result;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody()->getContents(), true) ?? [];
    }

    public function testPassesThroughForOtherPaths(): void
    {
        $response = $this->middleware($this->service(FormSubmissionResult::ok('x')))
            ->process($this->request('{}', 'POST', '/something'), $this->handler());

        self::assertTrue($response->hasHeader('X-Passed-Through'));
    }

    public function testPassesThroughForNonPost(): void
    {
        $response = $this->middleware($this->service(FormSubmissionResult::ok('x')))
            ->process($this->request('', 'GET'), $this->handler());

        self::assertTrue($response->hasHeader('X-Passed-Through'));
    }

    public function testRejectsCrossOrigin(): void
    {
        $request = $this->request('{}')->withHeader('sec-fetch-site', 'cross-site');
        $response = $this->middleware($this->service(FormSubmissionResult::ok('x')))->process($request, $this->handler());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testRejectsWhenRateLimited(): void
    {
        $limiter = $this->createStub(RateLimiter::class);
        $limiter->method('allow')->willReturn(false);

        $response = $this->middleware($this->service(FormSubmissionResult::ok('x')), $limiter)
            ->process($this->request('{}'), $this->handler());

        self::assertSame(429, $response->getStatusCode());
    }

    public function testRejectsInvalidToken(): void
    {
        $body = json_encode(['token' => 'forged', 'values' => []]);
        $response = $this->middleware($this->service(FormSubmissionResult::ok('x')))
            ->process($this->request((string) $body), $this->handler());

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($this->decode($response)['ok']);
    }

    public function testReturnsSuccessForValidToken(): void
    {
        $body = json_encode([
            'token' => $this->formToken->sign('1:/form/contact.form.yaml'),
            'values' => ['name' => 'Ada'],
        ]);

        $response = $this->middleware($this->service(FormSubmissionResult::ok('Received.')))
            ->process($this->request((string) $body), $this->handler());

        $decoded = $this->decode($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($decoded['ok']);
        self::assertSame('Received.', $decoded['message']);
    }

    public function testReturnsValidationErrors(): void
    {
        $body = json_encode(['token' => $this->formToken->sign('1:/form/contact.form.yaml'), 'values' => []]);
        $result = FormSubmissionResult::invalid('Invalid.', ['name' => 'This field is required.']);

        $response = $this->middleware($this->service($result))->process($this->request((string) $body), $this->handler());

        $decoded = $this->decode($response);
        self::assertFalse($decoded['ok']);
        self::assertSame(['name' => 'This field is required.'], $decoded['errors']);
    }
}
