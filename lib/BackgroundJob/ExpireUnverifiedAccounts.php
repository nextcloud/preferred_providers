<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\BackgroundJob;

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

	/** @var string */
	private $appName;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/** @var IConfig */
	private $config;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var IDBConnection */
	private $connection;

	public function __construct(
		ITimeFactory $timeFactory,
		IConfig $config,
		private LoggerInterface $logger,
		IDBConnection $connection,
		private VerifyMailHelper $mailHelper,
	) {
		parent::__construct($timeFactory);

		$this->appName = 'preferred_providers';
		$this->config = $config;
		$this->connection = $connection;

		// Run once per 15 minutes
		$this->setInterval(15 * 60);
	}

	/**
	 * @return void
	 */
	#[\Override]
	public function run($argument) {
		// process if token is 5min old
		$users = $this->getUsersForUserLowerThanValue($this->appName, 'disable_user_after', strval(time()));
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
