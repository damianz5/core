<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Bundle\Core\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Compiler\QueryTypePass;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\Parser\Common;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\Parser\Content;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\Parser\Repository\FieldGroups;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\Parser\Repository\Options;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\Parser\Repository\Search;
use Ibexa\Bundle\Core\DependencyInjection\Configuration\Parser\Repository\Storage;
use Ibexa\Bundle\Core\DependencyInjection\IbexaCoreExtension;
use Ibexa\Bundle\Core\DependencyInjection\ServiceTags;
use Ibexa\Contracts\Core\Repository\Values\Filter;
use Ibexa\Core\MVC\Symfony\Routing\ChainRouter;
use Ibexa\Tests\Bundle\Core\DependencyInjection\Stub\Filter\CustomCriterionQueryBuilder;
use Ibexa\Tests\Bundle\Core\DependencyInjection\Stub\Filter\CustomSortClauseQueryBuilder;
use Ibexa\Tests\Bundle\Core\DependencyInjection\Stub\QueryTypeBundle\QueryType\TestQueryType;
use Ibexa\Tests\Bundle\Core\DependencyInjection\Stub\StubPolicyProvider;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CheckExceptionOnInvalidReferenceBehaviorPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

class IbexaCoreExtensionTest extends AbstractExtensionTestCase
{
    private $minimalConfig = [];

    private $siteaccessConfig = [];

    /** @var \Ibexa\Bundle\Core\DependencyInjection\IbexaCoreExtension */
    private $extension;

    protected function setUp(): void
    {
        parent::setUp();

        $loader = new YamlFileLoader(
            $this->container,
            new FileLocator(__DIR__ . '/Fixtures')
        );

        $loader->load('parameters.yml');

        $this->siteaccessConfig = [
            'siteaccess' => [
                'default_siteaccess' => 'ibexa_demo_site',
                'list' => ['ibexa_demo_site', 'eng', 'fre', 'ibexa_demo_site_admin'],
                'groups' => [
                    'ibexa_demo_group' => ['ibexa_demo_site', 'eng', 'fre', 'ibexa_demo_site_admin'],
                    'ibexa_demo_frontend_group' => ['ibexa_demo_site', 'eng', 'fre'],
                    'empty_group' => [],
                ],
                'match' => [
                    'URILElement' => 1,
                    'Map\URI' => ['the_front' => 'ibexa_demo_site', 'the_back' => 'ibexa_demo_site_admin'],
                ],
            ],
            'system' => [
                'ibexa_demo_site' => [],
                'eng' => [],
                'fre' => [],
                'ibexa_demo_site_admin' => [],
                'empty_group' => ['var_dir' => 'foo'],
            ],
        ];

        $_ENV['HTTPCACHE_PURGE_TYPE'] = $_SERVER['HTTPCACHE_PURGE_TYPE'] = 'http';
    }

    protected function getContainerExtensions(): array
    {
        return [IbexaCoreExtension::class => $this->getCoreExtension()];
    }

    protected function getMinimalConfiguration(): array
    {
        return $this->minimalConfig = Yaml::parse(file_get_contents(__DIR__ . '/Fixtures/ezpublish_minimal_no_siteaccess.yml'));
    }

    public function testSiteAccessConfiguration()
    {
        $this->load($this->siteaccessConfig);
        $this->assertContainerBuilderHasParameter(
            'ibexa.site_access.list',
            $this->siteaccessConfig['siteaccess']['list']
        );
        $this->assertContainerBuilderHasParameter(
            'ibexa.site_access.default',
            $this->siteaccessConfig['siteaccess']['default_siteaccess']
        );
        $this->assertContainerBuilderHasParameter('ibexa.site_access.groups', $this->siteaccessConfig['siteaccess']['groups']);

        $expectedMatchingConfig = [];
        foreach ($this->siteaccessConfig['siteaccess']['match'] as $key => $val) {
            // Value is expected to always be an array (transformed by semantic configuration parser).
            $expectedMatchingConfig[$key] = is_array($val) ? $val : ['value' => $val];
        }
        $this->assertContainerBuilderHasParameter('ibexa.site_access.match_config', $expectedMatchingConfig);
        $this->assertContainerBuilderHasParameter('ibexa.site_access.config.empty_group.var_dir', 'foo');

        $groupsBySiteaccess = [];
        foreach ($this->siteaccessConfig['siteaccess']['groups'] as $groupName => $groupMembers) {
            foreach ($groupMembers as $member) {
                if (!isset($groupsBySiteaccess[$member])) {
                    $groupsBySiteaccess[$member] = [];
                }

                $groupsBySiteaccess[$member][] = $groupName;
            }
        }
    }

    public function testSiteAccessNoConfiguration()
    {
        $this->load();
        $this->assertContainerBuilderHasParameter('ibexa.site_access.list', ['setup']);
        $this->assertContainerBuilderHasParameter('ibexa.site_access.default', 'setup');
        $this->assertContainerBuilderHasParameter('ibexa.site_access.groups', []);
        $this->assertContainerBuilderHasParameter('ibexa.site_access.groups_by_site_access', []);
        $this->assertContainerBuilderHasParameter('ibexa.site_access.match_config', null);
    }

    public function testImageMagickConfigurationBasic()
    {
        if (!isset($_ENV['imagemagickConvertPath']) || !is_executable($_ENV['imagemagickConvertPath'])) {
            $this->markTestSkipped('Missing or mis-configured Imagemagick convert path.');
        }

        $this->load(
            [
                'imagemagick' => [
                    'enabled' => true,
                    'path' => $_ENV['imagemagickConvertPath'],
                ],
            ]
        );
        $this->assertContainerBuilderHasParameter('ibexa.image.imagemagick.enabled', true);
        $this->assertContainerBuilderHasParameter('ibexa.image.imagemagick.executable_path', dirname($_ENV['imagemagickConvertPath']));
        $this->assertContainerBuilderHasParameter('ibexa.image.imagemagick.executable', basename($_ENV['imagemagickConvertPath']));
    }

    /**
     * @dataProvider translationsConfigurationProvider
     */
    public function testUITranslationsConfiguration(
        bool $enabled,
        bool $expectedParameterValue
    ): void {
        if (is_bool($enabled)) {
            $this->load(
                [
                    'ui' => [
                        'translations' => [
                            'enabled' => $enabled,
                        ],
                    ],
                ]
            );
        }

        $this->assertContainerBuilderHasParameter('ibexa.ui.translations.enabled', $expectedParameterValue);
    }

    /**
     * @return iterable<string,array{bool,array{string}}>
     */
    public function translationsConfigurationProvider(): iterable
    {
        yield 'translations enabled' => [
            true,
            true,
        ];

        yield 'translations disabled' => [
            false,
            false,
        ];
    }

    public function testImageMagickConfigurationFilters()
    {
        if (!isset($_ENV['imagemagickConvertPath']) || !is_executable($_ENV['imagemagickConvertPath'])) {
            $this->markTestSkipped('Missing or mis-configured Imagemagick convert path.');
        }

        $customFilters = [
            'foobar' => '-foobar',
            'wow' => '-amazing',
        ];
        $this->load(
            [
                'imagemagick' => [
                    'enabled' => true,
                    'path' => $_ENV['imagemagickConvertPath'],
                    'filters' => $customFilters,
                ],
            ]
        );
        $this->assertTrue($this->container->hasParameter('ibexa.image.imagemagick.filters'));
        $filters = $this->container->getParameter('ibexa.image.imagemagick.filters');
        $this->assertArrayHasKey('foobar', $filters);
        $this->assertSame($customFilters['foobar'], $filters['foobar']);
        $this->assertArrayHasKey('wow', $filters);
        $this->assertSame($customFilters['wow'], $filters['wow']);
    }

    public function testImagePlaceholderConfiguration()
    {
        $this->load([
            'image_placeholder' => [
                'default' => [
                    'provider' => 'generic',
                    'options' => [
                        'foo' => 'Foo',
                        'bar' => 'Bar',
                    ],
                    'verify_binary_data_availability' => true,
                ],
                'fancy' => [
                    'provider' => 'remote',
                ],
            ],
        ]);

        $this->assertEquals([
            'default' => [
                'provider' => 'generic',
                'options' => [
                    'foo' => 'Foo',
                    'bar' => 'Bar',
                ],
                'verify_binary_data_availability' => true,
            ],
            'fancy' => [
                'provider' => 'remote',
                'options' => [],
                'verify_binary_data_availability' => false,
            ],
        ], $this->container->getParameter('ibexa.io.images.alias.placeholder_provider'));
    }

    public function testRoutingConfiguration()
    {
        $this->load();
        $this->assertContainerBuilderHasAlias('router', ChainRouter::class);

        $this->assertTrue($this->container->hasParameter('ibexa.default_router.non_site_access_aware_routes'));
        $nonSiteaccessAwareRoutes = $this->container->getParameter('ibexa.default_router.non_site_access_aware_routes');
        // See ezpublish_minimal_no_siteaccess.yml fixture
        $this->assertContains('foo_route', $nonSiteaccessAwareRoutes);
        $this->assertContains('my_prefix_', $nonSiteaccessAwareRoutes);
    }

    /**
     * @dataProvider cacheConfigurationProvider
     *
     * @param array $customCacheConfig
     * @param string $expectedPurgeType
     */
    public function testCacheConfiguration(array $customCacheConfig, $expectedPurgeType)
    {
        $this->load($customCacheConfig);

        $this->assertContainerBuilderHasParameter('ibexa.http_cache.purge_type', $expectedPurgeType);
    }

    public function cacheConfigurationProvider()
    {
        return [
            [[], 'local'],
            [
                [
                    'http_cache' => ['purge_type' => 'local'],
                ],
                'local',
            ],
            [
                [
                    'http_cache' => ['purge_type' => 'multiple_http'],
                ],
                'http',
            ],
            [
                [
                    'http_cache' => ['purge_type' => 'single_http'],
                ],
                'http',
            ],
            [
                [
                    'http_cache' => ['purge_type' => 'http'],
                ],
                'http',
            ],
            [
                [
                    'http_cache' => ['purge_type' => '%env(HTTPCACHE_PURGE_TYPE)%'],
                ],
                'http',
            ],
        ];
    }

    public function testCacheConfigurationCustomPurgeService()
    {
        $serviceId = 'foobar';
        $this->setDefinition($serviceId, new Definition());
        $this->load(
            [
                'http_cache' => ['purge_type' => 'foobar', 'timeout' => 12],
            ]
        );

        $this->assertContainerBuilderHasParameter('ibexa.http_cache.purge_type', 'foobar');
    }

    public function testLocaleConfiguration()
    {
        $this->load(['locale_conversion' => ['foo' => 'bar']]);
        $conversionMap = $this->container->getParameter('ibexa.locale.conversion_map');
        $this->assertArrayHasKey('foo', $conversionMap);
        $this->assertSame('bar', $conversionMap['foo']);
    }

    public function testRepositoriesConfiguration()
    {
        $repositories = [
            'main' => [
                'storage' => [
                    'engine' => 'legacy',
                    'connection' => 'default',
                ],
                'search' => [
                    'engine' => 'legacy',
                    'connection' => 'blabla',
                ],
                'fields_groups' => [
                    'list' => ['content', 'metadata'],
                    'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                ],
                'options' => [
                    'default_version_archive_limit' => 5,
                    'remove_archived_versions_on_publish' => true,
                ],
            ],
            'foo' => [
                'storage' => [
                    'engine' => 'sqlng',
                    'connection' => 'default',
                ],
                'search' => [
                    'engine' => 'solr',
                    'connection' => 'lalala',
                ],
                'fields_groups' => [
                    'list' => ['content', 'metadata'],
                    'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                ],
                'options' => [
                    'default_version_archive_limit' => 5,
                    'remove_archived_versions_on_publish' => true,
                ],
            ],
        ];
        $this->load(['repositories' => $repositories]);
        $this->assertTrue($this->container->hasParameter('ibexa.repositories'));

        foreach ($repositories as &$repositoryConfig) {
            $repositoryConfig['storage']['config'] = [];
            $repositoryConfig['search']['config'] = [];
        }
        $this->assertSame($repositories, $this->container->getParameter('ibexa.repositories'));
    }

    /**
     * @dataProvider repositoriesConfigurationFieldGroupsProvider
     */
    public function testRepositoriesConfigurationFieldGroups($repositories, $expectedRepositories)
    {
        $this->load(['repositories' => $repositories]);
        $this->assertTrue($this->container->hasParameter('ibexa.repositories'));

        $repositoriesPar = $this->container->getParameter('ibexa.repositories');
        $this->assertEquals(count($repositories), count($repositoriesPar));

        foreach ($repositoriesPar as $key => $repo) {
            $this->assertArrayHasKey($key, $expectedRepositories);
            $this->assertArrayHasKey('fields_groups', $repo);
            $this->assertEqualsCanonicalizing($expectedRepositories[$key]['fields_groups'], $repo['fields_groups'], 'Invalid fields groups element');
        }
    }

    public function repositoriesConfigurationFieldGroupsProvider()
    {
        return [
            //empty config
            [
                ['main' => null],
                ['main' => [
                        'fields_groups' => [
                            'list' => ['content', 'metadata'],
                            'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                        ],
                    ],
                ],
            ],
            //single item with custom fields
            [
                ['foo' => [
                        'fields_groups' => [
                            'list' => ['bar', 'baz', 'john'],
                            'default' => 'bar',
                        ],
                    ],
                ],
                ['foo' => [
                        'fields_groups' => [
                            'list' => ['bar', 'baz', 'john'],
                            'default' => 'bar',
                        ],
                    ],
                ],
            ],
            //mixed item with custom config and empty item
            [
                [
                    'foo' => [
                        'fields_groups' => [
                            'list' => ['bar', 'baz', 'john', 'doe'],
                            'default' => 'bar',
                        ],
                    ],
                    'anotherone' => null,
                ],
                [
                    'foo' => [
                        'fields_groups' => [
                            'list' => ['bar', 'baz', 'john', 'doe'],
                            'default' => 'bar',
                        ],
                    ],
                    'anotherone' => [
                        'fields_groups' => [
                            'list' => ['content', 'metadata'],
                            'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                        ],
                    ],
                ],
            ],
            //items with only one field configured
            [
                [
                    'foo' => [
                        'fields_groups' => [
                            'list' => ['bar', 'baz', 'john'],
                        ],
                    ],
                    'bar' => [
                        'fields_groups' => [
                            'default' => 'metadata',
                        ],
                    ],
                ],
                [
                    'foo' => [
                        'fields_groups' => [
                            'list' => ['bar', 'baz', 'john'],
                            'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                        ],
                    ],
                    'bar' => [
                        'fields_groups' => [
                            'list' => ['content', 'metadata'],
                            'default' => 'metadata',
                        ],
                    ],
                ],
            ],
            //two different repositories
            [
                [
                    'foo' => [
                        'fields_groups' => [
                            'list' => ['bar', 'baz', 'john', 'doe'],
                            'default' => 'bar',
                        ],
                    ],
                    'bar' => [
                        'fields_groups' => [
                            'list' => ['lorem', 'ipsum'],
                            'default' => 'lorem',
                        ],
                    ],
                ],
                [
                    'foo' => [
                        'fields_groups' => [
                            'list' => ['bar', 'baz', 'john', 'doe'],
                            'default' => 'bar',
                        ],
                    ],
                    'bar' => [
                        'fields_groups' => [
                            'list' => ['lorem', 'ipsum'],
                            'default' => 'lorem',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testRepositoriesConfigurationEmpty()
    {
        $repositories = [
            'main' => null,
        ];
        $expectedRepositories = [
            'main' => [
                'storage' => [
                    'engine' => '%ibexa.api.storage_engine.default%',
                    'connection' => null,
                    'config' => [],
                ],
                'search' => [
                    'engine' => '%ibexa.api.search_engine.default%',
                    'connection' => null,
                    'config' => [],
                ],
                'fields_groups' => [
                    'list' => ['content', 'metadata'],
                    'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                ],
                'options' => [
                    'default_version_archive_limit' => 5,
                    'remove_archived_versions_on_publish' => true,
                ],
            ],
        ];
        $this->load(['repositories' => $repositories]);
        $this->assertTrue($this->container->hasParameter('ibexa.repositories'));

        $this->assertSame(
            $expectedRepositories,
            $this->container->getParameter('ibexa.repositories')
        );
    }

    public function testRepositoriesConfigurationStorageEmpty()
    {
        $repositories = [
            'main' => [
                'search' => [
                    'engine' => 'fantasticfind',
                    'connection' => 'french',
                ],
            ],
        ];
        $expectedRepositories = [
            'main' => [
                'search' => [
                    'engine' => 'fantasticfind',
                    'connection' => 'french',
                    'config' => [],
                ],
                'storage' => [
                    'engine' => '%ibexa.api.storage_engine.default%',
                    'connection' => null,
                    'config' => [],
                ],
                'fields_groups' => [
                    'list' => ['content', 'metadata'],
                    'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                ],
                'options' => [
                    'default_version_archive_limit' => 5,
                    'remove_archived_versions_on_publish' => true,
                ],
            ],
        ];
        $this->load(['repositories' => $repositories]);
        $this->assertTrue($this->container->hasParameter('ibexa.repositories'));

        $this->assertSame(
            $expectedRepositories,
            $this->container->getParameter('ibexa.repositories')
        );
    }

    public function testRepositoriesConfigurationSearchEmpty()
    {
        $repositories = [
            'main' => [
                'storage' => [
                    'engine' => 'persistentprudence',
                    'connection' => 'yes',
                ],
            ],
        ];
        $expectedRepositories = [
            'main' => [
                'storage' => [
                    'engine' => 'persistentprudence',
                    'connection' => 'yes',
                    'config' => [],
                ],
                'search' => [
                    'engine' => '%ibexa.api.search_engine.default%',
                    'connection' => null,
                    'config' => [],
                ],
                'fields_groups' => [
                    'list' => ['content', 'metadata'],
                    'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                ],
                'options' => [
                    'default_version_archive_limit' => 5,
                    'remove_archived_versions_on_publish' => true,
                ],
            ],
        ];
        $this->load(['repositories' => $repositories]);
        $this->assertTrue($this->container->hasParameter('ibexa.repositories'));

        $this->assertSame(
            $expectedRepositories,
            $this->container->getParameter('ibexa.repositories')
        );
    }

    public function testRepositoriesConfigurationCompatibility()
    {
        $repositories = [
            'main' => [
                'engine' => 'legacy',
                'connection' => 'default',
                'search' => [
                    'engine' => 'legacy',
                    'connection' => 'blabla',
                ],
            ],
            'foo' => [
                'engine' => 'sqlng',
                'connection' => 'default',
                'search' => [
                    'engine' => 'solr',
                    'connection' => 'lalala',
                ],
            ],
        ];
        $expectedRepositories = [
            'main' => [
                'search' => [
                    'engine' => 'legacy',
                    'connection' => 'blabla',
                    'config' => [],
                ],
                'storage' => [
                    'engine' => 'legacy',
                    'connection' => 'default',
                    'config' => [],
                ],
                'fields_groups' => [
                    'list' => ['content', 'metadata'],
                    'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                ],
                'options' => [
                    'default_version_archive_limit' => 5,
                    'remove_archived_versions_on_publish' => true,
                ],
            ],
            'foo' => [
                'search' => [
                    'engine' => 'solr',
                    'connection' => 'lalala',
                    'config' => [],
                ],
                'storage' => [
                    'engine' => 'sqlng',
                    'connection' => 'default',
                    'config' => [],
                ],
                'fields_groups' => [
                    'list' => ['content', 'metadata'],
                    'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                ],
                'options' => [
                    'default_version_archive_limit' => 5,
                    'remove_archived_versions_on_publish' => true,
                ],
            ],
        ];
        $this->load(['repositories' => $repositories]);
        $this->assertTrue($this->container->hasParameter('ibexa.repositories'));

        $this->assertSame(
            $expectedRepositories,
            $this->container->getParameter('ibexa.repositories')
        );
    }

    public function testRepositoriesConfigurationCompatibility2()
    {
        $repositories = [
            'main' => [
                'engine' => 'legacy',
                'connection' => 'default',
            ],
        ];
        $expectedRepositories = [
            'main' => [
                'storage' => [
                    'engine' => 'legacy',
                    'connection' => 'default',
                    'config' => [],
                ],
                'search' => [
                    'engine' => '%ibexa.api.search_engine.default%',
                    'connection' => null,
                    'config' => [],
                ],
                'fields_groups' => [
                    'list' => ['content', 'metadata'],
                    'default' => '%ibexa.site_access.config.default.content.field_groups.default%',
                ],
                'options' => [
                    'default_version_archive_limit' => 5,
                    'remove_archived_versions_on_publish' => true,
                ],
            ],
        ];
        $this->load(['repositories' => $repositories]);
        $this->assertTrue($this->container->hasParameter('ibexa.repositories'));

        $this->assertSame(
            $expectedRepositories,
            $this->container->getParameter('ibexa.repositories')
        );
    }

    public function testRegisteredPolicies()
    {
        $this->load();
        $this->assertContainerBuilderHasParameter('ibexa.api.role.policy_map');
        $previousPolicyMap = $this->container->getParameter('ibexa.api.role.policy_map');

        $policies1 = [
            'custom_module' => [
                'custom_function_1' => null,
                'custom_function_2' => ['CustomLimitation'],
            ],
            'helloworld' => [
                'foo' => ['bar'],
                'baz' => null,
            ],
        ];
        $this->extension->addPolicyProvider(new StubPolicyProvider($policies1));

        $policies2 = [
            'custom_module2' => [
                'custom_function_3' => null,
                'custom_function_4' => ['CustomLimitation2', 'CustomLimitation3'],
            ],
            'helloworld' => [
                'foo' => ['additional_limitation'],
                'some' => ['thingy', 'thing', 'but', 'wait'],
            ],
        ];
        $this->extension->addPolicyProvider(new StubPolicyProvider($policies2));

        $expectedPolicies = [
            'custom_module' => [
                'custom_function_1' => [],
                'custom_function_2' => ['CustomLimitation' => true],
            ],
            'helloworld' => [
                'foo' => ['bar' => true, 'additional_limitation' => true],
                'baz' => [],
                'some' => ['thingy' => true, 'thing' => true, 'but' => true, 'wait' => true],
            ],
            'custom_module2' => [
                'custom_function_3' => [],
                'custom_function_4' => ['CustomLimitation2' => true, 'CustomLimitation3' => true],
            ],
        ];

        $this->load();
        $this->assertContainerBuilderHasParameter('ibexa.api.role.policy_map');
        $expectedPolicies = array_merge_recursive($expectedPolicies, $previousPolicyMap);
        self::assertEquals($expectedPolicies, $this->container->getParameter('ibexa.api.role.policy_map'));
    }

    public function testUrlAliasConfiguration()
    {
        $configuration = [
            'transformation' => 'urlalias_lowercase',
            'separator' => 'dash',
            'transformation_groups' => [
                'urlalias' => [
                    'commands' => [
                        'ascii_lowercase',
                        'cyrillic_lowercase',
                    ],
                    'cleanup_method' => 'url_cleanup',
                ],
                'urlalias_compact' => [
                    'commands' => [
                        'greek_normalize',
                        'exta_lowercase',
                    ],
                    'cleanup_method' => 'compact_cleanup',
                ],
            ],
        ];
        $this->load([
            'url_alias' => [
                'slug_converter' => $configuration,
            ],
        ]);
        $parsedConfig = $this->container->getParameter('ibexa.url_alias.slug_converter');
        $this->assertSame(
            $configuration,
            $parsedConfig
        );
    }

    /**
     * Test automatic configuration of services implementing QueryType interface.
     *
     * @see \Ibexa\Core\QueryType\QueryType
     */
    public function testQueryTypeAutomaticConfiguration(): void
    {
        $definition = new Definition(TestQueryType::class);
        $definition->setAutoconfigured(true);
        $this->setDefinition(TestQueryType::class, $definition);

        $this->load();

        $this->compileCoreContainer();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            TestQueryType::class,
            QueryTypePass::QUERY_TYPE_SERVICE_TAG
        );
    }

    /**
     * Test automatic configuration of services implementing Criterion & SortClause Filtering Query
     * Builders.
     *
     * @dataProvider getFilteringQueryBuilderData
     *
     * @see \Ibexa\Contracts\Core\Repository\Values\Filter\CriterionQueryBuilder
     * @see \Ibexa\Contracts\Core\Repository\Values\Filter\SortClauseQueryBuilder
     */
    public function testFilteringQueryBuildersAutomaticConfiguration(
        string $classFQCN,
        string $tagName
    ): void {
        $definition = new Definition($classFQCN);
        $definition->setAutoconfigured(true);
        $this->setDefinition($classFQCN, $definition);

        $this->load();

        $this->compileCoreContainer();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(
            $classFQCN,
            $tagName
        );
    }

    /**
     * Data provider for {@see testFilteringQueryBuildersAutomaticConfiguration}.
     */
    public function getFilteringQueryBuilderData(): iterable
    {
        yield Filter\CriterionQueryBuilder::class => [
            CustomCriterionQueryBuilder::class,
            ServiceTags::FILTERING_CRITERION_QUERY_BUILDER,
        ];

        yield Filter\SortClauseQueryBuilder::class => [
            CustomSortClauseQueryBuilder::class,
            ServiceTags::FILTERING_SORT_CLAUSE_QUERY_BUILDER,
        ];
    }

    /**
     * Prepare Core Container for compilation by mocking required parameters and compile it.
     */
    private function compileCoreContainer(): void
    {
        $this->disableCheckExceptionOnInvalidReferenceBehaviorPass();
        $this->container->setParameter('webroot_dir', __DIR__);
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->setParameter('kernel.cache_dir', __DIR__ . '/cache');
        $this->container->setParameter('kernel.debug', false);
        $this->compile();
    }

    final public function disableCheckExceptionOnInvalidReferenceBehaviorPass(): void
    {
        $compilerPassConfig = $this->container->getCompilerPassConfig();
        $compilerPassConfig->setAfterRemovingPasses(
            array_filter(
                $compilerPassConfig->getAfterRemovingPasses(),
                static function (CompilerPassInterface $pass) {
                    return !($pass instanceof CheckExceptionOnInvalidReferenceBehaviorPass);
                }
            )
        );
    }

    protected function getCoreExtension(): IbexaCoreExtension
    {
        if (null !== $this->extension) {
            return $this->extension;
        }

        $this->extension = new IbexaCoreExtension(
            [
                new Common(),
                new Content(),
            ],
            [
                new Storage(),
                new Search(),
                new FieldGroups(),
                new Options(),
            ],
        );

        return $this->extension;
    }
}

class_alias(IbexaCoreExtensionTest::class, 'eZ\Bundle\EzPublishCoreBundle\Tests\DependencyInjection\EzPublishCoreExtensionTest');
