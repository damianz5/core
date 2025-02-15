<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Tests\Core\MVC\Symfony\Security\Authentication;

use Ibexa\Contracts\Core\Repository\Exceptions\PasswordInUnsupportedFormatException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\UserService;
use Ibexa\Contracts\Core\Repository\Values\User\User as APIUser;
use Ibexa\Core\MVC\Symfony\Security\Authentication\RepositoryAuthenticationProvider;
use Ibexa\Core\MVC\Symfony\Security\User;
use Ibexa\Core\Repository\User\Exception\UnsupportedPasswordHashType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RepositoryAuthenticationProviderTest extends TestCase
{
    private const UNSUPPORTED_USER_PASSWORD_HASH_TYPE = 5;

    /** @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface */
    private $encoderFactory;

    /** @var \Ibexa\Core\MVC\Symfony\Security\Authentication\RepositoryAuthenticationProvider */
    private $authProvider;

    /** @var \Ibexa\Contracts\Core\Repository\PermissionResolver|\PHPUnit\Framework\MockObject\MockObject */
    private $permissionResolver;

    /**
     * @var \Symfony\Component\Security\Core\User\UserProviderInterface
     */
    private $userProvider;

    /** @var \Ibexa\Contracts\Core\Repository\UserService|\PHPUnit\Framework\MockObject\MockObject */
    private $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->authProvider = new RepositoryAuthenticationProvider(
            $this->userProvider,
            $this->createMock(UserCheckerInterface::class),
            'foo',
            $this->encoderFactory
        );
        $this->permissionResolver = $this->createMock(PermissionResolver::class);
        $this->userService = $this->createMock(UserService::class);
        $this->authProvider->setPermissionResolver($this->permissionResolver);
        $this->authProvider->setUserService($this->userService);
    }

    public function testAuthenticationNotEzUser()
    {
        $password = 'some_encoded_password';
        $user = $this->createMock(UserInterface::class);
        $user
            ->expects($this->any())
            ->method('getPassword')
            ->will($this->returnValue($password));

        $tokenUser = $this->createMock(UserInterface::class);
        $tokenUser
            ->expects($this->any())
            ->method('getPassword')
            ->will($this->returnValue($password));
        $token = new UsernamePasswordToken($tokenUser, 'foo', 'bar');

        $method = new \ReflectionMethod($this->authProvider, 'checkAuthentication');
        $method->setAccessible(true);
        $method->invoke($this->authProvider, $user, $token);
    }

    public function testCheckAuthenticationCredentialsChanged()
    {
        $this->expectException(BadCredentialsException::class);

        $apiUser = $this->getMockBuilder(APIUser::class)
            ->setConstructorArgs([['passwordHash' => 'some_encoded_password']])
            ->setMethods(['getUserId'])
            ->getMockForAbstractClass();
        $apiUser
            ->expects($this->once())
            ->method('getUserId')
            ->will($this->returnValue(456));
        $tokenUser = new User($apiUser);
        $token = new UsernamePasswordToken($tokenUser, 'foo', 'bar');

        $renewedApiUser = $this->getMockBuilder(APIUser::class)
            ->setConstructorArgs([['passwordHash' => 'renewed_encoded_password']])
            ->getMockForAbstractClass();

        $user = $this->createMock(User::class);
        $user
            ->expects($this->any())
            ->method('getAPIUser')
            ->will($this->returnValue($renewedApiUser));

        $method = new \ReflectionMethod($this->authProvider, 'checkAuthentication');
        $method->setAccessible(true);
        $method->invoke($this->authProvider, $user, $token);
    }

    public function testCheckAuthenticationAlreadyLoggedIn()
    {
        $password = 'encoded_password';

        $apiUser = $this->getMockBuilder(APIUser::class)
            ->setConstructorArgs([['passwordHash' => $password]])
            ->setMethods(['getUserId'])
            ->getMockForAbstractClass();
        $tokenUser = new User($apiUser);
        $token = new UsernamePasswordToken($tokenUser, 'foo', 'bar');

        $user = $this->createMock(User::class);
        $user
            ->expects($this->once())
            ->method('getAPIUser')
            ->will($this->returnValue($apiUser));

        $this->permissionResolver
            ->expects($this->once())
            ->method('setCurrentUserReference')
            ->with($apiUser);

        $method = new \ReflectionMethod($this->authProvider, 'checkAuthentication');
        $method->setAccessible(true);
        $method->invoke($this->authProvider, $user, $token);
    }

    public function testCheckAuthenticationFailed()
    {
        $this->expectException(BadCredentialsException::class);

        $apiUser = $this->createMock(APIUser::class);
        $user = $this->createMock(User::class);
        $user->method('getAPIUser')
            ->willReturn($apiUser);
        $userName = 'my_username';
        $password = 'foo';
        $token = new UsernamePasswordToken($userName, $password, 'bar');

        $this->userService
            ->expects($this->once())
            ->method('checkUserCredentials')
            ->with($apiUser, $password)
            ->willReturn(false);

        $method = new \ReflectionMethod($this->authProvider, 'checkAuthentication');
        $method->setAccessible(true);
        $method->invoke($this->authProvider, $user, $token);
    }

    public function testCheckAuthentication()
    {
        $userName = 'my_username';
        $password = 'foo';
        $token = new UsernamePasswordToken($userName, $password, 'bar');

        $apiUser = $this->createMock(APIUser::class);
        $user = $this->createMock(User::class);
        $user->method('getAPIUser')
            ->willReturn($apiUser);

        $this->userService
            ->expects($this->once())
            ->method('checkUserCredentials')
            ->with($apiUser, $password)
            ->willReturn(true);

        $this->permissionResolver
            ->expects($this->once())
            ->method('setCurrentUserReference')
            ->with($apiUser);

        $method = new \ReflectionMethod($this->authProvider, 'checkAuthentication');
        $method->setAccessible(true);
        $method->invoke($this->authProvider, $user, $token);
    }

    public function testCheckAuthenticationFailedWhenPasswordInUnsupportedFormat()
    {
        $this->expectException(PasswordInUnsupportedFormatException::class);

        $apiUser = $this->createMock(APIUser::class);
        $user = $this->createMock(User::class);
        $user->method('getAPIUser')
            ->willReturn($apiUser);
        $userName = 'my_username';
        $password = 'foo';
        $token = new UsernamePasswordToken($userName, $password, 'foo');
        $this->userProvider
            ->method('loadUserByUsername')
            ->willReturn($user);

        $this->userService
            ->expects($this->once())
            ->method('checkUserCredentials')
            ->with($apiUser, $password)
            ->willThrowException(new UnsupportedPasswordHashType(self::UNSUPPORTED_USER_PASSWORD_HASH_TYPE));

        $this->authProvider->authenticate($token);
    }
}

class_alias(RepositoryAuthenticationProviderTest::class, 'eZ\Publish\Core\MVC\Symfony\Security\Tests\Authentication\RepositoryAuthenticationProviderTest');
