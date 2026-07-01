<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Controller;

use OC\Authentication\Token\IProvider;
use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Controller\PasswordController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class PasswordControllerTest extends TestCase {

	private IConfig&MockObject $config;
	private IL10N&MockObject $l10n;
	private IUserManager&MockObject $userManager;
	private ICrypto&MockObject $crypto;
	private IURLGenerator&MockObject $urlGenerator;
	private IUserSession&MockObject $userSession;
	private LoggerInterface&MockObject $logger;
	private IProvider&MockObject $tokenProvider;
	private ISecureRandom&MockObject $secureRandom;

	private PasswordController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->tokenProvider = $this->createMock(IProvider::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);

		$this->l10n->method('t')->willReturnArgument(0);

		$this->controller = new PasswordController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->config,
			$this->l10n,
			$this->userManager,
			$this->crypto,
			$this->urlGenerator,
			$this->userSession,
			$this->logger,
			$this->tokenProvider,
			$this->secureRandom,
		);
	}

	public function testSetPasswordInvalidToken(): void {
		$user = $this->createMock(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->with('user@example.com')->willReturn($user);

		$this->config->method('getUserValue')
			->with('user@example.com', Application::APP_ID, 'set_password')
			->willReturn('encrypted');
		$this->config->method('getSystemValue')->willReturn('secret');
		// Decrypted token does not match the passed one -> hash_equals fails
		$this->crypto->method('decrypt')->willReturn('the-real-token');

		$response = $this->controller->setPassword('wrong-token', 'user@example.com');

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	public function testSetPasswordUnknownUser(): void {
		$this->userManager->method('get')->with('user@example.com')->willReturn(null);

		$response = $this->controller->setPassword('any-token', 'user@example.com');

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	public function testSubmitPasswordTooLong(): void {
		$token = 'valid-token';

		$user = $this->createMock(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->with('user@example.com')->willReturn($user);

		$this->config->method('getUserValue')
			->with('user@example.com', Application::APP_ID, 'set_password')
			->willReturn('encrypted');
		$this->config->method('getSystemValue')->willReturn('secret');
		// Token check passes: decrypted token equals passed token
		$this->crypto->method('decrypt')->willReturn($token);

		$password = str_repeat('a', 101);

		$response = $this->controller->submitPassword($token, 'user@example.com', $password);

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	public function testSubmitPasswordInvalidToken(): void {
		$user = $this->createMock(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->with('user@example.com')->willReturn($user);

		$this->config->method('getUserValue')
			->with('user@example.com', Application::APP_ID, 'set_password')
			->willReturn('encrypted');
		$this->config->method('getSystemValue')->willReturn('secret');
		// Token check fails before the length check
		$this->crypto->method('decrypt')->willReturn('the-real-token');

		$response = $this->controller->submitPassword('wrong-token', 'user@example.com', 'short');

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}
}
