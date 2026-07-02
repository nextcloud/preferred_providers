<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-FileCopyrightText: 2024 Seyed Masih Sajadi <smasihsajadi@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Helper;

use Exception;
use OCA\Preferred_Providers\AppInfo\Application;

trait ExpireUserTrait {

	/**
	 * Expire a user
	 *
	 * @param string $userId
	 *
	 * @return void
	 */
	private function expireUser(string $userId) {
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled()) {
			return;
		}

		// disabling
		$user->setEnabled(false);
		// flag the account as auto-disabled by this app, so it can be told apart
		// from admin-disabled accounts and offered self-service reactivation
		$this->config->setUserValue($userId, $this->appName, 'pp_disabled', '1');

		// removing token to avoid conflict with further manual manipulation of the user
		$this->config->deleteUserValue($userId, Application::APP_ID, 'disable_user_after');
		$this->logger->info('User ' . $userId . ' failed to verify email in time and has been disabled');

		// send email
		if ($user->getEMailAddress() !== '' && $user->getEMailAddress() !== null) {
			$emailTemplate = $this->mailHelper->generateTemplate($user, false, true);
			try {
				$this->mailHelper->sendMail($user, $emailTemplate);
				// only send one mail
				$this->config->deleteUserValue($userId, Application::APP_ID, 'remind_password');
				$this->logger->debug('Unverified warning mail sent to ' . $userId);
			} catch (Exception) {
				$this->logger->error('Error while sending the failed to verify warning mail to  ' . $userId);
			}
		} else {
			// Should not happend
			$this->logger->error('Failed to verify warning mail COULD NOT BE sent to ' . $userId . '. No email address is set.');
		}
	}
}
