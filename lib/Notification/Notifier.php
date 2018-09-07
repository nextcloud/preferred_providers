<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Preferred_Providers\Notification;

use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/** @var string */
	protected $appName;

	/** @var IFactory */
	protected $l10nFactory;

	/** @var IUserManager */
	protected $userManager;

	/** @var IURLGenerator */
	protected $urlGenerator;

	public function __construct(string $appName,
								IFactory $l10nFactory,
								IUserManager $userManager,
								IURLGenerator $urlGenerator) {
		$this->appName      = $appName;
		$this->l10nFactory  = $l10nFactory;
		$this->userManager  = $userManager;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 */
	public function prepare(INotification $notification, $languageCode): INotification {
		if ($notification->getApp() !== $this->appName) {
			throw new \InvalidArgumentException('Incorrect app');
		}

		$l = $this->l10nFactory->get($this->appName, $languageCode);
		$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($this->appName, 'app-dark.svg')));

		$subject = $notification->getSubject();
		if ($subject === 'verify_email') {
			$message = $l->t('A confirmation mail was sent to {email}. Make sure to confirm the account within 6 hours.');
			$notification
				->setParsedSubject($l->t('Please verify your email address'))
				->setParsedMessage(str_replace('{email}', $notification->getUser(), $message))
				->setRichMessage($message, [
					'email' => [
						'type' => 'email',
						'id' => $notification->getUser(),
						'name' => $notification->getUser(),
					]
				]);
			return $notification;
		}
		throw new \InvalidArgumentException('Unknown subject');
	}
}
