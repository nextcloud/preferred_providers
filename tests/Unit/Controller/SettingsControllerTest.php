<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Controller;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Controller\SettingsController;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class SettingsControllerTest extends TestCase {

	private IConfig&MockObject $config;
	private IUserManager&MockObject $userManager;
	private IGroupManager&MockObject $groupManager;
	private LoggerInterface&MockObject $logger;
	private ITimeFactory&MockObject $timeFactory;
	private IAppManager&MockObject $appManager;
	private ISecureRandom&MockObject $secureRandom;
	private IManager&MockObject $notificationsManager;
	private VerifyMailHelper&MockObject $verifyMailHelper;
	private IAppConfig&MockObject $appConfig;

	private SettingsController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->notificationsManager = $this->createMock(IManager::class);
		$this->verifyMailHelper = $this->createMock(VerifyMailHelper::class);
		$this->appConfig = $this->createMock(IAppConfig::class);

		// The constructor computes $this->appRoot = $this->appManager->getAppPath($appName)
		// so this must be stubbed before instantiating the controller.
		$this->appManager->method('getAppPath')->willReturn('/tmp/app');

		$this->controller = new SettingsController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->config,
			$this->userManager,
			$this->groupManager,
			$this->logger,
			$this->timeFactory,
			$this->appManager,
			$this->secureRandom,
			$this->notificationsManager,
			$this->verifyMailHelper,
			$this->appConfig,
		);
	}

	public function testResetToken(): void {
		$this->secureRandom->method('generate')->with(10)->willReturn('random-string');

		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with('preferred_providers', 'provider_token', $this->isType('string'));

		$response = $this->controller->resetToken();

		$this->assertInstanceOf(DataResponse::class, $response);
		$data = $response->getData();
		$this->assertArrayHasKey('token', $data);
		$this->assertSame(md5('random-string'), $data['token']);
	}

	public function testSetGroupsForAll(): void {
		$this->groupManager->method('groupExists')->with('g1')->willReturn(true);

		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with('preferred_providers', 'provider_groups', 'g1');

		$response = $this->controller->setGroups(['g1'], 'all');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(['g1'], $response->getData()['groups']);
	}

	public function testSetGroupsForConfirmed(): void {
		$this->groupManager->method('groupExists')->with('g1')->willReturn(true);

		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with('preferred_providers', 'provider_groups_confirmed', 'g1');

		$response = $this->controller->setGroups(['g1'], 'confirmed');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(['g1'], $response->getData()['groups']);
	}

	public function testSetGroupsMissingGroupThrows(): void {
		$this->groupManager->method('groupExists')->with('missing')->willReturn(false);

		$this->expectException(OCSNotFoundException::class);

		$this->controller->setGroups(['missing']);
	}

	public function testSetGroupsBogusTargetThrows(): void {
		$this->groupManager->method('groupExists')->with('g1')->willReturn(true);

		$this->expectException(OCSBadRequestException::class);

		$this->controller->setGroups(['g1'], 'bogus');
	}

	public function testReactivateUnknownUserThrows(): void {
		$this->userManager->method('get')->with('nouser')->willReturn(null);

		$this->expectException(OCSNotFoundException::class);

		$this->controller->reactivate('nouser');
	}

	public function testReactivateUserWithoutEmailThrows(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn(null);
		$this->userManager->method('get')->with('user')->willReturn($user);

		$this->expectException(OCSBadRequestException::class);

		$this->controller->reactivate('user');
	}

	public function testReactivateHappyPath(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('user@example.com');
		$user->method('isEnabled')->willReturn(true);
		$user->expects($this->never())->method('setEnabled');
		$this->userManager->method('get')->with('user')->willReturn($user);

		$emailTemplate = $this->createMock(IEMailTemplate::class);
		$this->verifyMailHelper->expects($this->once())
			->method('generateTemplate')
			->with($user)
			->willReturn($emailTemplate);
		$this->verifyMailHelper->expects($this->once())
			->method('sendMail')
			->with($user, $emailTemplate);

		$notification = $this->createMock(INotification::class);
		$notification->method($this->anything())->willReturnSelf();
		$this->notificationsManager->method('createNotification')->willReturn($notification);
		$this->notificationsManager->expects($this->once())->method('notify')->with($notification);

		$response = $this->controller->reactivate('user');

		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertSame(['status' => 'success'], $response->getData());
	}
}
