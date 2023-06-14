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

namespace OCA\Preferred_Providers\Controller;

use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class AccountController extends ApiController {

	/* delay for the user to confirm his email */
	public const validateEmailDelay = (6 * 60 * 60);

	/** @var string */
	protected $appName;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IMailer */
	private $mailer;

	/** @var VerifyMailHelper */
	private $verifyMailHelper;

	/** @var LoggerInterface */
	private $logger;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var ICrypto */
	private $crypto;

	/** @var ISecureRandom */
	private $secureRandom;

	/** @var IManager */
	private $notificationsManager;

	/**
	 * Account constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IMailer $mailer
	 * @param VerifyMailHelper $verifyMailHelper
	 * @param LoggerInterface $logger
	 * @param IURLGenerator $urlGenerator
	 * @param ITimeFactory $timeFactory
	 * @param ICrypto $crypto
	 * @param ISecureRandom $secureRandom
	 * @param IManager $notificationsManager
	 */
	public function __construct(string $appName,
		IRequest $request,
		IConfig $config,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IMailer $mailer,
		VerifyMailHelper $verifyMailHelper,
		LoggerInterface $logger,
		IURLGenerator $urlGenerator,
		ITimeFactory $timeFactory,
		ICrypto $crypto,
		ISecureRandom $secureRandom,
		IManager $notificationsManager) {
		parent::__construct($appName, $request, 'POST');
		$this->appName = $appName;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->mailer = $mailer;
		$this->verifyMailHelper = $verifyMailHelper;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory = $timeFactory;
		$this->crypto = $crypto;
		$this->secureRandom = $secureRandom;
		$this->notificationsManager = $notificationsManager;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 *
	 * @CORS
	 *
	 * @param string $token The security token required
	 * @param string $email The email to create an account for
	 *
	 * @return DataResponse the app password for the user
	 *
	 * @throws OCSForbiddenException
	 */
	public function requestAccount(string $token = '', string $email = ''): DataResponse {
		// checking if valid token
		$provider_token = $this->config->getAppValue($this->appName, 'provider_token');
		if ($provider_token === '' || $provider_token !== $token) {
			return new DataResponse(['data' => ['message' => 'invalid token']], Http::STATUS_UNAUTHORIZED);
		}

		// checking if valid mail address
		if (!$this->mailer->validateMailAddress($email)) {
			return new DataResponse(['data' => ['message' => 'invalid mail address']], Http::STATUS_BAD_REQUEST);
		}

		// checking if user already exists
		if ($this->userManager->userExists($email)) {
			return new DataResponse(['data' => ['message' => 'user already exists']], Http::STATUS_BAD_REQUEST);
		}

		// NOW WE BEGIN!
		try {
			// create user
			$password = $this->generatePassword();
			$newUser = $this->userManager->createUser($email, $password);

			// add user to groups
			$groups = $this->config->getAppValue($this->appName, 'provider_groups', '');
			$unconfirmedGroups = $this->config->getAppValue($this->appName, 'provider_groups_unconfirmed', '');
			
			if (!empty($groups)) {
				$groupIds = array_merge(explode(',', $groups), explode(',', $unconfirmedGroups));
				foreach ($groupIds as $groupId) {
					if ($this->groupManager->groupExists($groupId)) {
						$this->groupManager->get($groupId)->addUser($newUser);
					}
				}
			}

			// set expire delay
			$this->config->setUserValue($email, $this->appName, 'disable_user_after', $this->timeFactory->getTime() + $this::validateEmailDelay);
			$this->logger->info('New account requested for: ' . $email . '. Groups: ' . $groups);
		} catch (\Exception $e) {
			$this->logger->error('Failed addUser attempt with exception.', ['exception' => $e]);

			return new DataResponse(['data' => ['message' => 'error creating the user']], Http::STATUS_BAD_REQUEST);
		}

		// send confirmation mail
		$newUser->setSystemEMailAddress($email);

		try {
			// send email without the reset password link
			$emailTemplate = $this->verifyMailHelper->generateTemplate($newUser);
			$this->verifyMailHelper->sendMail($newUser, $emailTemplate);
		} catch (\Exception $e) {
			$this->logger->error("Can't send new user mail to $email", ['exception' => $e]);
			// continue anyway. Let's only warn the admin log
			// return new DataResponse(['data' => ['message' => 'error sending the invitation mail']], Http::STATUS_BAD_REQUEST);
		}

		// generate a notification
		$notification = $this->notificationsManager->createNotification();
		$notification->setApp($this->appName)
			->setUser($email)
			->setDateTime(new \DateTime())
			->setSubject('verify_email')
			->setObject('verify_email', sha1($email));
		$this->notificationsManager->notify($notification);

		// generate set password token
		try {
			$setPasswordUrl = $this->processSetPasswordToken($email);
		} catch (\Exception $e) {
			$this->logger->error("An error occured during the password token generation for $email", ['exception' => $e]);

			return new DataResponse(['data' => ['message' => 'error generating the password token']], Http::STATUS_BAD_REQUEST);
		}

		// generate app-password

		return new DataResponse(['data' => ['setPassword' => $setPasswordUrl]], Http::STATUS_CREATED);
	}

	/**
	 * Generate token and process it
	 *
	 * @param string $email mail address
	 * @return string reset password url
	 */
	private function processSetPasswordToken(string $email): string {
		$token = $this->generateRandomToken();
		$encryptedValue = $this->crypto->encrypt($token, $email . $this->config->getSystemValue('secret'));
		$this->config->setUserValue($email, $this->appName, 'set_password', $encryptedValue);
		$this->config->setUserValue($email, $this->appName, 'remind_password', strval(time()));

		return $this->urlGenerator->linkToRouteAbsolute($this->appName . '.password.set_password', array('email' => $email, 'token' => $token));
	}

	/**
	 * Generate a random password of 20 random chars
	 *
	 * @return string
	 */
	private function generatePassword(): string {
		return $this->secureRandom->generate(20, ISecureRandom::CHAR_HUMAN_READABLE . ISecureRandom::CHAR_SYMBOLS);
	}

	/**
	 * Return a 21 char password
	 *
	 * @return string
	 */
	private function generateRandomToken(): string {
		return $this->secureRandom->generate(
			21,
			ISecureRandom::CHAR_DIGITS .
			ISecureRandom::CHAR_LOWER .
			ISecureRandom::CHAR_UPPER
		);
	}
}
