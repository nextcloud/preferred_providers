<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Seyed Masih Sajadi <smasihsajadi@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Migration;

use OCA\Preferred_Providers\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Self-service reactivation only re-enables accounts flagged with `pp_disabled=1`,
 * a flag introduced together with the feature. Accounts that were auto-disabled
 * *before* the feature shipped never got the flag and would be stuck.
 *
 * This repair step backfills the flag. Rather than scanning every user, it starts
 * from the small set of accounts that still hold a Preferred Providers token
 * (`set_password`/`verify_token`) — i.e. accounts this app provisioned and that
 * never completed verification — and flags the ones that are currently disabled.
 * That token is a best-effort signal the account was auto-disabled by this app
 * rather than by an admin. It is idempotent (already-flagged accounts are skipped).
 *
 * Cost scales with the number of unverified/pending PP accounts (typically tiny),
 * not with total user count, and uses only varchar comparisons so it is portable
 * across sqlite/mysql/pgsql/oracle (no CLOB `configvalue` comparison).
 */
class BackfillPpDisabled implements IRepairStep {

	public function __construct(
		private readonly IDBConnection $db,
		private readonly IConfig $config,
		private readonly IUserManager $userManager,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Backfill pp_disabled flag for accounts auto-disabled before self-service reactivation';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$stamped = 0;
		foreach ($this->getCandidateUserIds() as $userId) {
			if ($this->config->getUserValue($userId, Application::APP_ID, 'pp_disabled', '') === '1') {
				continue;
			}

			$user = $this->userManager->get($userId);
			// only accounts that are actually disabled are eligible; a null user is
			// a stale preferences row for a deleted account
			if ($user === null || $user->isEnabled()) {
				continue;
			}

			$this->config->setUserValue($userId, Application::APP_ID, 'pp_disabled', '1');
			$stamped++;
		}

		$output->info(sprintf('Flagged %d previously auto-disabled account(s) for self-service reactivation', $stamped));
	}

	/**
	 * User IDs that still carry a leftover Preferred Providers verification token.
	 *
	 * @return list<string>
	 */
	private function getCandidateUserIds(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('userid')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->in(
				'configkey',
				$qb->createNamedParameter(['set_password', 'verify_token'], IQueryBuilder::PARAM_STR_ARRAY)
			));

		$result = $qb->executeQuery();
		$userIds = [];
		while ($row = $result->fetch()) {
			$userIds[] = (string)$row['userid'];
		}
		$result->closeCursor();

		return $userIds;
	}
}
