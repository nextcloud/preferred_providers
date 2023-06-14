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

namespace OCA\Preferred_Providers\Event;

use OCA\Preferred_Providers\Helper\ExpireUserTrait;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserLoggedInEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class LoginListener implements IEventListener {
	use ExpireUserTrait;

	/** @var string */
	private $appName;

	/** @var IUserManager */
	private $userManager;

	/** @var IUserSession */
	private $userSession;

	/** @var IConfig */
	private $config;

	/** @var LoggerInterface */
	private $logger;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var VerifyMailHelper */
	private $mailHelper;

	/**
	 * @param string $appName
	 * @param IUserManager $userManager
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param LoggerInterface $logger
	 * @param ITimeFactory $timeFactory
	 * @param VerifyMailHelper $mailHelper
	 */
	public function __construct(string $appName,
		IUserManager $userManager,
		IUserSession $userSession,
		IConfig $config,
		LoggerInterface $logger,
		ITimeFactory $timeFactory,
		VerifyMailHelper $mailHelper) {
		$this->appName = $appName;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->config = $config;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
		$this->mailHelper = $mailHelper;
	}

	/**
	 * Handle incoming event from event dispatcher
	 *
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if ($event instanceof BeforeUserLoggedInEvent) {
			$this->verifyUser($event->getUsername());
		}
	}

	/**
	 * Verify if user has validated is email on time
	 *
	 * @param string $userId
	 *
	 * @return void
	 */
	private function verifyUser(string $userId) {
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled()) {
			return;
		}
		$disableTime = $this->config->getUserValue($userId, $this->appName, 'disable_user_after', false);
		// time expired, disabling user
		if ($disableTime !== false && $disableTime < $this->timeFactory->getTime()) {
			$this->expireUser($userId);
		}
	}
}
