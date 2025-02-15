<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Core\FieldType\Relation;

use Ibexa\Core\FieldType\Value as BaseValue;

/**
 * Value for Relation field type.
 *
 * @deprecated Since 7.0 and will be removed in 8.0. Use `RelationList\Value` instead.
 */
class Value extends BaseValue
{
    /**
     * Related content.
     *
     * @var mixed|null
     */
    public $destinationContentId;

    /**
     * Construct a new Value object and initialize it $destinationContent.
     *
     * @param mixed $destinationContentId Content id the relation is to
     */
    public function __construct($destinationContentId = null)
    {
        $this->destinationContentId = $destinationContentId;
    }

    /**
     * Returns the related content's name.
     */
    public function __toString()
    {
        return (string)$this->destinationContentId;
    }
}

class_alias(Value::class, 'eZ\Publish\Core\FieldType\Relation\Value');
