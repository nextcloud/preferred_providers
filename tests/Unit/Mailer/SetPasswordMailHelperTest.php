<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Mailer;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Mailer\SetPasswordMailHelper;
use OCA\Theming\ThemingDefaults;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SetPasswordMailHelperTest extends TestCase {

	private ThemingDefaults&MockObject $themingDefaults;
	private IURLGenerator&MockObject $urlGenerator;
	private IL10N&MockObject $l10n;
	private IMailer&MockObject $mailer;
	private IConfig&MockObject $config;
	private ICrypto&MockObject $crypto;

	private SetPasswordMailHelper $helper;

	protected function setUp(): void {
		parent::setUp();

		$this->themingDefaults = $this->createMock(ThemingDefaults::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->config = $this->createMock(IConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);

		$this->l10n->method('t')->willReturnArgument(0);
		$this->themingDefaults->method('getName')->willReturn('TestCloud');
		$this->mailer->method('createEMailTemplate')
			->willReturn($this->createMock(IEMailTemplate::class));

		$this->helper = new SetPasswordMailHelper(
			Application::APP_ID,
			$this->themingDefaults,
			$this->urlGenerator,
			$this->l10n,
			$this->mailer,
			$this->config,
			$this->crypto,
		);
	}

	public function testGenerateTemplate(): void {
		$this->config->method('getUserValue')
			->with('u@e.com', Application::APP_ID, 'set_password')
			->willReturn('encrypted-token');
		$this->crypto->expects($this->once())
			->method('decrypt')
			->willReturn('the-token');

		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->willReturn('https://cloud/set-password');

		$template = $this->helper->generateTemplate('u@e.com', false);

		$this->assertInstanceOf(IEMailTemplate::class, $template);
	}

	public function testSendMail(): void {
		$template = $this->createMock(IEMailTemplate::class);

		$message = $this->createMock(IMessage::class);
		$this->mailer->expects($this->once())
			->method('createMessage')
			->willReturn($message);
		$message->expects($this->once())
			->method('setTo')
			->with(['u@e.com']);
		$message->expects($this->once())
			->method('useTemplate')
			->with($template);
		$this->mailer->expects($this->once())
			->method('send')
			->with($message);

		$this->helper->sendMail('u@e.com', $template);
	}

	public function testSetL10N(): void {
		$newL10n = $this->createMock(IL10N::class);

		$this->helper->setL10N($newL10n);

		$this->addToAssertionCount(1);
	}
}
