<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Preferred_Providers\Hook;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;

class LoginHook {

	/** @var string */
	private $appName;
	/** @var IUserManager */
	private $userManager;
	/** @var IUserSession */
	private $userSession;
	/** @var IConfig */
	private $config;
	/** @var ILogger */
	private $logger;
	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * @param string $appName
	 * @param IUserManager $userManager
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param ILogger $logger
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(string $appName,
								IUserManager $userManager,
								IUserSession $userSession,
								IConfig $config,
								ILogger $logger,
								ITimeFactory $timeFactory){
		$this->appName = $appName;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->config = $config;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Register preLogin hook
	 */
	public function register() {
		$this->userSession->listen('\OC\User', 'preLogin', function($userId) {
			$this->verifyUser($userId);
		});
	}

	/**
	 * Verify if user has validated is email on time
	 * 
	 * @param string $userId
	 */
	private function verifyUser(string $userId) {
		$user = $this->userManager->get($userId);
		if($user === null || !$user->isEnabled()) {
			return;
		}
		$disableTime = $this->config->getUserValue($userId, $this->appName, 'disable_user_after', false);
		// time expired, disabling user
		if ($disableTime !== false && $disableTime < $this->timeFactory->getTime()) {
			$user->setEnabled(false);
			// removing token to avoid conflict with further manual manipulation of the user
			$this->config->deleteUserValue($email, $this->appName, 'disable_user_after');
			$this->logger->debug('User ' . $userId . ' failed to verify email in time and has been disabled', ['app' => $this->appName]);
		}
	}

}
