<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Data access for the WebMCP usage event log (tx_neoblackwebmcp_event). All
 * SQL for the append-only log lives here; the middleware writes through it and
 * the statistics service reads through it.
 */
class EventRepository
{
    private const TABLE = 'tx_neoblackwebmcp_event';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * Append one event. The timestamp is passed in so callers stay
     * deterministic (and testable).
     */
    public function log(string $tool, string $client, int $crdate): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->insert(self::TABLE, [
            'crdate' => $crdate,
            'tool' => $tool,
            'client' => $client,
        ]);
    }

    public function countSince(int $since, string $tool, string $client): int
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->count('uid')->from(self::TABLE);
        $this->constrain($queryBuilder, $since, $tool, $client);

        return (int) $queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * COUNT(*) grouped by a column (tool|client), most frequent first.
     *
     * @return list<array{label: string, count: int}>
     */
    public function groupedCounts(string $column, int $since, string $tool, string $client): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->selectLiteral(sprintf('%s AS label', $queryBuilder->quoteIdentifier($column)), 'COUNT(*) AS cnt')
            ->from(self::TABLE)
            ->groupBy($column)
            ->orderBy('cnt', 'DESC')
            ->addOrderBy('label', 'ASC');
        $this->constrain($queryBuilder, $since, $tool, $client);

        $rows = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $rows[] = ['label' => (string) $row['label'], 'count' => (int) $row['cnt']];
        }

        return $rows;
    }

    /**
     * Distinct values of a column over all time (for the filter chips).
     *
     * @return list<string>
     */
    public function distinctValues(string $column): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $values = $queryBuilder
            ->select($column)
            ->from(self::TABLE)
            ->groupBy($column)
            ->orderBy($column)
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(strval(...), $values);
    }

    /**
     * Raw event timestamps in the window (for the per-day timeline).
     *
     * @return list<int>
     */
    public function timestampsSince(int $since, string $tool, string $client): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->select('crdate')->from(self::TABLE);
        $this->constrain($queryBuilder, $since, $tool, $client);

        return array_map(intval(...), $queryBuilder->executeQuery()->fetchFirstColumn());
    }

    /**
     * The most recent events in the window, newest first.
     *
     * @return list<array{tool: string, client: string, crdate: int}>
     */
    public function recent(int $since, string $tool, string $client, int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('tool', 'client', 'crdate')
            ->from(self::TABLE)
            ->orderBy('crdate', 'DESC')
            ->setMaxResults($limit);
        $this->constrain($queryBuilder, $since, $tool, $client);

        $rows = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $rows[] = [
                'tool' => (string) $row['tool'],
                'client' => (string) $row['client'],
                'crdate' => (int) $row['crdate'],
            ];
        }

        return $rows;
    }

    private function constrain(QueryBuilder $queryBuilder, int $since, string $tool, string $client): void
    {
        $queryBuilder->where(
            $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, Connection::PARAM_INT)),
        );
        if ('' !== $tool) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('tool', $queryBuilder->createNamedParameter($tool)));
        }
        if ('' !== $client) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('client', $queryBuilder->createNamedParameter($client)));
        }
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }
}
