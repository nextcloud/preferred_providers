<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Notification;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Notification\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class NotifierTest extends TestCase {

	private IFactory&MockObject $l10nFactory;
	private IUserManager&MockObject $userManager;
	private IURLGenerator&MockObject $urlGenerator;

	private Notifier $notifier;

	protected function setUp(): void {
		parent::setUp();

		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->notifier = new Notifier(
			Application::APP_ID,
			$this->l10nFactory,
			$this->userManager,
			$this->urlGenerator,
		);
	}

	public function testGetID(): void {
		$this->assertSame(Application::APP_ID, $this->notifier->getID());
	}

	public function testGetName(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->l10nFactory->method('get')
			->with(Application::APP_ID)
			->willReturn($l10n);

		$this->assertSame('Simple signup', $this->notifier->getName());
	}

	public function testPrepareWrongApp(): void {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn('other_app');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Incorrect app');

		$this->notifier->prepare($notification, 'en');
	}

	public function testPrepareUnknownSubject(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->l10nFactory->method('get')->willReturn($l10n);

		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn(Application::APP_ID);
		$notification->method('getSubject')->willReturn('some_unknown_subject');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown subject');

		$this->notifier->prepare($notification, 'en');
	}

	public function testPrepareVerifyEmail(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->l10nFactory->method('get')
			->with(Application::APP_ID, 'en')
			->willReturn($l10n);

		$this->urlGenerator->method('imagePath')
			->with(Application::APP_ID, 'app-dark.svg')
			->willReturn('/img/app-dark.svg');
		$this->urlGenerator->method('getAbsoluteURL')
			->with('/img/app-dark.svg')
			->willReturn('https://cloud/img/app-dark.svg');

		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn(Application::APP_ID);
		$notification->method('getSubject')->willReturn('verify_email');
		$notification->method('getUser')->willReturn('user@example.com');

		$notification->expects($this->once())
			->method('setIcon')
			->with('https://cloud/img/app-dark.svg')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with('Please verify your email address')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('A confirmation mail was sent to user@example.com. Make sure to confirm the account within 6 hours.')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setRichMessage')
			->willReturnSelf();

		$result = $this->notifier->prepare($notification, 'en');

		$this->assertSame($notification, $result);
	}
}
