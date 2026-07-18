<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Security;

use Neoblack\Webmcp\Security\RateLimiter;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RateLimiterTest extends UnitTestCase
{
    private const NOW = 1700000000;

    public function testAllowsWhenUnderLimitAndIncrements(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturn(3);
        $cache->expects(self::once())->method('set')->with(self::anything(), 4, [], self::anything());

        self::assertTrue($this->limiter($cache)->allow('1.2.3.4', 10));
    }

    public function testDeniesAtLimitWithoutIncrementing(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturn(10);
        $cache->expects(self::never())->method('set');

        self::assertFalse($this->limiter($cache)->allow('1.2.3.4', 10));
    }

    public function testDisabledWhenLimitIsZero(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::never())->method('get');
        $cache->expects(self::never())->method('set');

        self::assertTrue($this->limiter($cache)->allow('1.2.3.4', 0));
    }

    public function testFirstCallInWindowIsAllowed(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        // Cache miss returns false → treated as 0.
        $cache->method('get')->willReturn(false);
        $cache->expects(self::once())->method('set')->with(self::anything(), 1, [], self::anything());

        self::assertTrue($this->limiter($cache)->allow('1.2.3.4', 5));
    }

    private function limiter(FrontendInterface $cache): RateLimiter
    {
        $context = new Context();
        $context->setAspect('date', new DateTimeAspect((new \DateTimeImmutable())->setTimestamp(self::NOW)));

        return new RateLimiter($cache, $context);
    }
}
