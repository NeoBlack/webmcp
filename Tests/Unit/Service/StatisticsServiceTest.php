<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Service;

use Neoblack\Webmcp\Domain\Repository\EventRepository;
use Neoblack\Webmcp\Dto\Filter;
use Neoblack\Webmcp\Service\StatisticsService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class StatisticsServiceTest extends UnitTestCase
{
    private const NOW = 1700000000;

    public function testCollectAggregatesRepositoryData(): void
    {
        $repository = $this->createStub(EventRepository::class);
        $repository->method('countSince')->willReturn(5);
        $repository->method('groupedCounts')->willReturnCallback(
            static fn (string $column): array => 'tool' === $column
                ? [['label' => 'filter_blog', 'count' => 4], ['label' => 'search_articles', 'count' => 1]]
                : [['label' => 'Claude', 'count' => 5]],
        );
        $repository->method('distinctValues')->willReturnCallback(
            static fn (string $column): array => 'tool' === $column
                ? ['filter_blog', 'search_articles']
                : ['Claude'],
        );
        // Two events "today" so they land in the last timeline bucket.
        $repository->method('timestampsSince')->willReturn([self::NOW, self::NOW]);
        $repository->method('recent')->willReturn([
            ['tool' => 'filter_blog', 'client' => 'Claude', 'crdate' => self::NOW],
        ]);

        $statistics = (new StatisticsService($repository, $this->contextAt(self::NOW)))
            ->collect(new Filter('', '', 7));

        self::assertSame(5, $statistics->total);

        // Percentages are relative to the largest count in each group.
        self::assertSame([
            ['label' => 'filter_blog', 'count' => 4, 'pct' => 100],
            ['label' => 'search_articles', 'count' => 1, 'pct' => 25],
        ], $statistics->perTool);
        self::assertSame([['label' => 'Claude', 'count' => 5, 'pct' => 100]], $statistics->perClient);

        // 7-day window → 7 zero-filled buckets, newest last.
        self::assertCount(7, $statistics->perDay);
        $today = $statistics->perDay[6];
        self::assertSame(2, $today['count']);
        self::assertSame(100, $today['pct']);
        self::assertSame(date('d.m.', self::NOW), $today['label']);
        self::assertSame(0, $statistics->perDay[0]['count']);

        self::assertSame(['filter_blog', 'search_articles'], $statistics->tools);
        self::assertSame(['Claude'], $statistics->clients);

        self::assertCount(1, $statistics->recent);
        self::assertSame(date('d.m.Y H:i', self::NOW), $statistics->recent[0]['date']);
    }

    public function testEmptyDataProducesZeroPercentages(): void
    {
        $repository = $this->createStub(EventRepository::class);
        $repository->method('countSince')->willReturn(0);
        $repository->method('groupedCounts')->willReturn([]);
        $repository->method('distinctValues')->willReturn([]);
        $repository->method('timestampsSince')->willReturn([]);
        $repository->method('recent')->willReturn([]);

        $statistics = (new StatisticsService($repository, $this->contextAt(self::NOW)))
            ->collect(new Filter('', '', 30));

        self::assertSame(0, $statistics->total);
        self::assertSame([], $statistics->perTool);
        self::assertCount(30, $statistics->perDay);
        self::assertSame(0, $statistics->perDay[0]['pct']);
    }

    private function contextAt(int $timestamp): Context
    {
        $context = new Context();
        $context->setAspect('date', new DateTimeAspect((new \DateTimeImmutable())->setTimestamp($timestamp)));

        return $context;
    }
}
