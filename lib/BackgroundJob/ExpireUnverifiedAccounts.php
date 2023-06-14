<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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

	/** @var LoggerInterface */
	private $logger;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var IDBConnection */
	private $connection;

	/** @var VerifyMailHelper */
	private $mailHelper;

	public function __construct(ITimeFactory $timeFactory, IConfig $config, LoggerInterface $logger, IDBConnection $connection, VerifyMailHelper $mailHelper) {
		parent::__construct($timeFactory);

		$this->appName = 'preferred_providers';
		$this->config = $config;
		$this->logger = $logger;
		$this->connection = $connection;
		$this->mailHelper = $mailHelper;

		// Run once per 15 minutes
		$this->setInterval(15 * 60);
	}

	/**
	 * @return void
	 */
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
		$sql = 'SELECT `userid` FROM `*PREFIX*preferences` ' .
			'WHERE `appid` = ? AND `configkey` = ? ';

		if ($this->config->getSystemValueString('dbtype', 'sqlite') === 'oci') {
			//oracle hack: need to explicitly cast CLOB to CHAR for comparison
			$sql .= 'AND to_char(`configvalue`) < ?';
		} else {
			$sql .= 'AND `configvalue` < ?';
		}

		$result = $this->connection->executeQuery($sql, array($appName, $key, $value));

		$userIDs = array();
		while ($row = $result->fetch()) {
			$userIDs[] = $row['userid'];
		}

		return $userIDs;
	}
}
