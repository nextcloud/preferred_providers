<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Joas Schilling <coding@schilljs.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		$this->appName = $appName;
		$this->l10nFactory = $l10nFactory;
		$this->userManager = $userManager;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	#[\Override]
	public function getID(): string {
		return $this->appName;
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	#[\Override]
	public function getName(): string {
		return $this->l10nFactory->get($this->appName)->t('Simple signup');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 */
	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
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
