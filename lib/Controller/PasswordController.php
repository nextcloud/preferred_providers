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

use OC\Authentication\Token\IProvider;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

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

	/** @var LoggerInterface */
	private $logger;

	/** @var IProvider */
	private $tokenProvider;

	/** @var ISecureRandom */
	private $secureRandom;

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
	 * @param LoggerInterface $logger
	 * @param IProvider $tokenProvider
	 * @param ISecureRandom $secureRandom
	 */
	public function __construct(string $appName,
		IRequest $request,
		IConfig $config,
		IL10N $l10n,
		IUserManager $userManager,
		ICrypto $crypto,
		IURLGenerator $urlGenerator,
		IUserSession $userSession,
		LoggerInterface $logger,
		IProvider $tokenProvider,
		ISecureRandom $secureRandom) {
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
		$this->tokenProvider = $tokenProvider;
		$this->secureRandom = $secureRandom;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * Display password definition template
	 *
	 * @param string $token The security token
	 * @param string $email The user email
	 * @param string $ocsis this a ocs api request
	 * @return TemplateResponse
	 */
	public function setPassword(string $token, string $email, $ocs = false) {
		try {
			$this->checkPasswordToken($token, $email);
		} catch (\Exception $e) {
			return new TemplateResponse('core', 'error', [
				'errors' => [['error' => $e->getMessage()]]
			], 'guest');
		}

		return $this->generateTemplate($token, $email, '', $ocs !== false);
	}

	/**
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 *
	 * shortcut for secondary route with ocs api parameter
	 */
	public function setPasswordOcs(string $token, string $email, $ocs = false): TemplateResponse {
		return $this->setPassword($token, $email, $ocs);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 * @AnonRateThrottle(limit=5, period=1)
	 *
	 * Display password definition template
	 *
	 * @param string $token The security token
	 * @param string $email The user email
	 * @param string $password The user password
	 * @param string $ocsapirequest OCS-APIREQUEST header check
	 * @return TemplateResponse|RedirectResponse
	 */
	public function submitPassword(string $token, string $email, string $password, string $ocsapirequest = '') {
		// process token validation
		try {
			$this->checkPasswordToken($token, $email);
		} catch (\Exception $e) {
			return new TemplateResponse('core', 'error', [
				'errors' => [['error' => $e->getMessage()]]
			], 'guest');
		}

		if (\mb_strlen($password) > 100) {
			return new TemplateResponse('core', 'error', [
				'errors' => [['error' => $this->l10n->t('Password too long')]]
			], 'guest');
		}

		// all clear! set password
		try {
			$user = $this->userManager->get($email);
			if (!$user->setPassword($password)) {
				return $this->generateTemplate($token, $email, $this->l10n->t('Unable to set the password. Contact your provider.'), $ocsapirequest === '1');
			}
			$this->config->deleteUserValue($email, $this->appName, 'set_password');
			$this->config->deleteUserValue($email, $this->appName, 'remind_password');
			// logout and ignore failure
			@\OC::$server->getUserSession()->unsetMagicInCookie();
		} catch (\Exception $e) {
			return $this->generateTemplate($token, $email, $e->getMessage(), $ocsapirequest === '1');
		}

		// redirect to ClientFlowLogin if the request comes from android/ios/desktop
		if ($ocsapirequest === '1') {
			$clientName = $this->getClientName();
			$redirectUri = $this->generateAppPassword($email, $clientName);
			return new RedirectResponse($redirectUri);
		}

		// login
		try {
			$loginResult = $this->userManager->checkPasswordNoLogging($email, $password);
			$this->userSession->completeLogin($loginResult, ['loginName' => $email, 'password' => $password]);
			$this->userSession->createSessionToken($this->request, $loginResult->getUID(), $email, $password);
		} catch (\Exception $e) {
			$this->logger->debug('Unable to perform auto login for ' . $email, ['app' => $this->appName]);
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
	protected function generateTemplate(string $token, string $email, string $error = '', bool $ocs = false) {
		$response = new TemplateResponse(
			$this->appName,
			'password-public',
			[
				'link' => $this->urlGenerator->linkToRoute($this->appName . '.password.submit_password', ['token' => $token]),
				'email' => $email,
				'ocsapirequest' => $this->request->getHeader('OCS-APIREQUEST') || $ocs,
				'error' => $error
			],
			'guest'
		);

		if ($ocs) {
			// We need to set the CSP header to allow the redirect to the Nextcloud client
			// some browsers (e.g. Safari) seems to block the redirect if the CSP header is not set.
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedFormActionDomain('nc://*');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}

	/**
	 * Check token authenticity
	 *
	 * @param string $token
	 * @param string $userId the user mail address / id
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	protected function checkPasswordToken($token, $userId) {
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled()) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}

		try {
			$encryptedToken = $this->config->getUserValue($userId, $this->appName, 'set_password');
			$decryptedToken = $this->crypto->decrypt($encryptedToken, $userId . $this->config->getSystemValue('secret'));
		} catch (\Exception $e) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}

		if (!hash_equals($decryptedToken, $token)) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
	}

	/**
	 * @return string
	 */
	private function getClientName() {
		$userAgent = $this->request->getHeader('USER_AGENT');

		return $userAgent !== '' ? $userAgent : 'unknown';
	}

	/**
	 * generate application password and return nc protocol formatted url
	 *
	 * @param string $email the user email/userId
	 * @param string $clientName the user agent
	 * @return string
	 */
	protected function generateAppPassword(string $email, string $clientName) {

		// generate token
		$token = $this->secureRandom->generate(72, ISecureRandom::CHAR_HUMAN_READABLE);
		$this->tokenProvider->generateToken($token, $email, $email, null, $clientName);

		$serverPostfix = '';

		if (strpos($this->request->getRequestUri(), '/index.php') !== false) {
			$serverPostfix = substr($this->request->getRequestUri(), 0, strpos($this->request->getRequestUri(), '/index.php'));
		} elseif (strpos($this->request->getRequestUri(), '/login/flow') !== false) {
			$serverPostfix = substr($this->request->getRequestUri(), 0, strpos($this->request->getRequestUri(), '/login/flow'));
		}

		$protocol = $this->request->getServerProtocol();

		if ($protocol !== 'https') {
			$xForwardedProto = $this->request->getHeader('X-Forwarded-Proto');
			$xForwardedSSL = $this->request->getHeader('X-Forwarded-Ssl');
			if ($xForwardedProto === 'https' || $xForwardedSSL === 'on') {
				$protocol = 'https';
			}
		}

		$serverPath = $protocol . '://' . $this->request->getServerHost() . $serverPostfix;
		$redirectUri = 'nc://login/server:' . $serverPath . '&user:' . urlencode($email) . '&password:' . urlencode($token);

		return $redirectUri;
	}
}
