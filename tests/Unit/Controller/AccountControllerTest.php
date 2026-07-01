<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Controller;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Controller\AccountController;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AccountControllerTest extends TestCase {

	private IConfig&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private IUserManager&MockObject $userManager;
	private IGroupManager&MockObject $groupManager;
	private IMailer&MockObject $mailer;
	private VerifyMailHelper&MockObject $verifyMailHelper;
	private LoggerInterface&MockObject $logger;
	private IURLGenerator&MockObject $urlGenerator;
	private ITimeFactory&MockObject $timeFactory;
	private ICrypto&MockObject $crypto;
	private ISecureRandom&MockObject $secureRandom;
	private IManager&MockObject $notificationsManager;

	private AccountController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->verifyMailHelper = $this->createMock(VerifyMailHelper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->notificationsManager = $this->createMock(IManager::class);

		$this->controller = new AccountController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->config,
			$this->userManager,
			$this->groupManager,
			$this->mailer,
			$this->verifyMailHelper,
			$this->logger,
			$this->urlGenerator,
			$this->timeFactory,
			$this->crypto,
			$this->secureRandom,
			$this->notificationsManager,
			$this->appConfig,
		);
	}

	public function testRequestAccountInvalidToken(): void {
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'provider_token')
			->willReturn('the-real-token');

		$response = $this->controller->requestAccount('wrong-token', 'user@example.com');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testRequestAccountEmptyServerTokenIsRejected(): void {
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'provider_token')
			->willReturn('');

		$response = $this->controller->requestAccount('', 'user@example.com');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testRequestAccountInvalidMail(): void {
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'provider_token')
			->willReturn('token');
		$this->mailer->method('validateMailAddress')->with('not-a-mail')->willReturn(false);

		$response = $this->controller->requestAccount('token', 'not-a-mail');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testRequestAccountUserAlreadyExists(): void {
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'provider_token')
			->willReturn('token');
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->userManager->method('userExists')->with('user@example.com')->willReturn(true);

		$response = $this->controller->requestAccount('token', 'user@example.com');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testRequestAccountHappyPath(): void {
		$email = 'user@example.com';

		$this->appConfig->method('getValueString')
			->willReturnCallback(static fn (string $app, string $key, string $default = ''): string => match ($key) {
				'provider_token' => 'token',
				'provider_groups' => 'partners',
				default => '',
			});
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->userManager->method('userExists')->willReturn(false);

		$newUser = $this->createMock(IUser::class);
		$this->userManager->method('createUser')->with($email, $this->isType('string'))->willReturn($newUser);
		$newUser->expects($this->once())->method('setSystemEMailAddress')->with($email);

		// The controller merges provider_groups with the (empty) unconfirmed
		// groups, so it also probes an empty group id: keep the stub lenient.
		$group = $this->createMock(IGroup::class);
		$this->groupManager->method('groupExists')->willReturnCallback(static fn (string $gid): bool => $gid === 'partners');
		$this->groupManager->method('get')->willReturn($group);
		$group->expects($this->once())->method('addUser')->with($newUser);

		$this->timeFactory->method('getTime')->willReturn(1000);
		$this->secureRandom->method('generate')->willReturn('random-value');
		$this->crypto->method('encrypt')->willReturn('encrypted');

		$notification = $this->createMock(INotification::class);
		$notification->method($this->anything())->willReturnSelf();
		$this->notificationsManager->method('createNotification')->willReturn($notification);
		$this->notificationsManager->expects($this->once())->method('notify')->with($notification);

		$this->verifyMailHelper->expects($this->once())->method('sendMail');
		$this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://cloud/set-password');

		$response = $this->controller->requestAccount('token', $email);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertSame('https://cloud/set-password', $data['data']['setPassword']);
	}
}
