<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Contracts\Core\Repository;

use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Contracts\Core\Repository\Values\Content\LanguageCreateStruct;

/**
 * Language service, used for language operations.
 */
interface LanguageService
{
    /**
     * Creates the a new Language in the content repository.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\LanguageCreateStruct $languageCreateStruct
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException If user does not have access to content translations
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the languageCode already exists
     */
    public function createLanguage(LanguageCreateStruct $languageCreateStruct): Language;

    /**
     * Changes the name of the language in the content repository.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Language $language
     * @param string $newName
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException If user does not have access to content translations
     */
    public function updateLanguageName(Language $language, string $newName): Language;

    /**
     * Enables a language.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Language $language
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException If user does not have access to content translations
     */
    public function enableLanguage(Language $language): Language;

    /**
     * Disables a language.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Language $language
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException If user does not have access to content translations
     */
    public function disableLanguage(Language $language): Language;

    /**
     * Loads a Language from its language code ($languageCode).
     *
     * @param string $languageCode
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException if language could not be found
     */
    public function loadLanguage(string $languageCode): Language;

    /**
     * Loads all Languages.
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language[]
     */
    public function loadLanguages(): iterable;

    /**
     * Loads a Language by its id ($languageId).
     *
     * @param int $languageId
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException if language could not be found
     */
    public function loadLanguageById(int $languageId): Language;

    /**
     * Bulk-load Languages by language codes.
     *
     * Note: it does not throw exceptions on load, just ignores erroneous Languages.
     *
     * @param string[] $languageCodes
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language[] list of Languages with language-code as keys
     */
    public function loadLanguageListByCode(array $languageCodes): iterable;

    /**
     * Bulk-load Languages by ids.
     *
     * Note: it does not throw exceptions on load, just ignores erroneous Languages.
     *
     * @param int[] $languageIds
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\Language[] list of Languages with id as keys
     */
    public function loadLanguageListById(array $languageIds): iterable;

    /**
     * Deletes  a language from content repository.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Language $language
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if language can not be deleted
     *         because it is still assigned to some content / type / (...).
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException If user is not allowed to delete a language
     */
    public function deleteLanguage(Language $language): void;

    /**
     * Returns a configured default language code.
     *
     * @return string
     */
    public function getDefaultLanguageCode(): string;

    /**
     * Instantiates an object to be used for creating languages.
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\LanguageCreateStruct
     */
    public function newLanguageCreateStruct(): LanguageCreateStruct;
}

class_alias(LanguageService::class, 'eZ\Publish\API\Repository\LanguageService');
