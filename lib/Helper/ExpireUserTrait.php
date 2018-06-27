<?php
declare (strict_types = 1);
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

namespace OCA\Preferred_Providers\Helper;

trait ExpireUserTrait {

	/**
	 * Expire a user
	 *
	 * @param string $userId
	 */
	private function expireUser(string $userId) {
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled()) {
			return;
		}

		// disabling
		$user->setEnabled(false);

		// removing token to avoid conflict with further manual manipulation of the user
		$this->config->deleteUserValue($userId, $this->appName, 'disable_user_after');
		$this->logger->debug('User ' . $userId . ' failed to verify email in time and has been disabled', ['app' => $this->appName]);

		// send email
		if ($user->getEMailAddress() !== '' && $user->getEMailAddress() !== null) {
			$emailTemplate = $this->mailHelper->generateTemplate($user, false, true);
			try {
				$this->mailHelper->sendMail($user, $emailTemplate);
				// only send one mail
				$this->config->deleteUserValue($userId, $this->appName, 'remind_password');
				$this->logger->debug('Failed to verify warning mail sent to ' . $userId, ['app' => $this->appName]);
			} catch (Exception $e) {
				$this->logger->debug('Error while sending the failed to verify warning mail to  ' . $userId, ['app' => $this->appName]);
			}
		} else {
			// Should not happend
			$this->logger->debug('Failed to verify warning mail COULD NOT BE sent to ' . $userId . '. No email address is set.', ['app' => $this->appName]);
		}
	}

}

?>