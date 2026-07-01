<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\BackgroundJob;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\BackgroundJob\NotifyUnsetPassword;
use OCA\Preferred_Providers\Mailer\SetPasswordMailHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\IResult;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class NotifyUnsetPasswordTest extends TestCase {

	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;
	private IDBConnection&MockObject $connection;
	private SetPasswordMailHelper&MockObject $mailHelper;
	private IConfig&MockObject $config;

	private NotifyUnsetPassword $job;

	protected function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->mailHelper = $this->createMock(SetPasswordMailHelper::class);
		$this->config = $this->createMock(IConfig::class);

		$this->timeFactory->method('getTime')->willReturn(1000);

		$this->job = new NotifyUnsetPassword(
			$this->timeFactory,
			$this->logger,
			$this->connection,
			$this->mailHelper,
			$this->config,
		);
	}

	public function testIsTimedJob(): void {
		$this->assertInstanceOf(TimedJob::class, $this->job);
	}

	public function testRunWithNoMatchingUsers(): void {
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturn(false);

		$this->config->method('getSystemValueString')
			->with('dbtype', 'sqlite')
			->willReturn('sqlite');
		$this->connection->expects($this->once())
			->method('executeQuery')
			->willReturn($result);

		// No matching users: no mail is generated or sent, no cleanup.
		$this->mailHelper->expects($this->never())->method('generateTemplate');
		$this->mailHelper->expects($this->never())->method('sendMail');
		$this->config->expects($this->never())->method('deleteUserValue');

		$this->invokeRun();
	}

	public function testRunNotifiesMatchingUser(): void {
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturnOnConsecutiveCalls(
			['userid' => 'alice'],
			false,
		);

		$this->config->method('getSystemValueString')->willReturn('sqlite');
		$this->connection->method('executeQuery')->willReturn($result);

		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->mailHelper->expects($this->once())
			->method('generateTemplate')
			->with('alice')
			->willReturn($template);
		$this->mailHelper->expects($this->once())
			->method('sendMail')
			->with('alice', $template);

		// After sending, the reminder flag is deleted so only one mail goes out.
		$this->config->expects($this->once())
			->method('deleteUserValue')
			->with('alice', Application::APP_ID, 'remind_password');

		$this->invokeRun();
	}

	public function testRunSwallowsSendMailErrors(): void {
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturnOnConsecutiveCalls(
			['userid' => 'bob'],
			false,
		);

		$this->config->method('getSystemValueString')->willReturn('sqlite');
		$this->connection->method('executeQuery')->willReturn($result);

		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->mailHelper->method('generateTemplate')->willReturn($template);
		$this->mailHelper->method('sendMail')
			->willThrowException(new \Exception('SMTP down'));

		// On failure the reminder flag is not cleared and the error is logged.
		$this->config->expects($this->never())->method('deleteUserValue');
		$this->logger->expects($this->atLeastOnce())->method('debug');

		$this->invokeRun();
	}

	private function invokeRun(): void {
		$method = new \ReflectionMethod(NotifyUnsetPassword::class, 'run');
		$method->invoke($this->job, null);
	}
}
