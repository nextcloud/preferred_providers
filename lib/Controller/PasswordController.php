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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ICrypto;

class PasswordController extends Controller {

	/** @var string */
	protected $appName;
	/** @var IRequest */
	protected $request;
	/** @var IConfig */
	private $config;
	/** @var IL10N */
	private $l10n;
	/** @var IUserManager */
	private $userManager;
	/** @var ICrypto */
	private $crypto;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserSession */
	private $userSession;
	/** @var ILogger */
	private $logger;

	/**
	 * Account constructor.
	 * 
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IL10N $l10n
	 * @param IUserManager $userManager
	 * @param ICrypto $crypto
	 * @param IURLGenerator $urlGenerator
	 * @param IUserSession $userSession
	 * @param ILogger $logger
	 */
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IL10N $l10n,
								IUserManager $userManager,
								ICrypto $crypto,
								IURLGenerator $urlGenerator,
								IUserSession $userSession,
								ILogger $logger) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->request = $request;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->crypto = $crypto;
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}


	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 * 
	 * Display password definition template
	 * 
	 * @param string $token The security token
	 * @param string $email The user email
	 * @return TemplateResponse
	 */
	public function setPassword(string $token, string $email) {
		try {
			$this->checkPasswordToken($token, $email);
		} catch (\Exception $e) {
			return new TemplateResponse('core', 'error', [
				'errors' => array(array('error' => $e->getMessage()))
			], 'guest');
		}
		return $this->generateTemplate($token, $email);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 * 
	 * Display password definition template
	 * 
	 * @param string $token The security token
	 * @param string $email The user email
	 * @param string $password The user password
	 * @param string $passwordConfirm The user password confirmation
	 * @param string $ocsapirequest OCS-APIREQUEST header check
	 * @return TemplateResponse|RedirectResponse
	 */
	public function submitPassword(string $token, string $email, string $password, string $passwordConfirm, string $ocsapirequest = '') {
		// checking if passwords match
		if ($password !== $passwordConfirm) {
			return $this->generateTemplate($token, $email, $this->l10n->t('Password does not match the confirm password'));
		}

		// process token validation
		try {
			$this->checkPasswordToken($token, $email);
		} catch (\Exception $e) {
			return new TemplateResponse('core', 'error', [
				'errors' => array(array('error' => $e->getMessage()))
			], 'guest');
		}

		// all clear! set password
		try {
			$user = $this->userManager->get($email);
			if (!$user->setPassword($password)) {
				return $this->generateTemplate($token, $email, $this->l10n->t('Unable to set the password. Contact your provider.'));
			}
			//$this->config->deleteUserValue($email, $this->appName, 'set_password');
			// logout and ignore failure
			@\OC::$server->getUserSession()->unsetMagicInCookie();
		} catch (\Exception $e) {
			return $this->generateTemplate($token, $email, $e->getMessage());
		}

		// login
		try {
			$loginResult = $this->userManager->checkPasswordNoLogging($email, $password);
			$this->userSession->completeLogin($loginResult, ['loginName' => $email, 'password' => $password]);
			$this->userSession->createSessionToken($this->request, $loginResult->getUID(), $email, $password);	
		} catch (\Exception $e) {
			$this->logger->debug('Unable to perform auto login for ' . $email, ['app' => $this->appName]);
		}

		// redirect to ClientFlowLogin if the request comes from android/ios/desktop
		if ($ocsapirequest === 'true') {
			return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute('core.ClientFlowLogin.showAuthPickerPage'));
		}

		return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
		
	}


	/**
	 * Generate template
	 * 
	 * @param string $token The security token
	 * @param string $email The user email
	 * @param string $error optional
	 * @return TemplateResponse
	 */
	protected function generateTemplate(string $token, string $email, string $error = '') {
		return new TemplateResponse(
			$this->appName,
			'password-public',
			array(
				'link' => $this->urlGenerator->linkToRouteAbsolute($this->appName.'.password.submit_password', array('token' => $token)),
				'email' => $email,
				'ocsapirequest' => $this->request->getHeader('OCS-APIREQUEST'),
				'error' => $error
			),
			'guest'
		);
	}

	/**
	 * Check token authenticity
	 * 
	 * @param string $token
	 * @param string $userId the user mail address / id
	 * @throws \Exception
	 */
	protected function checkPasswordToken($token, $userId) {
		$user = $this->userManager->get($userId);
		if($user === null || !$user->isEnabled()) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
		try {
			$encryptedToken = $this->config->getUserValue($userId, $this->appName, 'set_password');
			$decryptedToken = $this->crypto->decrypt($encryptedToken, $userId.$this->config->getSystemValue('secret'));
		} catch (\Exception $e) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
		if (!hash_equals($decryptedToken, $token)) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
	}
}
