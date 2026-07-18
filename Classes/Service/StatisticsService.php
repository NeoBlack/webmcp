<?php

declare(strict_types=1);

namespace Neoblack\Webmcp\Service;

use Neoblack\Webmcp\Domain\Repository\EventRepository;
use Neoblack\Webmcp\Dto\Filter;
use Neoblack\Webmcp\Dto\Statistics;
use TYPO3\CMS\Core\Context\Context;

/**
 * Turns the raw event log into the view-ready {@see Statistics} shown by the
 * backend module: totals, per-tool / per-client bars, a zero-filled per-day
 * timeline and the most recent events. "Now" comes from the Context date
 * aspect, keeping the window boundaries deterministic and testable.
 */
final class StatisticsService
{
    private const RECENT_LIMIT = 100;

    public function __construct(
        private readonly EventRepository $repository,
        private readonly Context $context,
    ) {}

    public function collect(Filter $filter): Statistics
    {
        $now = (int)$this->context->getPropertyFromAspect('date', 'timestamp', 0) ?: time();
        $since = $now - $filter->days * 86400;

        return new Statistics(
            total: $this->repository->countSince($since, $filter->tool, $filter->client),
            perTool: $this->withPercentage($this->repository->groupedCounts('tool', $since, $filter->tool, $filter->client)),
            perClient: $this->withPercentage($this->repository->groupedCounts('client', $since, $filter->tool, $filter->client)),
            perDay: $this->buildTimeline($since, $filter->days, $filter->tool, $filter->client, $now),
            recent: $this->formatRecent($this->repository->recent($since, $filter->tool, $filter->client, self::RECENT_LIMIT)),
            tools: $this->repository->distinctValues('tool'),
            clients: $this->repository->distinctValues('client'),
        );
    }

    /**
     * Add a percentage (of the largest count) to grouped rows for the bars.
     *
     * @param list<array{label: string, count: int}> $rows
     * @return list<array{label: string, count: int, pct: int}>
     */
    private function withPercentage(array $rows): array
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, $row['count']);
        }

        return array_map(
            static fn (array $row): array => $row + ['pct' => $max > 0 ? (int)round($row['count'] / $max * 100) : 0],
            $rows,
        );
    }

    /**
     * Events per calendar day across the window (zero-filled), so the timeline
     * has one bar per day, oldest first.
     *
     * @return list<array{label: string, count: int, pct: int}>
     */
    private function buildTimeline(int $since, int $days, string $tool, string $client, int $now): array
    {
        $buckets = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $buckets[date('Y-m-d', $now - $i * 86400)] = 0;
        }
        foreach ($this->repository->timestampsSince($since, $tool, $client) as $timestamp) {
            $key = date('Y-m-d', $timestamp);
            if (isset($buckets[$key])) {
                $buckets[$key]++;
            }
        }

        $max = $buckets === [] ? 0 : max($buckets);
        $series = [];
        foreach ($buckets as $day => $count) {
            $series[] = [
                'label' => date('d.m.', (int)strtotime($day)),
                'count' => $count,
                'pct' => $max > 0 ? (int)round($count / $max * 100) : 0,
            ];
        }

        return $series;
    }

    /**
     * @param list<array{tool: string, client: string, crdate: int}> $events
     * @return list<array{tool: string, client: string, date: string}>
     */
    private function formatRecent(array $events): array
    {
        return array_map(
            static fn (array $event): array => [
                'tool' => $event['tool'],
                'client' => $event['client'],
                'date' => date('d.m.Y H:i', $event['crdate']),
            ],
            $events,
        );
    }
}
