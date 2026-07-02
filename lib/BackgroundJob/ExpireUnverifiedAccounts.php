<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\BackgroundJob;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Helper\ExpireUserTrait;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ExpireUnverifiedAccounts extends TimedJob {
	use ExpireUserTrait;

	public function __construct(
		ITimeFactory $timeFactory,
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
		private readonly IDBConnection $connection,
		private readonly VerifyMailHelper $mailHelper,
		private readonly IUserManager $userManager,
	) {
		parent::__construct($timeFactory);

		// Run once per 15 minutes
		$this->setInterval(15 * 60);
	}

	/**
	 * @return void
	 */
	#[\Override]
	public function run($argument) {
		// process if token is 5min old
		$users = $this->getUsersForUserLowerThanValue(Application::APP_ID, 'disable_user_after', strval(time()));
		foreach ($users as $userId) {
			$this->expireUser($userId);
		}
	}

	/**
	 * Determines the users that have the given value set for a specific app-key-pair
	 *
	 * @param string $appName the app to get the user for
	 * @param string $key the key to get the user for
	 * @param string $value the value to get the user for
	 * @return array of user IDs
	 */
	private function getUsersForUserLowerThanValue($appName, $key, $value) {
		$sql = 'SELECT `userid` FROM `*PREFIX*preferences` '
			. 'WHERE `appid` = ? AND `configkey` = ? ';

		if ($this->config->getSystemValueString('dbtype', 'sqlite') === 'oci') {
			//oracle hack: need to explicitly cast CLOB to CHAR for comparison
			$sql .= 'AND to_char(`configvalue`) < ?';
		} else {
			$sql .= 'AND `configvalue` < ?';
		}

		$result = $this->connection->executeQuery($sql, [$appName, $key, $value]);

		$userIDs = [];
		while ($row = $result->fetch()) {
			$userIDs[] = $row['userid'];
		}

		return $userIDs;
	}
}
