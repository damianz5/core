<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Core\Persistence\Legacy\Content\UrlWildcard\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Ibexa\Contracts\Core\Persistence\Content\UrlWildcard;
use Ibexa\Core\Persistence\Legacy\Content\UrlWildcard\Gateway;

/**
 * URL wildcard gateway implementation using the Doctrine database.
 *
 * @internal Gateway implementation is considered internal. Use Persistence UrlWildcard Handler instead.
 *
 * @see \Ibexa\Contracts\Core\Persistence\Content\UrlWildcard\Handler
 */
final class DoctrineDatabase extends Gateway
{
    /**
     * 2^30, since PHP_INT_MAX can cause overflows in DB systems, if PHP is run
     * on 64 bit systems.
     */
    private const MAX_LIMIT = 1073741824;

    /** @var \Doctrine\DBAL\Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function insertUrlWildcard(UrlWildcard $urlWildcard): int
    {
        $query = $this->connection->createQueryBuilder();
        $query
            ->insert(self::URL_WILDCARD_TABLE)
            ->values(
                [
                    'destination_url' => $query->createPositionalParameter(
                        $this->trimUrl($urlWildcard->destinationUrl),
                        ParameterType::STRING
                    ),
                    'source_url' => $query->createPositionalParameter(
                        $this->trimUrl($urlWildcard->sourceUrl),
                        ParameterType::STRING
                    ),
                    'type' => $query->createPositionalParameter(
                        $urlWildcard->forward ? 1 : 2,
                        ParameterType::INTEGER
                    ),
                ]
            );

        $query->execute();

        return (int)$this->connection->lastInsertId(self::URL_WILDCARD_SEQ);
    }

    public function updateUrlWildcard(
        int $id,
        string $sourceUrl,
        string $destinationUrl,
        bool $forward
    ): void {
        $query = $this->connection->createQueryBuilder();

        $query
            ->update(self::URL_WILDCARD_TABLE)
            ->set(
                'destination_url',
                $query->createPositionalParameter(
                    $this->trimUrl($destinationUrl),
                    ParameterType::STRING
                ),
            )->set(
                'source_url',
                $query->createPositionalParameter(
                    $this->trimUrl($sourceUrl),
                    ParameterType::STRING
                ),
            )->set(
                'type',
                $query->createPositionalParameter(
                    $forward ? 1 : 2,
                    ParameterType::INTEGER
                )
            );

        $query->where(
            $query->expr()->eq(
                'id',
                $query->createPositionalParameter(
                    $id,
                    ParameterType::INTEGER
                )
            )
        );

        $query->execute();
    }

    public function deleteUrlWildcard(int $id): void
    {
        $query = $this->connection->createQueryBuilder();
        $query
            ->delete(self::URL_WILDCARD_TABLE)
            ->where(
                $query->expr()->eq(
                    'id',
                    $query->createPositionalParameter($id, ParameterType::INTEGER)
                )
            );
        $query->execute();
    }

    private function buildLoadUrlWildcardDataQuery(): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query
            ->select('id', 'destination_url', 'source_url', 'type')
            ->from(self::URL_WILDCARD_TABLE);

        return $query;
    }

    public function loadUrlWildcardData(int $id): array
    {
        $query = $this->buildLoadUrlWildcardDataQuery();
        $query
            ->where(
                $query->expr()->eq(
                    'id',
                    $query->createPositionalParameter($id, ParameterType::INTEGER)
                )
            );
        $result = $query->execute()->fetch(FetchMode::ASSOCIATIVE);

        return false !== $result ? $result : [];
    }

    public function loadUrlWildcardsData(int $offset = 0, int $limit = -1): array
    {
        $query = $this->buildLoadUrlWildcardDataQuery();
        $query
            ->setMaxResults($limit > 0 ? $limit : self::MAX_LIMIT)
            ->setFirstResult($offset);

        $stmt = $query->execute();

        return $stmt->fetchAll(FetchMode::ASSOCIATIVE);
    }

    public function loadUrlWildcardBySourceUrl(string $sourceUrl): array
    {
        $query = $this->buildLoadUrlWildcardDataQuery();
        $expr = $query->expr();
        $query
            ->where(
                $expr->eq(
                    'source_url',
                    $query->createPositionalParameter($sourceUrl)
                )
            );

        $result = $query->execute()->fetch(FetchMode::ASSOCIATIVE);

        return false !== $result ? $result : [];
    }

    public function countAll(): int
    {
        $query = $this->connection->createQueryBuilder();
        $query
            ->select($this->connection->getDatabasePlatform()->getCountExpression('id'))
            ->from(self::URL_WILDCARD_TABLE);

        return (int) $query->execute()->fetchColumn();
    }

    private function trimUrl(string $url): string
    {
        return trim($url, '/');
    }
}

class_alias(DoctrineDatabase::class, 'eZ\Publish\Core\Persistence\Legacy\Content\UrlWildcard\Gateway\DoctrineDatabase');
