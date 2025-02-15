<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Core\Repository;

use Exception;
use Ibexa\Contracts\Core\Persistence\Content\URLAlias as SPIURLAlias;
use Ibexa\Contracts\Core\Persistence\Content\UrlAlias\Handler;
use Ibexa\Contracts\Core\Repository\Exceptions\ForbiddenException;
use Ibexa\Contracts\Core\Repository\LanguageResolver;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository as RepositoryInterface;
use Ibexa\Contracts\Core\Repository\URLAliasService as URLAliasServiceInterface;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\URLAlias;
use Ibexa\Core\Base\Exceptions\InvalidArgumentException;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Base\Exceptions\UnauthorizedException;

/**
 * @internal Type-hint \Ibexa\Contracts\Core\Repository\URLAliasService instead.
 */
class URLAliasService implements URLAliasServiceInterface
{
    /** @var \Ibexa\Contracts\Core\Repository\Repository */
    protected $repository;

    /** @var \Ibexa\Contracts\Core\Persistence\Content\UrlAlias\Handler */
    protected $urlAliasHandler;

    /** @var \Ibexa\Core\Repository\Helper\NameSchemaService */
    protected $nameSchemaService;

    /** @var \Ibexa\Contracts\Core\Repository\PermissionResolver */
    private $permissionResolver;

    /** @var \Ibexa\Contracts\Core\Repository\LanguageResolver */
    private $languageResolver;

    public function __construct(
        RepositoryInterface $repository,
        Handler $urlAliasHandler,
        Helper\NameSchemaService $nameSchemaService,
        PermissionResolver $permissionResolver,
        LanguageResolver $languageResolver
    ) {
        $this->repository = $repository;
        $this->urlAliasHandler = $urlAliasHandler;
        $this->nameSchemaService = $nameSchemaService;
        $this->permissionResolver = $permissionResolver;
        $this->languageResolver = $languageResolver;
    }

    /**
     * Create a user chosen $alias pointing to $location in $languageCode.
     *
     * This method runs URL filters and transformers before storing them.
     * Hence the path returned in the URLAlias Value may differ from the given.
     * $alwaysAvailable makes the alias available in all languages.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Location $location
     * @param string $path
     * @param string $languageCode the languageCode for which this alias is valid
     * @param bool $forwarding if true a redirect is performed
     * @param bool $alwaysAvailable
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to create url alias
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the path already exists for the given context
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\BadStateException
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias
     */
    public function createUrlAlias(
        Location $location,
        string $path,
        string $languageCode,
        bool $forwarding = false,
        bool $alwaysAvailable = false
    ): URLAlias {
        if (!$this->permissionResolver->canUser('content', 'urltranslator', $location)) {
            throw new UnauthorizedException('content', 'urltranslator');
        }

        $path = $this->cleanUrl($path);

        $this->repository->beginTransaction();
        try {
            $spiUrlAlias = $this->urlAliasHandler->createCustomUrlAlias(
                $location->id,
                $path,
                $forwarding,
                $languageCode,
                $alwaysAvailable
            );
            $this->repository->commit();
        } catch (ForbiddenException $e) {
            $this->repository->rollback();
            throw new InvalidArgumentException(
                '$path',
                $e->getMessage(),
                $e
            );
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->buildUrlAliasDomainObject($spiUrlAlias, $path);
    }

    /**
     * Create a user chosen $alias pointing to a resource in $languageCode.
     *
     * This method does not handle location resources - if a user enters a location target
     * the createCustomUrlAlias method has to be used.
     * This method runs URL filters and and transformers before storing them.
     * Hence the path returned in the URLAlias Value may differ from the given.
     *
     * $alwaysAvailable makes the alias available in all languages.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to create global
     *          url alias
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the path already exists for the given
     *         language or if resource is not valid
     *
     * @param string $resource
     * @param string $path
     * @param string $languageCode
     * @param bool $forwarding
     * @param bool $alwaysAvailable
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias
     */
    public function createGlobalUrlAlias(
        string $resource,
        string $path,
        string $languageCode,
        bool $forwarding = false,
        bool $alwaysAvailable = false
    ): URLAlias {
        if ($this->permissionResolver->hasAccess('content', 'urltranslator') === false) {
            throw new UnauthorizedException('content', 'urltranslator');
        }

        if (!preg_match('#^([a-zA-Z0-9_]+):(.+)$#', $resource, $matches)) {
            throw new InvalidArgumentException('$resource', 'argument is not valid');
        }

        $path = $this->cleanUrl($path);

        if ($matches[1] === 'eznode' || 0 === strpos($resource, 'module:content/view/full/')) {
            if ($matches[1] === 'eznode') {
                $locationId = $matches[2];
            } else {
                $resourcePath = explode('/', $matches[2]);
                $locationId = end($resourcePath);
            }

            $location = $this->repository->getLocationService()->loadLocation((int)$locationId);

            if (!$this->permissionResolver->canUser('content', 'urltranslator', $location)) {
                throw new UnauthorizedException('content', 'urltranslator');
            }

            return $this->createUrlAlias(
                $location,
                $path,
                $languageCode,
                $forwarding,
                $alwaysAvailable
            );
        }

        $this->repository->beginTransaction();
        try {
            $spiUrlAlias = $this->urlAliasHandler->createGlobalUrlAlias(
                $matches[1] . ':' . $this->cleanUrl($matches[2]),
                $path,
                $forwarding,
                $languageCode,
                $alwaysAvailable
            );
            $this->repository->commit();
        } catch (ForbiddenException $e) {
            $this->repository->rollback();
            throw new InvalidArgumentException('$path', $e->getMessage(), $e);
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $this->buildUrlAliasDomainObject($spiUrlAlias, $path);
    }

    /**
     * List of url aliases pointing to $location, sorted by language priority.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Location $location
     * @param bool $custom if true the user generated aliases are listed otherwise the autogenerated
     * @param string $languageCode filters those which are valid for the given language
     * @param bool|null $showAllTranslations If enabled will include all alias as if they where always available.
     * @param string[]|null $prioritizedLanguages If set used as prioritized language codes, returning first match.
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias[]
     */
    public function listLocationAliases(
        Location $location,
        ?bool $custom = true,
        ?string $languageCode = null,
        ?bool $showAllTranslations = null,
        ?array $prioritizedLanguages = null
    ): iterable {
        $spiUrlAliasList = $this->urlAliasHandler->listURLAliasesForLocation(
            $location->id,
            $custom
        );
        if ($showAllTranslations === null) {
            $showAllTranslations = $this->languageResolver->getShowAllTranslations();
        }
        if ($prioritizedLanguages === null) {
            $prioritizedLanguages = $this->languageResolver->getPrioritizedLanguages();
        }
        $urlAliasList = [];

        foreach ($spiUrlAliasList as $spiUrlAlias) {
            if (
            !$this->isUrlAliasLoadable(
                $spiUrlAlias,
                $languageCode,
                $showAllTranslations,
                $prioritizedLanguages
            )
            ) {
                continue;
            }

            $path = $this->extractPath(
                $spiUrlAlias,
                $languageCode,
                $showAllTranslations,
                $prioritizedLanguages
            );

            if ($path === false) {
                continue;
            }

            $urlAliasList[$spiUrlAlias->id] = $this->buildUrlAliasDomainObject($spiUrlAlias, $path);
        }

        $prioritizedAliasList = [];
        foreach ($prioritizedLanguages as $prioritizedLanguageCode) {
            foreach ($urlAliasList as $urlAlias) {
                foreach ($urlAlias->languageCodes as $aliasLanguageCode) {
                    if ($aliasLanguageCode === $prioritizedLanguageCode) {
                        $prioritizedAliasList[$urlAlias->id] = $urlAlias;
                        break;
                    }
                }
            }
        }

        // Add aliases not matched by prioritized language to the end of the list
        return array_values($prioritizedAliasList + $urlAliasList);
    }

    /**
     * Determines alias language code.
     *
     * Method will return false if language code can't be matched against alias language codes or language settings.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\UrlAlias $spiUrlAlias
     * @param string|null $languageCode
     * @param bool $showAllTranslations
     * @param string[] $prioritizedLanguageList
     *
     * @return string|bool
     */
    protected function selectAliasLanguageCode(
        SPIURLAlias $spiUrlAlias,
        ?string $languageCode,
        bool $showAllTranslations,
        array $prioritizedLanguageList
    ) {
        if (isset($languageCode) && !in_array($languageCode, $spiUrlAlias->languageCodes)) {
            return false;
        }

        foreach ($prioritizedLanguageList as $prioritizedLanguageCode) {
            if (in_array($prioritizedLanguageCode, $spiUrlAlias->languageCodes)) {
                return $prioritizedLanguageCode;
            }
        }

        if ($spiUrlAlias->alwaysAvailable || $showAllTranslations) {
            $lastLevelData = end($spiUrlAlias->pathData);
            reset($lastLevelData['translations']);

            return key($lastLevelData['translations']);
        }

        return false;
    }

    /**
     * Returns path extracted from normalized path data returned from persistence, using language settings.
     *
     * Will return false if path could not be determined.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\UrlAlias $spiUrlAlias
     * @param string $languageCode
     * @param bool $showAllTranslations
     * @param string[] $prioritizedLanguageList
     *
     * @return string|bool
     */
    protected function extractPath(
        SPIURLAlias $spiUrlAlias,
        $languageCode,
        $showAllTranslations,
        array $prioritizedLanguageList
    ) {
        $pathData = [];
        $pathLevels = count($spiUrlAlias->pathData);

        foreach ($spiUrlAlias->pathData as $level => $levelEntries) {
            if ($level === $pathLevels - 1) {
                $prioritizedLanguageCode = $this->selectAliasLanguageCode(
                    $spiUrlAlias,
                    $languageCode,
                    $showAllTranslations,
                    $prioritizedLanguageList
                );
            } else {
                $prioritizedLanguageCode = $this->choosePrioritizedLanguageCode(
                    $levelEntries,
                    $showAllTranslations,
                    $prioritizedLanguageList
                );
            }

            if ($prioritizedLanguageCode === false) {
                return false;
            }

            $pathData[$level] = $levelEntries['translations'][$prioritizedLanguageCode];
        }

        return implode('/', $pathData);
    }

    /**
     * Returns language code with highest priority.
     *
     * Will return false if language code could not be matched with language settings in place.
     *
     * @param array $entries
     * @param bool $showAllTranslations
     * @param string[] $prioritizedLanguageList
     *
     * @return string|bool
     */
    protected function choosePrioritizedLanguageCode(array $entries, $showAllTranslations, array $prioritizedLanguageList)
    {
        foreach ($prioritizedLanguageList as $prioritizedLanguageCode) {
            if (isset($entries['translations'][$prioritizedLanguageCode])) {
                return $prioritizedLanguageCode;
            }
        }

        if ($entries['always-available'] || $showAllTranslations) {
            reset($entries['translations']);

            return key($entries['translations']);
        }

        return false;
    }

    /**
     * Matches path string with normalized path data returned from persistence.
     *
     * Returns matched path string (possibly case corrected) and array of corresponding language codes or false
     * if path could not be matched.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\UrlAlias $spiUrlAlias
     * @param string $path
     * @param string $languageCode
     *
     * @return array
     */
    protected function matchPath(SPIURLAlias $spiUrlAlias, $path, $languageCode)
    {
        $matchedPathElements = [];
        $matchedPathLanguageCodes = [];
        $pathElements = explode('/', $path);
        $pathLevels = count($spiUrlAlias->pathData);

        foreach ($pathElements as $level => $pathElement) {
            if ($level === $pathLevels - 1) {
                $matchedLanguageCode = $this->selectAliasLanguageCode(
                    $spiUrlAlias,
                    $languageCode,
                    $this->languageResolver->getShowAllTranslations(),
                    $this->languageResolver->getPrioritizedLanguages()
                );
            } else {
                $matchedLanguageCode = $this->matchLanguageCode($spiUrlAlias->pathData[$level], $pathElement);
            }

            if ($matchedLanguageCode === false) {
                return [false, false];
            }

            $matchedPathLanguageCodes[] = $matchedLanguageCode;
            $matchedPathElements[] = $spiUrlAlias->pathData[$level]['translations'][$matchedLanguageCode];
        }

        return [implode('/', $matchedPathElements), $matchedPathLanguageCodes];
    }

    /**
     * @param array $pathElementData
     * @param string $pathElement
     *
     * @return string|bool
     */
    protected function matchLanguageCode(array $pathElementData, $pathElement)
    {
        foreach ($this->sortTranslationsByPrioritizedLanguages($pathElementData['translations']) as $translationData) {
            if (strtolower($pathElement) === strtolower($translationData['text'])) {
                return $translationData['lang'];
            }
        }

        return false;
    }

    /**
     * Needed when translations for the part of the alias are the same for multiple languages.
     * In that case we need to ensure that more prioritized language is chosen.
     *
     * @param array $translations
     *
     * @return array
     */
    private function sortTranslationsByPrioritizedLanguages(array $translations)
    {
        $sortedTranslations = [];
        foreach ($this->languageResolver->getPrioritizedLanguages() as $languageCode) {
            if (isset($translations[$languageCode])) {
                $sortedTranslations[] = [
                    'lang' => $languageCode,
                    'text' => $translations[$languageCode],
                ];
                unset($translations[$languageCode]);
            }
        }

        foreach ($translations as $languageCode => $translation) {
            $sortedTranslations[] = [
                'lang' => $languageCode,
                'text' => $translation,
            ];
        }

        return $sortedTranslations;
    }

    /**
     * Returns true or false depending if URL alias is loadable or not for language settings in place.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\UrlAlias $spiUrlAlias
     * @param string|null $languageCode
     * @param bool $showAllTranslations
     * @param string[] $prioritizedLanguageList
     *
     * @return bool
     */
    protected function isUrlAliasLoadable(
        SPIURLAlias $spiUrlAlias,
        ?string $languageCode,
        bool $showAllTranslations,
        array $prioritizedLanguageList
    ) {
        if (isset($languageCode) && !in_array($languageCode, $spiUrlAlias->languageCodes)) {
            return false;
        }

        if ($showAllTranslations) {
            return true;
        }

        foreach ($spiUrlAlias->pathData as $levelPathData) {
            if ($levelPathData['always-available']) {
                continue;
            }

            foreach ($levelPathData['translations'] as $translationLanguageCode => $translation) {
                if (in_array($translationLanguageCode, $prioritizedLanguageList)) {
                    continue 2;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Returns true or false depending if URL alias is loadable or not for language settings in place.
     *
     * @param array $pathData
     * @param array $languageCodes
     *
     * @return bool
     */
    protected function isPathLoadable(array $pathData, array $languageCodes)
    {
        if ($this->languageResolver->getShowAllTranslations()) {
            return true;
        }

        foreach ($pathData as $level => $levelPathData) {
            if ($levelPathData['always-available']) {
                continue;
            }

            if (in_array($languageCodes[$level], $this->languageResolver->getPrioritizedLanguages())) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * List global aliases.
     *
     * @param string $languageCode filters those which are valid for the given language
     * @param int $offset
     * @param int $limit
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias[]
     */
    public function listGlobalAliases(?string $languageCode = null, int $offset = 0, int $limit = -1): iterable
    {
        $urlAliasList = [];
        $spiUrlAliasList = $this->urlAliasHandler->listGlobalURLAliases(
            $languageCode,
            $offset,
            $limit
        );

        foreach ($spiUrlAliasList as $spiUrlAlias) {
            $path = $this->extractPath(
                $spiUrlAlias,
                $languageCode,
                $this->languageResolver->getShowAllTranslations(),
                $this->languageResolver->getPrioritizedLanguages()
            );

            if ($path === false) {
                continue;
            }

            $urlAliasList[] = $this->buildUrlAliasDomainObject($spiUrlAlias, $path);
        }

        return $urlAliasList;
    }

    /**
     * Removes urls aliases.
     *
     * This method does not remove autogenerated aliases for locations.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException if the user is not allowed to remove url alias
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if alias list contains
     *         autogenerated alias
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias[] $aliasList
     */
    public function removeAliases(array $aliasList): void
    {
        if ($this->permissionResolver->hasAccess('content', 'urltranslator') === false) {
            throw new UnauthorizedException('content', 'urltranslator');
        }

        $spiUrlAliasList = [];
        foreach ($aliasList as $alias) {
            if (!$alias->isCustom) {
                throw new InvalidArgumentException(
                    '$aliasList',
                    'The alias list contains an autogenerated alias'
                );
            }
            $spiUrlAliasList[] = $this->buildSPIUrlAlias($alias);
        }

        $this->repository->beginTransaction();
        try {
            $this->urlAliasHandler->removeURLAliases($spiUrlAliasList);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Builds persistence domain object.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias $urlAlias
     *
     * @return \Ibexa\Contracts\Core\Persistence\Content\UrlAlias
     */
    protected function buildSPIUrlAlias(URLAlias $urlAlias)
    {
        return new SPIURLAlias(
            [
                'id' => $urlAlias->id,
                'type' => $urlAlias->type,
                'destination' => $urlAlias->destination,
                'isCustom' => $urlAlias->isCustom,
            ]
        );
    }

    /**
     * looks up the URLAlias for the given url.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException if the path does not exist or is not valid for the given language
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException if the path exceeded maximum depth level
     *
     * @param string $url
     * @param string|null $languageCode
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias
     */
    public function lookup(string $url, ?string $languageCode = null): URLAlias
    {
        $url = $this->cleanUrl($url);

        $spiUrlAlias = $this->urlAliasHandler->lookup($url);

        list($path, $languageCodes) = $this->matchPath($spiUrlAlias, $url, $languageCode);
        if ($path === false || !$this->isPathLoadable($spiUrlAlias->pathData, $languageCodes)) {
            throw new NotFoundException('URLAlias', $url);
        }

        return $this->buildUrlAliasDomainObject($spiUrlAlias, $path);
    }

    /**
     * Returns the URL alias for the given location in the given language.
     *
     * If $languageCode is null the method returns the url alias in the most prioritized language.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException if no url alias exist for the given language
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Location $location
     * @param string|null $languageCode
     * @param bool|null $showAllTranslations
     * @param string[]|null $prioritizedLanguageList
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias
     */
    public function reverseLookup(
        Location $location,
        ?string $languageCode = null,
        ?bool $showAllTranslations = null,
        ?array $prioritizedLanguageList = null
    ): URLAlias {
        if ($showAllTranslations === null) {
            $showAllTranslations = $this->languageResolver->getShowAllTranslations();
        }
        if ($prioritizedLanguageList === null) {
            $prioritizedLanguageList = $this->languageResolver->getPrioritizedLanguages();
        }
        $urlAliases = $this->listLocationAliases(
            $location,
            false,
            $languageCode,
            $showAllTranslations,
            $prioritizedLanguageList
        );

        foreach ($prioritizedLanguageList as $prioritizedLanguageCode) {
            foreach ($urlAliases as $urlAlias) {
                if (in_array($prioritizedLanguageCode, $urlAlias->languageCodes)) {
                    return $urlAlias;
                }
            }
        }

        foreach ($urlAliases as $urlAlias) {
            if ($urlAlias->alwaysAvailable) {
                return $urlAlias;
            }
        }

        if (!empty($urlAliases) && $showAllTranslations) {
            return reset($urlAliases);
        }

        throw new NotFoundException('URLAlias', $location->id);
    }

    /**
     * Loads URL alias by given $id.
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException
     *
     * @param string $id
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias
     */
    public function load(string $id): URLAlias
    {
        $spiUrlAlias = $this->urlAliasHandler->loadUrlAlias($id);
        $path = $this->extractPath(
            $spiUrlAlias,
            null,
            $this->languageResolver->getShowAllTranslations(),
            $this->languageResolver->getPrioritizedLanguages()
        );

        if ($path === false) {
            throw new NotFoundException('URLAlias', $id);
        }

        return $this->buildUrlAliasDomainObject($spiUrlAlias, $path);
    }

    /**
     * Refresh all system URL aliases for the given Location (and historize outdated if needed).
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Location $location
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\ForbiddenException
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException
     * @throws \Exception any unexpected exception that might come from custom Field Type implementation
     */
    public function refreshSystemUrlAliasesForLocation(Location $location): void
    {
        if (!$this->repository->getPermissionResolver()->canUser('content', 'urltranslator', $location)) {
            throw new UnauthorizedException('content', 'urltranslator');
        }

        $this->repository->beginTransaction();
        try {
            $content = $location->getContent();
            $urlAliasNames = $this->nameSchemaService->resolveUrlAliasSchema($content);
            foreach ($urlAliasNames as $languageCode => $name) {
                $this->urlAliasHandler->publishUrlAliasForLocation(
                    $location->id,
                    $location->parentLocationId,
                    $name,
                    $languageCode,
                    $content->contentInfo->alwaysAvailable
                );
            }
            $this->urlAliasHandler->repairBrokenUrlAliasesForLocation($location->id);

            // handle URL aliases for missing Translations
            $this->urlAliasHandler->archiveUrlAliasesForDeletedTranslations(
                $location->id,
                $location->parentLocationId,
                $content->getVersionInfo()->languageCodes
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Delete global, system or custom URL alias pointing to non-existent Locations.
     *
     * @return int Number of removed URL aliases
     *
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException
     * @throws \Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException
     */
    public function deleteCorruptedUrlAliases(): int
    {
        if ($this->repository->getPermissionResolver()->hasAccess('content', 'urltranslator') === false) {
            throw new UnauthorizedException('content', 'urltranslator');
        }

        return $this->urlAliasHandler->deleteCorruptedUrlAliases();
    }

    /**
     * @param string $url
     *
     * @return string
     */
    protected function cleanUrl(string $url): string
    {
        return trim($url, '/ ');
    }

    /**
     * Builds API UrlAlias object from given SPI UrlAlias object.
     *
     * @param \Ibexa\Contracts\Core\Persistence\Content\UrlAlias $spiUrlAlias
     * @param string $path
     *
     * @return \Ibexa\Contracts\Core\Repository\Values\Content\URLAlias
     */
    protected function buildUrlAliasDomainObject(SPIURLAlias $spiUrlAlias, string $path): URLAlias
    {
        return new URLAlias(
            [
                'id' => $spiUrlAlias->id,
                'type' => $spiUrlAlias->type,
                'destination' => $spiUrlAlias->destination,
                'languageCodes' => $spiUrlAlias->languageCodes,
                'alwaysAvailable' => $spiUrlAlias->alwaysAvailable,
                'path' => '/' . $path,
                'isHistory' => $spiUrlAlias->isHistory,
                'isCustom' => $spiUrlAlias->isCustom,
                'forward' => $spiUrlAlias->forward,
            ]
        );
    }
}

class_alias(URLAliasService::class, 'eZ\Publish\Core\Repository\URLAliasService');
