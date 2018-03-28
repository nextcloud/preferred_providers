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

namespace OCA\Preferred_Providers\Controller;

use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;

use OCA\Preferred_Providers\Mailer\NewUserMailHelper;

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;


class AccountController extends ApiController {

	/* delay for the user to confirm his email */
	const validateEmailDelay = (6 * 60 * 60);

	/** @var string */
	protected $appName;
	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var IProvider */
	private $tokenProvider;
	/** @var IMailer */
	private $mailer;
	/** @var NewUserMailHelper */
	private $newUserMailHelper;
	/** @var ILogger */
	private $logger;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var ICrypto */
	private $crypto;

	/**
	 * Account constructor.
	 * 
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param ISecureRandom $secureRandom
	 * @param IProvider $tokenProvider
	 * @param IMailer $mailer
	 * @param NewUserMailHelper $newUserMailHelper
	 * @param ILogger $logger
	 * @param IURLGenerator $urlGenerator
	 * @param ITimeFactory $timeFactory
	 * @param ICrypto $crypto
	 */
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IUserManager $userManager,
								ISecureRandom $secureRandom,
								IProvider $tokenProvider,
								IMailer $mailer,
								NewUserMailHelper $newUserMailHelper,
								ILogger $logger,
								IURLGenerator $urlGenerator,
								ITimeFactory $timeFactory,
								ICrypto $crypto){
		parent::__construct($appName, $request, 'POST');
		$this->appName = $appName;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;
		$this->tokenProvider = $tokenProvider;
		$this->mailer = $mailer;
		$this->newUserMailHelper = $newUserMailHelper;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory = $timeFactory;
		$this->crypto = $crypto;
	}

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
	 * 
	 * @param string $token The security token required
	 * @param string $email The email to create an account for
	 * @return string the app password for the user
	 * @throws OCSForbiddenException
     */
	public function requestAccount(string $token = '', string $email = '') {
		// checking if valid token
		$provider_token = $this->config->getAppValue($this->appName, 'provider_token', false);
		if (!$provider_token || $provider_token !== $token) {
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
		// create user
		try {
			$password = $this->generatePassword();
			$newUser = $this->userManager->createUser($email, $password);
			$this->config->setUserValue($email, $this->appName, 'disable_user_after', $this->timeFactory->getTime() + $this::validateEmailDelay);
			$this->logger->info('New account requested for: ' . $email, ['app' => $this->appName]);
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => 'Failed addUser attempt with exception.',
				'level' => \OCP\Util::ERROR,
				'app' => $this->appName,
			]);
			return new DataResponse(['data' => ['message' => 'error creating the user']], Http::STATUS_BAD_REQUEST);
		}

		// send confirmation mail
		$newUser->setEMailAddress($email);
		try {
			// send email without the reset password link
			$emailTemplate = $this->newUserMailHelper->generateTemplate($newUser);
			$this->newUserMailHelper->sendMail($newUser, $emailTemplate);
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => "Can't send new user mail to $email",
				'level' => \OCP\Util::ERROR,
				'app' => $this->appName,
			]);
			return new DataResponse(['data' => ['message' => 'error sending the invitation mail']], Http::STATUS_BAD_REQUEST);
		}

		// generate reset password token
		try {
			$lostPassUrl = $this->processLostPasswordToken($email);
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => "An error occured during the token generation for $email",
				'level' => \OCP\Util::ERROR,
				'app' => $this->appName,
			]);
			return new DataResponse(['data' => ['message' => 'error generating the user token']], Http::STATUS_BAD_REQUEST);
		}

		// generate app-password
		return new DataResponse(['data' => ['lostPass' => $lostPassUrl]], Http::STATUS_CREATED);
	}

    /**
     * Generate token and process it
	 * 
	 * @param string $email mail address
	 * @return string reset password url
	 */
	private function processLostPasswordToken(string $email): string {
		$token = $this->generateRandomToken();
		$tokenValue = $this->timeFactory->getTime() . ':' . $token;
		$encryptedValue = $this->crypto->encrypt($tokenValue, $email . $this->config->getSystemValue('secret'));
		$this->config->setUserValue($email, 'core', 'lostpassword', $encryptedValue);
		return $this->urlGenerator->linkToRouteAbsolute('core.lost.resetform', ['userId' => $email, 'token' => $token]);
	}

    /**
     * Generate a random password of 30 random chars
	 */
	private function processDeviceToken(string $email) {
		$token = $this->generateRandomDeviceToken();
		$this->tokenProvider->generateToken($token, $email, $email, null, $this->appName);
		return $token;

	}

    /**
     * Generate a random password of 30 random chars
	 * 
	 * @return string
	 */
	private function generatePassword(): string {
		return $this->secureRandom->generate(20, ISecureRandom::CHAR_HUMAN_READABLE.ISecureRandom::CHAR_SYMBOLS);
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

	/**
	 * Return a 25 digit device password e.g. AbCdE-fGhJk-MnPqR-sTwXy-23456
	 * 
	 * @return string
	 */
	private function generateRandomDeviceToken(): string {
		$groups = [];
		for ($i = 0; $i < 5; $i++) {
			$groups[] = $this->secureRandom->generate(5, ISecureRandom::CHAR_HUMAN_READABLE);
		}
		return implode('-', $groups);
	}

}
