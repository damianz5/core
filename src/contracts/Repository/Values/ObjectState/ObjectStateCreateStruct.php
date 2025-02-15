<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Core\Repository\Values\ObjectState;

use Ibexa\Contracts\Core\Repository\Values\ValueObject;

/**
 * This class represents a value for creating object states.
 */
class ObjectStateCreateStruct extends ValueObject
{
    /**
     * Readable unique string identifier of a group.
     *
     * Required.
     *
     * @var string
     */
    public $identifier;

    /**
     * Priority for ordering. If not set the object state is created as the last one.
     *
     * @var int
     */
    public $priority = false;

    /**
     * The default language code.
     *
     * Required.
     *
     * @var string
     */
    public $defaultLanguageCode;

    /**
     * An array of names with languageCode keys.
     *
     * Required. - at least one name in the main language is required
     *
     * @var string[]
     */
    public $names;

    /**
     * An array of descriptions with languageCode keys.
     *
     * @var string[]
     */
    public $descriptions;
}

class_alias(ObjectStateCreateStruct::class, 'eZ\Publish\API\Repository\Values\ObjectState\ObjectStateCreateStruct');
