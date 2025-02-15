<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Core\Persistence\Legacy\Filter\SortClauseQueryBuilder\Content;

use Ibexa\Contracts\Core\Persistence\Filter\Doctrine\FilteringQueryBuilder;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause\SectionIdentifier;
use Ibexa\Contracts\Core\Repository\Values\Filter\FilteringSortClause;
use Ibexa\Contracts\Core\Repository\Values\Filter\SortClauseQueryBuilder;
use Ibexa\Core\Persistence\Legacy\Content\Section\Gateway as SectionGateway;

class SectionIdentifierSortClauseQueryBuilder implements SortClauseQueryBuilder
{
    public function accepts(FilteringSortClause $sortClause): bool
    {
        return $sortClause instanceof SectionIdentifier;
    }

    public function buildQuery(
        FilteringQueryBuilder $queryBuilder,
        FilteringSortClause $sortClause
    ): void {
        $queryBuilder
            ->addSelect('section.identifier')
            ->joinOnce(
                'content',
                SectionGateway::CONTENT_SECTION_TABLE,
                'section',
                'content.section_id = section.id'
            );

        /** @var \Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause $sortClause */
        $queryBuilder->addOrderBy('section.identifier', $sortClause->direction);
    }
}

class_alias(SectionIdentifierSortClauseQueryBuilder::class, 'eZ\Publish\Core\Persistence\Legacy\Filter\SortClauseQueryBuilder\Content\SectionIdentifierSortClauseQueryBuilder');
