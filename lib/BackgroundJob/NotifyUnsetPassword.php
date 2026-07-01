<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\BackgroundJob;

use Exception;
use OCA\Preferred_Providers\Mailer\SetPasswordMailHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class NotifyUnsetPassword extends TimedJob {

	/** @var string */
	private $appName;

	/** @var IDBConnection */
	private $connection;

	/** @var IConfig */
	private $config;

	public function __construct(
		ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
		IDBConnection $connection,
		private readonly SetPasswordMailHelper $mailHelper,
		IConfig $config,
	) {
		parent::__construct($timeFactory);
		$this->appName = 'preferred_providers';
		$this->connection = $connection;
		$this->config = $config;

		// Run once per 5 minutes
		$this->setInterval(5 * 60);
	}

	/**
	 * @return void
	 */
	#[\Override]
	public function run($argument) {
		// process if token is 5min old
		$users = $this->getUsersForUserLowerThanValue($this->appName, 'remind_password', strval(time() - 5 * 60));
		foreach ($users as $userId) {
			$emailTemplate = $this->mailHelper->generateTemplate($userId);
			try {
				$this->mailHelper->sendMail($userId, $emailTemplate);
				// only send one mail
				$this->config->deleteUserValue($userId, $this->appName, 'remind_password');
				$this->logger->debug('Password definition mail sent to ' . $userId);
			} catch (Exception) {
				$this->logger->debug('Error while sending the password definition mail to  ' . $userId);
			}
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
