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
use OCA\Theming\ThemingDefaults;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

class VerifyMailHelper {

	/** @var string */
	protected $appName;
	/** @var ThemingDefaults */
	private $themingThemingDefaults;
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

	/**
	 * @param string $appName
	 * @param ThemingDefaults $themingThemingDefaults
	 * @param IURLGenerator $urlGenerator
	 * @param IL10N $l10n
	 * @param IMailer $mailer
	 * @param ISecureRandom $secureRandom
	 * @param ITimeFactory $timeFactory
	 * @param IConfig $config
	 * @param ICrypto $crypto
	 */
	public function __construct(string $appName,
								ThemingDefaults $themingThemingDefaults,
								IURLGenerator $urlGenerator,
								IL10N $l10n,
								IMailer $mailer,
								ISecureRandom $secureRandom,
								ITimeFactory $timeFactory,
								IConfig $config,
								ICrypto $crypto) {
		$this->appName = $appName;
		$this->themingThemingDefaults = $themingThemingDefaults;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->mailer = $mailer;
		$this->secureRandom = $secureRandom;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->crypto = $crypto;
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
	 * Generate token and mail template
	 * 
	 * @param IUser $user
	 * @param bool $verified is the mail verified
	 * @return IEMailTemplate
	 */
	public function generateTemplate(IUser $user, bool $verified = false): IEMailTemplate {
		// set base data
		$userId = $user->getUID();

		// generate token + link
		$token = $this->generateVerifyToken($user);
		$link = $verified ? $this->urlGenerator->getAbsoluteURL('/') :
			$this->urlGenerator->linkToRouteAbsolute($this->appName.'.mail.confirm_mail_address', ['email' => $userId, 'token' => $token]);

		// generate mail template
		$emailTemplate = $this->mailer->createEMailTemplate('account.Welcome', [
			'link' => $link,
			'email' => $userId,
			'instancename' => $this->themingThemingDefaults->getName()
		]);

		if ($verified) {
			$emailTemplate = $this->generateVerifiedMailBody($emailTemplate, $userId, $link);
		} else {
			$emailTemplate = $this->generateNonVerifiedMailBody($emailTemplate, $userId, $link);
		}

		return $emailTemplate;
	}

	/**
	 * Generate mail body for the welcome mail
	 */
	protected function generateVerifiedMailBody(IEMailTemplate $emailTemplate, string $userId, string $link): IEMailTemplate {
		$emailTemplate->setSubject($this->l10n->t('Welcome to your %s account', [$this->themingThemingDefaults->getName()]));
		$emailTemplate->addHeader();
		$emailTemplate->addBodyText($this->l10n->t('Your %s account %s is now verified!', [$this->themingThemingDefaults->getName(), $userId]));
		$leftButtonText = $this->l10n->t('Start using %s', [$this->themingThemingDefaults->getName()]);
		$emailTemplate->addBodyButtonGroup(
			$leftButtonText,
			$link,
			$this->l10n->t('Install mobile or desktop client'),
			'https://nextcloud.com/install/#install-clients'
		);
		$emailTemplate->addFooter();
		return $emailTemplate;
	}

	/**
	 * Generate mail body for the verification mail
	 */
	protected function generateNonVerifiedMailBody(IEMailTemplate $emailTemplate, string $userId, string $link): IEMailTemplate {
		$emailTemplate->setSubject($this->l10n->t('Verify your %s account', [$this->themingThemingDefaults->getName()]));
		$emailTemplate->addHeader();
		$emailTemplate->addBodyText($this->l10n->t('Just one step left to complete your account setup.'));
		$buttonText = $this->l10n->t('Click here to verify your email address');
		$emailTemplate->addBodyButton(
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
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}

	/**
	 * Generate and save the verify mail token
	 *
	 * @param IUser $user
	 * @return string the token
	 */
	private function generateVerifyToken(IUser $user): string {
		$token = $this->secureRandom->generate(
			21,
			ISecureRandom::CHAR_DIGITS .
			ISecureRandom::CHAR_LOWER .
			ISecureRandom::CHAR_UPPER
		);
		$tokenValue = $this->timeFactory->getTime() . ':' . $token;
		$mailAddress = (null !== $user->getEMailAddress()) ? $user->getEMailAddress() : '';
		$encryptedValue = $this->crypto->encrypt($tokenValue, $mailAddress . $this->config->getSystemValue('secret'));
		$this->config->setUserValue($user->getUID(), $this->appName, 'verify_token', $encryptedValue);
		return $token;
	}
}
