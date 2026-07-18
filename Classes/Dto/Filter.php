<?php

declare(strict_types=1);

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
    ) {}
}
