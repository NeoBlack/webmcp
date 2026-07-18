<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Dto;

/**
 * The dashboard filter: which tool, which client and how many days back. An
 * empty tool/client means "all".
 */
final class Filter
{
    public function __construct(
        public readonly string $tool,
        public readonly string $client,
        public readonly int $days,
    ) {
    }
}
