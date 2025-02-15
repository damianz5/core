<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Core\Search\Legacy\Content\Common\Gateway\CriterionHandler\FieldValue\Handler;

use Doctrine\DBAL\Query\QueryBuilder;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;

/**
 * FieldValue CriterionHandler handling ezkeyword External Storage for Legacy/SQL Search.
 */
class Keyword extends Collection
{
    public function handle(
        QueryBuilder $outerQuery,
        QueryBuilder $subQuery,
        Criterion $criterion,
        string $column
    ) {
        $subQuery
            ->innerJoin(
                'f_def',
                'ezkeyword_attribute_link',
                'kwd_lnk',
                'f_def.id = kwd_lnk.objectattribute_id'
            )
            ->innerJoin(
                'kwd_lnk',
                'ezkeyword',
                'kwd',
                'kwd.id = kwd_lnk.keyword_id'
            );

        return parent::handle($outerQuery, $subQuery, $criterion, 'keyword');
    }
}

class_alias(Keyword::class, 'eZ\Publish\Core\Search\Legacy\Content\Common\Gateway\CriterionHandler\FieldValue\Handler\Keyword');
