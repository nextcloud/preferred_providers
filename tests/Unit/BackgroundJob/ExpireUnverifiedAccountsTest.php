<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\BackgroundJob;

use OCA\Preferred_Providers\BackgroundJob\ExpireUnverifiedAccounts;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\IResult;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ExpireUnverifiedAccountsTest extends TestCase {
	private ITimeFactory&MockObject $timeFactory;
	private IConfig&MockObject $config;
	private LoggerInterface&MockObject $logger;
	private IDBConnection&MockObject $connection;
	private VerifyMailHelper&MockObject $mailHelper;
	private IUserManager&MockObject $userManager;

	private ExpireUnverifiedAccounts $job;

	protected function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->mailHelper = $this->createMock(VerifyMailHelper::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->timeFactory->method('getTime')->willReturn(1000);

		$this->job = new ExpireUnverifiedAccounts(
			$this->timeFactory,
			$this->config,
			$this->logger,
			$this->connection,
			$this->mailHelper,
			$this->userManager,
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

		// Nothing to expire: no user lookups, no mail, no disabling.
		$this->userManager->expects($this->never())->method('get');
		$this->mailHelper->expects($this->never())->method('sendMail');

		$this->invokeRun();
	}

	public function testRunExpiresMatchingUser(): void {
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturnOnConsecutiveCalls(
			['userid' => 'alice'],
			false,
		);

		$this->config->method('getSystemValueString')
			->with('dbtype', 'sqlite')
			->willReturn('sqlite');
		$this->connection->method('executeQuery')->willReturn($result);

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$user->method('getEMailAddress')->willReturn('alice@example.com');
		$user->expects($this->once())->method('setEnabled')->with(false);

		$this->userManager->method('get')->with('alice')->willReturn($user);

		$this->config->expects($this->atLeastOnce())
			->method('deleteUserValue');
		$this->mailHelper->method('generateTemplate')
			->with($user, false, true)
			->willReturn($this->createMock(\OCP\Mail\IEMailTemplate::class));
		$this->mailHelper->expects($this->once())
			->method('sendMail')
			->with($user, $this->anything());

		$this->invokeRun();
	}

	public function testRunSkipsAlreadyDisabledUser(): void {
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturnOnConsecutiveCalls(
			['userid' => 'bob'],
			false,
		);

		$this->config->method('getSystemValueString')->willReturn('sqlite');
		$this->connection->method('executeQuery')->willReturn($result);

		$user = $this->createMock(\OCP\IUser::class);
		$user->method('isEnabled')->willReturn(false);
		$user->expects($this->never())->method('setEnabled');

		$this->userManager->method('get')->with('bob')->willReturn($user);
		$this->mailHelper->expects($this->never())->method('sendMail');

		$this->invokeRun();
	}

	private function invokeRun(): void {
		$method = new \ReflectionMethod(ExpireUnverifiedAccounts::class, 'run');
		$method->invoke($this->job, null);
	}
}
