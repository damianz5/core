<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Core\Repository\Values\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Core\Repository\Values\Content\Content;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\Core\Repository\Values\Content\Content
 */
class ContentTest extends TestCase
{
    public function testObjectProperties()
    {
        $object = new Content(['internalFields' => []]);
        $properties = $object->attributes();
        self::assertNotContains('internalFields', $properties, 'Internal property found ');
        self::assertContains('id', $properties, 'Property not found ');
        self::assertContains('fields', $properties, 'Property not found ');
        self::assertContains('versionInfo', $properties, 'Property not found ');
        self::assertContains('contentInfo', $properties, 'Property not found ');

        // check for duplicates and double check existence of property
        $propertiesHash = [];
        foreach ($properties as $property) {
            if (isset($propertiesHash[$property])) {
                self::fail("Property '{$property}' exists several times in properties list");
            } elseif (!isset($object->$property)) {
                self::fail("Property '{$property}' does not exist on object, even though it was hinted to be there");
            }
            $propertiesHash[$property] = 1;
        }
    }

    public function testGetName()
    {
        $name = 'Translated name';
        $versionInfoMock = $this->createMock(VersionInfo::class);
        $versionInfoMock->expects($this->once())
            ->method('getName')
            ->willReturn($name);

        $object = new Content(['versionInfo' => $versionInfoMock]);

        $this->assertEquals($name, $object->getName());
    }
}

class_alias(ContentTest::class, 'eZ\Publish\Core\Repository\Tests\Values\Content\ContentTest');
