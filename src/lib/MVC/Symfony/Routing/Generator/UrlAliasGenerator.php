<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Core\MVC\Symfony\Routing\Generator;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\Routing\Generator;
use Symfony\Component\Routing\RouterInterface;

/**
 * URL generator for UrlAlias based links.
 *
 * @see \Ibexa\Core\MVC\Symfony\Routing\UrlAliasRouter
 */
class UrlAliasGenerator extends Generator
{
    public const INTERNAL_CONTENT_VIEW_ROUTE = 'ibexa.content.view';

    /** @var \Ibexa\Core\Repository\Repository */
    private $repository;

    /**
     * The default router (that works with declared routes).
     *
     * @var \Symfony\Component\Routing\RouterInterface
     */
    private $defaultRouter;

    /** @var int */
    private $rootLocationId;

    /** @var array */
    private $excludedUriPrefixes = [];

    /** @var array */
    private $pathPrefixMap = [];

    /** @var \Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface */
    private $configResolver;

    /**
     * Array of characters that are potentially unsafe for output for (x)html, json, etc,
     * and respective url-encoded value.
     *
     * @var array
     */
    private $unsafeCharMap;

    public function __construct(Repository $repository, RouterInterface $defaultRouter, ConfigResolverInterface $configResolver, array $unsafeCharMap = [])
    {
        $this->repository = $repository;
        $this->defaultRouter = $defaultRouter;
        $this->configResolver = $configResolver;
        $this->unsafeCharMap = $unsafeCharMap;
    }

    /**
     * Generates the URL from $urlResource and $parameters.
     * Entries in $parameters will be added in the query string.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Location $location
     * @param array $parameters
     *
     * @return string
     */
    public function doGenerate($location, array $parameters)
    {
        $siteAccess = $parameters['siteaccess'] ?? null;

        unset($parameters['language'], $parameters['contentId'], $parameters['siteaccess']);

        $pathString = $this->createPathString($location, $siteAccess);
        $queryString = $this->createQueryString($parameters);
        $url = $pathString . $queryString;

        return $this->filterCharactersOfURL($url);
    }

    /**
     * Injects current root locationId that will be used for link generation.
     *
     * @param int $rootLocationId
     */
    public function setRootLocationId($rootLocationId)
    {
        $this->rootLocationId = $rootLocationId;
    }

    /**
     * @param array $excludedUriPrefixes
     */
    public function setExcludedUriPrefixes(array $excludedUriPrefixes)
    {
        $this->excludedUriPrefixes = $excludedUriPrefixes;
    }

    /**
     * Returns path corresponding to $rootLocationId.
     *
     * @param int $rootLocationId
     * @param array $languages
     * @param string $siteaccess
     *
     * @return string
     */
    public function getPathPrefixByRootLocationId($rootLocationId, $languages = null, $siteaccess = null)
    {
        if (!$rootLocationId) {
            return '';
        }

        if (!isset($this->pathPrefixMap[$siteaccess])) {
            $this->pathPrefixMap[$siteaccess] = [];
        }

        if (!isset($this->pathPrefixMap[$siteaccess][$rootLocationId])) {
            $this->pathPrefixMap[$siteaccess][$rootLocationId] = $this->repository
                ->getURLAliasService()
                ->reverseLookup(
                    $this->loadLocation($rootLocationId),
                    null,
                    false,
                    $languages
                )
                ->path;
        }

        return $this->pathPrefixMap[$siteaccess][$rootLocationId];
    }

    /**
     * Checks if passed URI has an excluded prefix, when a root location is defined.
     *
     * @param string $uri
     *
     * @return bool
     */
    public function isUriPrefixExcluded($uri)
    {
        foreach ($this->excludedUriPrefixes as $excludedPrefix) {
            $excludedPrefix = '/' . trim($excludedPrefix, '/');
            if (mb_stripos($uri, $excludedPrefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Loads a location by its locationId, regardless to user limitations since the router is invoked BEFORE security (no user authenticated yet).
     * Not to be used for link generation.
     *
     * @param int $locationId
     *
     * @return \Ibexa\Core\Repository\Values\Content\Location
     */
    public function loadLocation($locationId)
    {
        return $this->repository->sudo(
            static function (Repository $repository) use ($locationId) {
                /* @var $repository \Ibexa\Core\Repository\Repository */
                return $repository->getLocationService()->loadLocation($locationId);
            }
        );
    }

    /**
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Location $location
     * @param string|null $siteAccess
     *
     * @return string
     */
    private function createPathString(Location $location, ?string $siteAccess = null): string
    {
        $urlAliasService = $this->repository->getURLAliasService();

        if ($siteAccess) {
            // We generate for a different SiteAccess, so potentially in a different language.
            $languages = $this->configResolver->getParameter('languages', null, $siteAccess);
            $urlAliases = $urlAliasService->listLocationAliases($location, false, null, null, $languages);
            // Use the target SiteAccess root location
            $rootLocationId = $this->configResolver->getParameter('content.tree_root.location_id', null, $siteAccess);
        } else {
            $languages = null;
            $urlAliases = $urlAliasService->listLocationAliases($location, false);
            $rootLocationId = $this->rootLocationId;
        }

        if (!empty($urlAliases)) {
            $path = $urlAliases[0]->path;
            // Remove rootLocation's prefix if needed.
            if ($rootLocationId !== null) {
                $pathPrefix = $this->getPathPrefixByRootLocationId($rootLocationId, $languages, $siteAccess);
                // "/" cannot be considered as a path prefix since it's root, so we ignore it.
                if ($pathPrefix !== '/' && ($path === $pathPrefix || mb_stripos($path, $pathPrefix . '/') === 0)) {
                    $path = mb_substr($path, mb_strlen($pathPrefix));
                } elseif ($pathPrefix !== '/' && !$this->isUriPrefixExcluded($path) && $this->logger !== null) {
                    // Location path is outside configured content tree and doesn't have an excluded prefix.
                    // This is most likely an error (from content edition or link generation logic).
                    $this->logger->warning("Generating a link to a location outside root content tree: '$path' is outside tree starting to location #$rootLocationId");
                }
            }
        } else {
            $path = $this->defaultRouter->generate(
                self::INTERNAL_CONTENT_VIEW_ROUTE,
                ['contentId' => $location->contentId, 'locationId' => $location->id]
            );
        }

        return $path ?: '/';
    }

    /**
     * Creates query string from parameters. If `_fragment` parameter is provided then
     * fragment identifier is added at the end of the URL.
     *
     * @param array $parameters
     *
     * @return string
     */
    private function createQueryString(array $parameters): string
    {
        $queryString = '';
        $fragment = null;
        if (isset($parameters['_fragment'])) {
            $fragment = $parameters['_fragment'];
            unset($parameters['_fragment']);
        }

        if (!empty($parameters)) {
            $queryString = '?' . http_build_query($parameters, '', '&');
        }

        if ($fragment) {
            // logic aligned with Symfony 3.4: \Symfony\Component\Routing\Generator\UrlGenerator::doGenerate
            $queryString .= '#' . strtr(rawurlencode($fragment), ['%2F' => '/', '%3F' => '?']);
        }

        return $queryString;
    }

    /**
     * Replace potentially unsafe characters with url-encoded counterpart.
     *
     * @param string $url
     *
     * @return string
     */
    private function filterCharactersOfURL(string $url): string
    {
        return strtr($url, $this->unsafeCharMap);
    }
}

class_alias(UrlAliasGenerator::class, 'eZ\Publish\Core\MVC\Symfony\Routing\Generator\UrlAliasGenerator');
