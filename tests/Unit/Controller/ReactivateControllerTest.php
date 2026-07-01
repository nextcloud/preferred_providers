<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Controller;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Controller\ReactivateController;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ReactivateControllerTest extends TestCase {
	private IConfig&MockObject $config;
	private IUserManager&MockObject $userManager;
	private ITimeFactory&MockObject $timeFactory;
	private VerifyMailHelper&MockObject $verifyMailHelper;
	private LoggerInterface&MockObject $logger;

	private ReactivateController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->verifyMailHelper = $this->createMock(VerifyMailHelper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ReactivateController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->config,
			$this->userManager,
			$this->timeFactory,
			$this->verifyMailHelper,
			$this->logger,
		);
	}

	public function testShowFormRendersForm(): void {
		$response = $this->controller->showForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('reactivate-public', $response->getTemplateName());
		$this->assertSame('form', $response->getParams()['status']);
	}

	public function testRequestReactivationUnknownEmailIsSilent(): void {
		$this->userManager->method('get')->with('nobody@example.com')->willReturn(null);
		$this->verifyMailHelper->expects($this->never())->method('sendMail');

		$response = $this->controller->requestReactivation('nobody@example.com');

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('sent', $response->getParams()['status']);
	}

	public function testRequestReactivationSkipsNonPpDisabledAccount(): void {
		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('admin-disabled@example.com')->willReturn($user);
		// not flagged as auto-disabled by this app
		$this->config->method('getUserValue')->willReturn('');

		$user->expects($this->never())->method('setEnabled');
		$this->verifyMailHelper->expects($this->never())->method('sendMail');

		$response = $this->controller->requestReactivation('admin-disabled@example.com');

		$this->assertSame('sent', $response->getParams()['status']);
	}

	public function testRequestReactivationReenablesPpDisabledAccount(): void {
		$email = 'user@example.com';
		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')->with($email)->willReturn($user);
		$this->config->method('getUserValue')
			->with($email, Application::APP_ID, 'pp_disabled', '')
			->willReturn('1');
		$this->timeFactory->method('getTime')->willReturn(1000);

		$user->expects($this->once())->method('setEnabled')->with(true);
		$this->config->expects($this->once())
			->method('setUserValue')
			->with($email, Application::APP_ID, 'disable_user_after', $this->isType('string'));
		$this->config->expects($this->once())
			->method('deleteUserValue')
			->with($email, Application::APP_ID, 'pp_disabled');
		$this->verifyMailHelper->method('generateTemplate')
			->with($user)
			->willReturn($this->createMock(IEMailTemplate::class));
		$this->verifyMailHelper->expects($this->once())->method('sendMail')->with($user, $this->anything());

		$response = $this->controller->requestReactivation($email);

		$this->assertSame('sent', $response->getParams()['status']);
	}
}
