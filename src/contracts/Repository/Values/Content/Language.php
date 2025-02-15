<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Core\Repository\Values\Content;

use Ibexa\Contracts\Core\Repository\Values\ValueObject;

/**
 * This class represents a language in the repository.
 *
 * @property-read mixed $id the language id
 * @property-read string $languageCode the language code in
 * @property-read string $name human readable name of the language
 * @property-read bool $enabled indicates if the language is enabled or not.
 */
class Language extends ValueObject
{
    /**
     * Constant for use in API's to specify that you want to load all languages.
     */
    public const ALL = [];

    /**
     * The language id (auto generated).
     *
     * @var mixed
     */
    protected $id;

    /**
     * the languageCode code.
     *
     * @var string
     */
    protected $languageCode;

    /**
     * Human readable name of the language.
     *
     * @var string
     */
    protected $name;

    /**
     * Indicates if the language is enabled or not.
     *
     * @var bool
     */
    protected $enabled;
}

class_alias(Language::class, 'eZ\Publish\API\Repository\Values\Content\Language');
