<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Functional\Domain\Repository;

use Neoblack\Webmcp\Domain\Repository\EventRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class EventRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['neoblack/webmcp'];

    /** Needed so the container can resolve the (public) DashboardController -> ModuleTemplateFactory. */
    protected array $coreExtensionsToLoad = ['backend'];
    private EventRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new EventRepository(GeneralUtility::makeInstance(ConnectionPool::class));
    }

    public function testLogInsertsSingleRow(): void
    {
        $this->subject->log('filter_blog', 'Claude', 1700000000);

        $rows = $this->allRows();
        self::assertCount(1, $rows);
        self::assertSame('filter_blog', $rows[0]['tool']);
        self::assertSame('Claude', $rows[0]['client']);
        self::assertSame(1700000000, (int) $rows[0]['crdate']);
    }

    public function testCountSinceRespectsWindowAndFilters(): void
    {
        $this->subject->log('a', 'C1', 1000);
        $this->subject->log('a', 'C2', 3000);
        $this->subject->log('b', 'C1', 4000);

        self::assertSame(2, $this->subject->countSince(2500, '', ''), 'window only');
        self::assertSame(1, $this->subject->countSince(2500, 'a', ''), 'window + tool');
        self::assertSame(1, $this->subject->countSince(0, '', 'C2'), 'client filter');
    }

    public function testGroupedCountsOrderedByFrequency(): void
    {
        foreach (['a', 'a', 'a', 'b', 'b', 'c'] as $i => $tool) {
            $this->subject->log($tool, 'C', 1000 + $i);
        }

        self::assertSame(
            [
                ['label' => 'a', 'count' => 3],
                ['label' => 'b', 'count' => 2],
                ['label' => 'c', 'count' => 1],
            ],
            $this->subject->groupedCounts('tool', 0, '', ''),
        );
    }

    public function testDistinctValuesSortedAlphabetically(): void
    {
        $this->subject->log('search', 'B', 1000);
        $this->subject->log('filter', 'A', 1001);
        $this->subject->log('filter', 'A', 1002);

        self::assertSame(['filter', 'search'], $this->subject->distinctValues('tool'));
        self::assertSame(['A', 'B'], $this->subject->distinctValues('client'));
    }

    public function testTimestampsSinceReturnsIntsInWindow(): void
    {
        $this->subject->log('a', 'C', 1000);
        $this->subject->log('a', 'C', 2000);

        self::assertSame([2000], $this->subject->timestampsSince(1500, '', ''));
    }

    public function testRecentNewestFirstWithLimit(): void
    {
        $this->subject->log('a', 'C', 1000);
        $this->subject->log('b', 'C', 2000);
        $this->subject->log('c', 'C', 3000);

        $recent = $this->subject->recent(0, '', '', 2);

        self::assertCount(2, $recent);
        self::assertSame('c', $recent[0]['tool']);
        self::assertSame(3000, $recent[0]['crdate']);
        self::assertSame('b', $recent[1]['tool']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allRows(): array
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_neoblackwebmcp_event')
            ->select(['*'], 'tx_neoblackwebmcp_event')
            ->fetchAllAssociative();
    }
}
