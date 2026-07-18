<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Dto;

/**
 * Aggregated, view-ready usage statistics produced by the StatisticsService.
 *
 * @phpstan-type Bar array{label: string, count: int, pct: int}
 * @phpstan-type Event array{tool: string, client: string, date: string}
 */
final class Statistics
{
    /**
     * @param list<Bar>    $perTool
     * @param list<Bar>    $perClient
     * @param list<Bar>    $perDay
     * @param list<Event>  $recent
     * @param list<string> $tools
     * @param list<string> $clients
     */
    public function __construct(
        public readonly int $total,
        public readonly array $perTool,
        public readonly array $perClient,
        public readonly array $perDay,
        public readonly array $recent,
        public readonly array $tools,
        public readonly array $clients,
    ) {
    }
}
