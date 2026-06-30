<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	/** @var IUserManager */
	private $userManager;

	/** @var IUserSession */
	private $userSession;

	/** @var IConfig */
	private $config;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * @param string $appName
	 * @param IUserManager $userManager
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param LoggerInterface $logger
	 * @param ITimeFactory $timeFactory
	 * @param VerifyMailHelper $mailHelper
	 */
	public function __construct(
		private string $appName,
		IUserManager $userManager,
		IUserSession $userSession,
		IConfig $config,
		private LoggerInterface $logger,
		ITimeFactory $timeFactory,
		private VerifyMailHelper $mailHelper,
	) {
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->config = $config;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Handle incoming event from event dispatcher
	 *
	 * @param Event $event
	 */
	#[\Override]
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
