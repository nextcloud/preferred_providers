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

namespace OCA\Preferred_Providers\Mailer;

use OCP\Mail\IEMailTemplate;
use OCA\Theming\ThemingDefaults;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;

class SetPasswordMailHelper {

	/** @var string */
	protected $appName;
	/** @var ThemingDefaults */
	private $themingDefaults;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IL10N */
	private $l10n;
	/** @var IMailer */
	private $mailer;
	/** @var IConfig */
	private $config;
	/** @var ICrypto */
	private $crypto;

	/**
	 * @param string $appName
	 * @param ThemingDefaults $themingDefaults
	 * @param IURLGenerator $urlGenerator
	 * @param IL10N $l10n
	 * @param IMailer $mailer
	 * @param IConfig $config
	 * @param ICrypto $crypto
	 */
	public function __construct(string $appName,
								ThemingDefaults $themingDefaults,
								IURLGenerator $urlGenerator,
								IL10N $l10n,
								IMailer $mailer,
								IConfig $config,
								ICrypto $crypto) {
		$this->appName = $appName;
		$this->themingDefaults = $themingDefaults;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->mailer = $mailer;
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
	 * @param string $userId
	 * @param bool $verified is the mail verified
	 * @return IEMailTemplate
	 */
	public function generateTemplate(string $userId, bool $verified = false): IEMailTemplate {
		// get token + link
		$token = $this->getToken($userId);
		$link = $this->urlGenerator->linkToRouteAbsolute($this->appName.'.password.set_password', array('email' => $userId, 'token' => $token));

		// generate mail template
		$emailTemplate = $this->mailer->createEMailTemplate('account.SetPassword', [
			'link' => $link,
			'email' => $userId,
			'instancename' => $this->themingDefaults->getName()
		]);

		$emailTemplate->setSubject($this->l10n->t('One more step required', [$this->themingDefaults->getName()]));
		$emailTemplate->addHeader();
		$emailTemplate->addBodyText($this->l10n->t('It looks like you did not create your password yet.'));
		$emailTemplate->addBodyText($this->l10n->t('Did you encountered an issue during the sign-up process?'));
		$emailTemplate->addBodyButton(
			$this->l10n->t('Access your account'),
			$link
		);
		$emailTemplate->addFooter();

		return $emailTemplate;
	}

	/**
	 * Sends a welcome mail to $user to ask him to verify his mail address
	 *
	 * @param string $user
	 * @param IEmailTemplate $emailTemplate
	 * @throws Exception If mail could not be sent
	 */
	public function sendMail(string $userId,
							 IEMailTemplate $emailTemplate) {
		$message = $this->mailer->createMessage();
		$message->setTo([$userId]);
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}

	/**
	 * get password definition token
	 *
	 * @param string $userId the user id
	 * @return string the token
	 */
	private function getToken($userId): string {
		$encryptedToken = $this->config->getUserValue($userId, $this->appName, 'set_password');
		return $this->crypto->decrypt($encryptedToken, $userId.$this->config->getSystemValue('secret'));
	}
}
