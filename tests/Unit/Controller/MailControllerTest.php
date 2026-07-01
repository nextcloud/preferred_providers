<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Controller;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Controller\AccountController;
use OCA\Preferred_Providers\Controller\MailController;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class MailControllerTest extends TestCase {

	private IConfig&MockObject $config;
	private IL10N&MockObject $l10n;
	private LoggerInterface&MockObject $logger;
	private IUserManager&MockObject $userManager;
	private ICrypto&MockObject $crypto;
	private ITimeFactory&MockObject $timeFactory;
	private IURLGenerator&MockObject $urlGenerator;
	private VerifyMailHelper&MockObject $verifyMailHelper;
	private IGroupManager&MockObject $groupManager;
	private IAppConfig&MockObject $appConfig;

	private MailController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->verifyMailHelper = $this->createMock(VerifyMailHelper::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);

		$this->l10n->method('t')->willReturnArgument(0);

		$this->controller = new MailController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->config,
			$this->l10n,
			$this->logger,
			$this->userManager,
			$this->crypto,
			$this->timeFactory,
			$this->urlGenerator,
			$this->verifyMailHelper,
			$this->groupManager,
			$this->appConfig,
		);
	}

	public function testConfirmMailAddressInvalidToken(): void {
		$email = 'user@example.com';

		$user = $this->createMock(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$user->method('getEMailAddress')->willReturn($email);
		$this->userManager->method('get')->with($email)->willReturn($user);

		$this->config->method('getUserValue')->willReturn('encrypted');
		$this->config->method('getSystemValue')->willReturn('secret');
		$this->crypto->method('decrypt')->willReturn('9999999999:wrongtoken');
		$this->timeFactory->method('getTime')->willReturn(2000000000);

		$response = $this->controller->confirmMailAddress($email, 'validtoken');

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	public function testConfirmMailAddressUserNull(): void {
		$email = 'user@example.com';

		$this->userManager->method('get')->with($email)->willReturn(null);

		$response = $this->controller->confirmMailAddress($email, 'validtoken');

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	public function testConfirmMailAddressHappyPath(): void {
		$email = 'user@example.com';
		$token = 'validtoken';

		$user = $this->createMock(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$user->method('getEMailAddress')->willReturn($email);
		$this->userManager->method('get')->with($email)->willReturn($user);

		$this->config->method('getUserValue')->willReturn('encrypted');
		$this->config->method('getSystemValue')->willReturn('secret');

		$this->timeFactory->method('getTime')->willReturn(2000000000);
		// recent enough: time - 100 is well within the validateEmailDelay window
		$decrypted = (2000000000 - 100) . ':' . $token;
		$this->crypto->method('decrypt')->willReturn($decrypted);

		// remove user deadline & token
		$this->config->expects($this->exactly(2))
			->method('deleteUserValue')
			->willReturnCallback(function (string $userId, string $appName, string $key): void {
				$this->assertSame('user@example.com', $userId);
				$this->assertSame(Application::APP_ID, $appName);
				$this->assertContains($key, ['disable_user_after', 'verify_token']);
			});

		// welcome mail
		$this->verifyMailHelper->expects($this->once())
			->method('generateTemplate')
			->with($user, true)
			->willReturn($this->createMock(\OCP\Mail\IEMailTemplate::class));
		$this->verifyMailHelper->expects($this->once())->method('sendMail');

		// keep group loops empty
		$this->appConfig->method('getValueString')->willReturn('');
		$this->groupManager->method('groupExists')->willReturn(false);

		$this->urlGenerator->method('getAbsoluteURL')->with('/')->willReturn('https://cloud/');

		$response = $this->controller->confirmMailAddress($email, $token);

		$this->assertInstanceOf(RedirectResponse::class, $response);

		// sanity check the constant referenced by the controller
		$this->assertSame(21600, AccountController::validateEmailDelay);
	}
}
