<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Mailer;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class VerifyMailHelperTest extends TestCase {

	private ThemingDefaults&MockObject $themingDefaults;
	private IURLGenerator&MockObject $urlGenerator;
	private IL10N&MockObject $l10n;
	private IMailer&MockObject $mailer;
	private ISecureRandom&MockObject $secureRandom;
	private ITimeFactory&MockObject $timeFactory;
	private IConfig&MockObject $config;
	private ICrypto&MockObject $crypto;

	private VerifyMailHelper $helper;

	protected function setUp(): void {
		parent::setUp();

		$this->themingDefaults = $this->createMock(ThemingDefaults::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);

		$this->l10n->method('t')->willReturnArgument(0);
		$this->themingDefaults->method('getName')->willReturn('TestCloud');
		$this->mailer->method('createEMailTemplate')
			->willReturn($this->createMock(IEMailTemplate::class));

		$this->helper = new VerifyMailHelper(
			Application::APP_ID,
			$this->themingDefaults,
			$this->urlGenerator,
			$this->l10n,
			$this->mailer,
			$this->secureRandom,
			$this->timeFactory,
			$this->config,
			$this->crypto,
		);
	}

	public function testGenerateTemplateNotVerified(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$user->method('getEMailAddress')->willReturn('u@e.com');

		$this->secureRandom->method('generate')->willReturn('the-token');
		$this->timeFactory->method('getTime')->willReturn(1000);
		$this->crypto->method('encrypt')->willReturn('enc');
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('user1', Application::APP_ID, 'verify_token', 'enc');

		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->willReturn('https://cloud/confirm');
		$this->urlGenerator->expects($this->never())->method('getAbsoluteURL');

		$template = $this->helper->generateTemplate($user, false);

		$this->assertInstanceOf(IEMailTemplate::class, $template);
	}

	public function testGenerateTemplateVerified(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$user->method('getEMailAddress')->willReturn('u@e.com');

		$this->secureRandom->method('generate')->willReturn('the-token');
		$this->timeFactory->method('getTime')->willReturn(1000);
		$this->crypto->method('encrypt')->willReturn('enc');

		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('/')
			->willReturn('https://cloud/');
		$this->urlGenerator->expects($this->never())->method('linkToRouteAbsolute');

		$template = $this->helper->generateTemplate($user, true);

		$this->assertInstanceOf(IEMailTemplate::class, $template);
	}

	public function testSendMail(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('u@e.com');
		$user->method('getDisplayName')->willReturn('U');

		$template = $this->createMock(IEMailTemplate::class);

		$message = $this->createMock(IMessage::class);
		$this->mailer->expects($this->once())
			->method('createMessage')
			->willReturn($message);
		$message->expects($this->once())
			->method('setTo')
			->with(['u@e.com' => 'U']);
		$message->expects($this->once())
			->method('useTemplate')
			->with($template);
		$this->mailer->expects($this->once())
			->method('send')
			->with($message);

		$this->helper->sendMail($user, $template);
	}
}
