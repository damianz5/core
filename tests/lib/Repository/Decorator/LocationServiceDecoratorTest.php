<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Core\Repository\Decorator;

use Ibexa\Contracts\Core\Repository\Decorator\LocationServiceDecorator;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationUpdateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LocationServiceDecoratorTest extends TestCase
{
    private const EXAMPLE_LOCATION_ID = 54;
    private const EXAMPLE_OFFSET = 10;
    private const EXAMPLE_LIMIT = 100;

    protected function createDecorator(MockObject $service): LocationService
    {
        return new class($service) extends LocationServiceDecorator {
        };
    }

    protected function createServiceMock(): MockObject
    {
        return $this->createMock(LocationService::class);
    }

    public function testCopySubtreeDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            $this->createMock(Location::class),
            $this->createMock(Location::class),
        ];

        $serviceMock->expects($this->once())->method('copySubtree')->with(...$parameters);

        $decoratedService->copySubtree(...$parameters);
    }

    public function testLoadLocationDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            self::EXAMPLE_LOCATION_ID,
            ['random_value_5ced05ce160308.46670993'],
            true,
        ];

        $serviceMock->expects($this->once())->method('loadLocation')->with(...$parameters);

        $decoratedService->loadLocation(...$parameters);
    }

    public function testLoadLocationListDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            ['random_value_5ced05ce160353.35020609'],
            ['random_value_5ced05ce160364.09322984'],
            true,
        ];

        $serviceMock->expects($this->once())->method('loadLocationList')->with(...$parameters);

        $decoratedService->loadLocationList(...$parameters);
    }

    public function testLoadLocationByRemoteIdDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            'random_value_5ced05ce160397.21653541',
            ['random_value_5ced05ce1603a3.59834231'],
            true,
        ];

        $serviceMock->expects($this->once())->method('loadLocationByRemoteId')->with(...$parameters);

        $decoratedService->loadLocationByRemoteId(...$parameters);
    }

    public function testLoadLocationsDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            $this->createMock(ContentInfo::class),
            $this->createMock(Location::class),
            ['random_value_5ced05ce1603f9.50138109'],
        ];

        $serviceMock->expects($this->once())->method('loadLocations')->with(...$parameters);

        $decoratedService->loadLocations(...$parameters);
    }

    public function testLoadLocationChildrenDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            $this->createMock(Location::class),
            self::EXAMPLE_OFFSET,
            self::EXAMPLE_LIMIT,
            ['random_value_5ced05ce160459.73858583'],
        ];

        $serviceMock->expects($this->once())->method('loadLocationChildren')->with(...$parameters);

        $decoratedService->loadLocationChildren(...$parameters);
    }

    public function testLoadParentLocationsForDraftContentDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            $this->createMock(VersionInfo::class),
            ['random_value_5ced05ce160494.77580729'],
        ];

        $serviceMock->expects($this->once())->method('loadParentLocationsForDraftContent')->with(...$parameters);

        $decoratedService->loadParentLocationsForDraftContent(...$parameters);
    }

    public function testGetLocationChildCountDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [$this->createMock(Location::class)];

        $serviceMock->expects($this->once())->method('getLocationChildCount')->with(...$parameters);

        $decoratedService->getLocationChildCount(...$parameters);
    }

    public function testCreateLocationDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            $this->createMock(ContentInfo::class),
            $this->createMock(LocationCreateStruct::class),
        ];

        $serviceMock->expects($this->once())->method('createLocation')->with(...$parameters);

        $decoratedService->createLocation(...$parameters);
    }

    public function testUpdateLocationDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            $this->createMock(Location::class),
            $this->createMock(LocationUpdateStruct::class),
        ];

        $serviceMock->expects($this->once())->method('updateLocation')->with(...$parameters);

        $decoratedService->updateLocation(...$parameters);
    }

    public function testSwapLocationDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            $this->createMock(Location::class),
            $this->createMock(Location::class),
        ];

        $serviceMock->expects($this->once())->method('swapLocation')->with(...$parameters);

        $decoratedService->swapLocation(...$parameters);
    }

    public function testHideLocationDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [$this->createMock(Location::class)];

        $serviceMock->expects($this->once())->method('hideLocation')->with(...$parameters);

        $decoratedService->hideLocation(...$parameters);
    }

    public function testUnhideLocationDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [$this->createMock(Location::class)];

        $serviceMock->expects($this->once())->method('unhideLocation')->with(...$parameters);

        $decoratedService->unhideLocation(...$parameters);
    }

    public function testMoveSubtreeDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            $this->createMock(Location::class),
            $this->createMock(Location::class),
        ];

        $serviceMock->expects($this->once())->method('moveSubtree')->with(...$parameters);

        $decoratedService->moveSubtree(...$parameters);
    }

    public function testDeleteLocationDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [$this->createMock(Location::class)];

        $serviceMock->expects($this->once())->method('deleteLocation')->with(...$parameters);

        $decoratedService->deleteLocation(...$parameters);
    }

    public function testNewLocationCreateStructDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [self::EXAMPLE_LOCATION_ID];

        $serviceMock->expects($this->once())->method('newLocationCreateStruct')->with(...$parameters);

        $decoratedService->newLocationCreateStruct(...$parameters);
    }

    public function testNewLocationUpdateStructDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [];

        $serviceMock->expects($this->once())->method('newLocationUpdateStruct')->with(...$parameters);

        $decoratedService->newLocationUpdateStruct(...$parameters);
    }

    public function testGetAllLocationsCountDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [];

        $serviceMock->expects($this->once())->method('getAllLocationsCount')->with(...$parameters);

        $decoratedService->getAllLocationsCount(...$parameters);
    }

    public function testLoadAllLocationsDecorator()
    {
        $serviceMock = $this->createServiceMock();
        $decoratedService = $this->createDecorator($serviceMock);

        $parameters = [
            897,
            847,
        ];

        $serviceMock->expects($this->once())->method('loadAllLocations')->with(...$parameters);

        $decoratedService->loadAllLocations(...$parameters);
    }
}

class_alias(LocationServiceDecoratorTest::class, 'eZ\Publish\SPI\Repository\Tests\Decorator\LocationServiceDecoratorTest');
