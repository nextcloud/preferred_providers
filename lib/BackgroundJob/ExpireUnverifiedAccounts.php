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
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;
use OC\AppFramework\Utility\TimeFactory;
use OC\BackgroundJob\TimedJob;
use OC\SystemConfig;

class ExpireUnverifiedAccounts extends TimedJob {
	use ExpireUserTrait;

	/** @var string */
	private $appName;

	/** @var IUserManager */
	private $userManager;

	/** @var IUserSession */
	private $userSession;

	/** @var IConfig */
	private $config;

	/** @var SystemConfig */
	private $systemConfig;

	/** @var ILogger */
	private $logger;

	/** @var TimeFactory */
	private $timeFactory;

	/** @var IDBConnection */
	private $connection;

	/** @var VerifyMailHelper */
	private $mailHelper;

	public function __construct() {
		// Run once per 15 minutes
		$this->setInterval(15 * 60);
	}

	public function run($argument) {
		$this->appName = 'preferred_providers';
		$this->userManager = \OC::$server->getUserManager();
		$this->userSession = \OC::$server->getUserSession();
		$this->config = \OC::$server->getConfig();
		$this->systemConfig = \OC::$server->getSystemConfig();
		$this->logger = \OC::$server->getLogger();
		$this->timeFactory = new TimeFactory();
		$this->connection = \OC::$server->getDatabaseConnection();

		$this->mailHelper = new VerifyMailHelper(
			$this->appName,
			\OC::$server->getThemingDefaults(),
			\OC::$server->getURLGenerator(),
			\OC::$server->getL10N($this->appName),
			\OC::$server->getMailer(),
			\OC::$server->getSecureRandom(),
			$this->timeFactory,
			$this->config,
			\OC::$server->getCrypto()
		);

		// process if token is 5min old
		$users = $this->getUsersForUserLowerThanValue($this->appName, 'disable_user_after', time());
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

		if ($this->systemConfig->getValue('dbtype', 'sqlite') === 'oci') {
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
