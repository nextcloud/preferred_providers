<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Tests\Unit\Migration;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Migration\BackfillPpDisabled;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class BackfillPpDisabledTest extends TestCase {
	private IDBConnection&MockObject $db;
	private IConfig&MockObject $config;
	private IUserManager&MockObject $userManager;
	private BackfillPpDisabled $step;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->step = new BackfillPpDisabled($this->db, $this->config, $this->userManager);
	}

	public function testGetName(): void {
		$this->assertIsString($this->step->getName());
	}

	public function testBackfillsOnlyDisabledUnflaggedAccounts(): void {
		// candidate users that still hold a PP token
		$this->stubCandidateUsers(['alice', 'bob', 'carol', 'ghost']);

		// alice: disabled, not yet flagged   -> flagged
		// bob:   already flagged             -> skipped
		// carol: still enabled               -> skipped
		// ghost: deleted (userManager null)  -> skipped
		$this->config->method('getUserValue')->willReturnCallback(
			static fn (string $uid, string $app, string $key, string $default = ''): string
				=> $uid === 'bob' && $key === 'pp_disabled' ? '1' : $default
		);

		$alice = $this->createMock(IUser::class);
		$alice->method('isEnabled')->willReturn(false);
		$carol = $this->createMock(IUser::class);
		$carol->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturnMap([
			['alice', $alice],
			['carol', $carol],
			['ghost', null],
		]);

		$this->config->expects($this->once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'pp_disabled', '1');

		$this->step->run($this->createMock(IOutput::class));
	}

	/**
	 * @param list<string> $userIds
	 */
	private function stubCandidateUsers(array $userIds): void {
		$rows = array_map(static fn (string $u): array => ['userid' => $u], $userIds);

		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturnOnConsecutiveCalls(...[...$rows, false]);

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('cmp');
		$expr->method('in')->willReturn('cmp');

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('selectDistinct')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn('param');
		$qb->method('executeQuery')->willReturn($result);

		$this->db->method('getQueryBuilder')->willReturn($qb);
	}
}
