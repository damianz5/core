<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Bundle\Core\DependencyInjection\Compiler;

use Ibexa\Bundle\Core\DependencyInjection\Compiler\PlaceholderProviderPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class PlaceholderProviderPassTest extends AbstractCompilerPassTestCase
{
    public const PROVIDER_ID = 'provider.id';
    public const PROVIDER_TYPE = 'provider.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(PlaceholderProviderPass::REGISTRY_DEFINITION_ID, new Definition());
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PlaceholderProviderPass());
    }

    public function testAddProvider()
    {
        $definition = new Definition();
        $definition->addTag(PlaceholderProviderPass::TAG_NAME, ['type' => self::PROVIDER_TYPE]);

        $this->setDefinition(self::PROVIDER_ID, $definition);
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            PlaceholderProviderPass::REGISTRY_DEFINITION_ID,
            'addProvider',
            [self::PROVIDER_TYPE, new Reference(self::PROVIDER_ID)]
        );
    }

    public function testAddProviderWithoutType()
    {
        $this->expectException(\LogicException::class);

        $definition = new Definition();
        $definition->addTag(PlaceholderProviderPass::TAG_NAME);

        $this->setDefinition(self::PROVIDER_ID, $definition);
        $this->compile();
    }
}

class_alias(PlaceholderProviderPassTest::class, 'eZ\Bundle\EzPublishCoreBundle\Tests\DependencyInjection\Compiler\PlaceholderProviderPassTest');
