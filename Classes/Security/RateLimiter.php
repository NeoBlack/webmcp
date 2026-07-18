<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Security;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;

/**
 * Fixed-window rate limiter for the ingest endpoint. Allows up to $limit calls
 * per client per 60-second window, counted in the injected cache.
 *
 * Privacy-preserving: the client identifier (IP) is only ever hashed into the
 * cache key together with the window number, and entries expire after two
 * windows — no plaintext IP is stored anywhere.
 *
 * Not final so it can be doubled in tests.
 */
class RateLimiter
{
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly Context $context,
    ) {
    }

    /**
     * Returns true if another call from $clientId is allowed. A $limit of 0 (or
     * less) disables limiting entirely.
     */
    public function allow(string $clientId, int $limit): bool
    {
        if ($limit <= 0) {
            return true;
        }

        $now = (int) $this->context->getPropertyFromAspect('date', 'timestamp', 0) ?: time();
        $window = intdiv($now, self::WINDOW_SECONDS);
        $identifier = 'rl_' . sha1($clientId . '|' . $window);

        $count = (int) $this->cache->get($identifier);
        if ($count >= $limit) {
            return false;
        }

        $this->cache->set($identifier, $count + 1, [], self::WINDOW_SECONDS * 2);

        return true;
    }
}
