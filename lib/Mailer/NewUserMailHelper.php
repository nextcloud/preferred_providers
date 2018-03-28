<?php
declare(strict_types=1);
/**
 * 
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2018 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Leon Klingele <leon@struktur.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Preferred_Providers\Mailer;

use OCP\Mail\IEMailTemplate;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

class NewUserMailHelper {
	/** @var Defaults */
	private $themingDefaults;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IL10N */
	private $l10n;
	/** @var IMailer */
	private $mailer;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IConfig */
	private $config;
	/** @var ICrypto */
	private $crypto;
	/** @var string */
	private $fromAddress;

	/**
	 * @param Defaults $themingDefaults
	 * @param IURLGenerator $urlGenerator
	 * @param IL10N $l10n
	 * @param IMailer $mailer
	 * @param ISecureRandom $secureRandom
	 * @param ITimeFactory $timeFactory
	 * @param IConfig $config
	 * @param ICrypto $crypto
	 * @param string $fromAddress
	 */
	public function __construct(Defaults $themingDefaults,
								IURLGenerator $urlGenerator,
								IL10N $l10n,
								IMailer $mailer,
								ISecureRandom $secureRandom,
								ITimeFactory $timeFactory,
								IConfig $config,
								ICrypto $crypto,
								$fromAddress) {
		$this->themingDefaults = $themingDefaults;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->mailer = $mailer;
		$this->secureRandom = $secureRandom;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->crypto = $crypto;
		$this->fromAddress = $fromAddress;
	}

	/**
	 * Set the IL10N object
	 *
	 * @param IL10N $l10n
	 */
	public function setL10N(IL10N $l10n) {
		$this->l10n = $l10n;
	}

	/**
	 * @param IUser $user
	 * @return IEMailTemplate
	 */
	public function generateTemplate(IUser $user) {
		$token = $this->secureRandom->generate(
			21,
			ISecureRandom::CHAR_DIGITS .
			ISecureRandom::CHAR_LOWER .
			ISecureRandom::CHAR_UPPER
		);
		$tokenValue = $this->timeFactory->getTime() . ':' . $token;
		$mailAddress = (null !== $user->getEMailAddress()) ? $user->getEMailAddress() : '';
		$encryptedValue = $this->crypto->encrypt($tokenValue, $mailAddress . $this->config->getSystemValue('secret'));
		$this->config->setUserValue($user->getUID(), 'preferred_providers', 'verify_token', $encryptedValue);
		$link = $this->urlGenerator->linkToRouteAbsolute('preferred_providers.MailHelper.confirmMailAddress', ['email' => $mailAddress, 'token' => $token]);

		$displayName = $user->getDisplayName();
		$userId = $user->getUID();

		$emailTemplate = $this->mailer->createEMailTemplate('settings.Welcome', [
			'link' => $link,
			'displayname' => $displayName,
			'userid' => $userId,
			'instancename' => $this->themingDefaults->getName()
		]);

		$emailTemplate->setSubject($this->l10n->t('Your %s account was created', [$this->themingDefaults->getName()]));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($this->l10n->t('Welcome aboard'));
		$emailTemplate->addBodyText($this->l10n->t('Welcome to your %s account, you can add, protect, and share your data.', [$this->themingDefaults->getName()]));
		$emailTemplate->addBodyText($this->l10n->t('To keep using you raccount, you need to verify your email address !'));
		$emailTemplate->addBodyText($this->l10n->t('Your username is: %s', [$userId]));
		$buttonText = $this->l10n->t('Click here to verify your email address');
		$emailTemplate->addBodyButtonGroup(
			$buttonText,
			$link
		);
		$emailTemplate->addFooter();

		return $emailTemplate;
	}

	/**
	 * Sends a welcome mail to $user to ask him to verify his mail address
	 *
	 * @param IUser $user
	 * @param IEmailTemplate $emailTemplate
	 * @throws Exception If mail could not be sent
	 */
	public function sendMail(IUser $user,
							 IEMailTemplate $emailTemplate) {
		$message = $this->mailer->createMessage();
		$message->setTo([$user->getEMailAddress() => $user->getDisplayName()]);
		$message->setFrom([$this->fromAddress => $this->themingDefaults->getName()]);
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}
}
