<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Controller;

use OC\Authentication\Token\IProvider;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
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

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IConfig $config,
		private readonly IL10N $l10n,
		private readonly IUserManager $userManager,
		private readonly ICrypto $crypto,
		private readonly IURLGenerator $urlGenerator,
		private readonly IUserSession $userSession,
		private readonly LoggerInterface $logger,
		private readonly IProvider $tokenProvider,
		private readonly ISecureRandom $secureRandom,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Display password definition template
	 *
	 * @param string $token The security token
	 * @param string $email The user email
	 * @param string $ocs is this a ocs api request
	 * @return TemplateResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/password/set/{email}/{token}')]
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
	 * shortcut for secondary route with flow parameter
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/password/set/{email}/{token}/flow/{flow}')]
	public function setPasswordFlow(string $token, string $email, string $flow = ''): TemplateResponse {
		try {
			$this->checkPasswordToken($token, $email);
		} catch (\Exception $e) {
			return new TemplateResponse('core', 'error', [
				'errors' => [['error' => $e->getMessage()]]
			], 'guest');
		}

		return $this->generateTemplate($token, $email, '', false, $flow);
	}

	/**
	 * shortcut for secondary route with ocs api parameter
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/password/set/{email}/{token}/{ocs}')]
	public function setPasswordOcs(string $token, string $email, $ocs = false): TemplateResponse {
		return $this->setPassword($token, $email, $ocs);
	}

	/**
	 * Display password definition template
	 *
	 * @param string $token The security token
	 * @param string $email The user email
	 * @param string $password The user password
	 * @param string $ocsapirequest OCS-APIREQUEST header check
	 * @param string $flow registration flow variant
	 * @return TemplateResponse|RedirectResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[AnonRateLimit(limit: 5, period: 1)]
	#[FrontpageRoute(verb: 'POST', url: '/password/submit/{token}')]
	public function submitPassword(string $token, string $email, string $password, string $ocsapirequest = '', string $flow = '') {
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
				return $this->generateTemplate($token, $email, $this->l10n->t('Unable to set the password. Contact your provider.'), $ocsapirequest === '1', $flow);
			}
			$this->config->deleteUserValue($email, $this->appName, 'set_password');
			$this->config->deleteUserValue($email, $this->appName, 'remind_password');
			// logout and ignore failure
			@\OCP\Server::get(\OCP\IUserSession::class)->unsetMagicInCookie();
		} catch (\Exception $e) {
			return $this->generateTemplate($token, $email, $e->getMessage(), $ocsapirequest === '1', $flow);
		}

		if ($flow === 'V3') {
			$this->loginUser($email, $password);
			return $this->generateFlowLoginResponse();
		}

		// redirect to ClientFlowLogin if the request comes from android/ios/desktop
		if ($ocsapirequest === '1') {
			$clientName = $this->getClientName();
			$redirectUri = $this->generateAppPassword($email, $clientName);
			return new RedirectResponse($redirectUri);
		}

		// login
		$this->loginUser($email, $password);

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
	protected function generateTemplate(string $token, string $email, string $error = '', bool $ocs = false, string $flow = '') {
		$ocsapirequest = $flow === '' && ($this->request->getHeader('OCS-APIREQUEST') || $ocs) ? '1' : '';
		$response = new TemplateResponse(
			$this->appName,
			'password-public',
			[
				'link' => $this->urlGenerator->linkToRoute($this->appName . '.password.submitpassword', ['token' => $token]),
				'email' => $email,
				'ocsapirequest' => $ocsapirequest,
				'flow' => $flow,
				'error' => $error
			],
			'guest'
		);

		if ($ocsapirequest !== '') {
			// We need to set the CSP header to allow the redirect to the Nextcloud client
			// some browsers (e.g. Safari) seems to block the redirect if the CSP header is not set.
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedFormActionDomain('nc://*');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}

	/**
	 * @return TemplateResponse
	 */
	protected function generateFlowLoginResponse() {
		$response = new TemplateResponse(
			$this->appName,
			'flow-login',
			[
				'ncLoginUrl' => $this->generateServerLoginUrl(),
				'redirectUrl' => $this->urlGenerator->getAbsoluteURL('/'),
			],
			'guest'
		);

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFormActionDomain('nc://*');
		$response->setContentSecurityPolicy($csp);

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
		} catch (\Exception) {
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
	 * @param string $email the user email/userId
	 * @param string $password the user password
	 *
	 * @return void
	 */
	private function loginUser(string $email, string $password): void {
		try {
			$loginResult = $this->userManager->checkPasswordNoLogging($email, $password);
			$this->userSession->completeLogin($loginResult, ['loginName' => $email, 'password' => $password]);
			$this->userSession->createSessionToken($this->request, $loginResult->getUID(), $email, $password);
		} catch (\Exception) {
			$this->logger->debug('Unable to perform auto login for ' . $email, ['app' => $this->appName]);
		}
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

		$serverPath = $this->getServerPath();
		$redirectUri = 'nc://login/server:' . $serverPath . '&user:' . urlencode($email) . '&password:' . urlencode($token);

		return $redirectUri;
	}

	/**
	 * generate server-only nc protocol formatted url
	 *
	 * @return string
	 */
	protected function generateServerLoginUrl() {
		return 'nc://login/server:' . rtrim($this->urlGenerator->getBaseUrl(), '/');
	}

	/**
	 * @return string
	 */
	private function getServerPath() {
		$serverPostfix = '';

		if (str_contains((string)$this->request->getRequestUri(), '/index.php')) {
			$serverPostfix = substr((string)$this->request->getRequestUri(), 0, strpos((string)$this->request->getRequestUri(), '/index.php'));
		} elseif (str_contains((string)$this->request->getRequestUri(), '/login/flow')) {
			$serverPostfix = substr((string)$this->request->getRequestUri(), 0, strpos((string)$this->request->getRequestUri(), '/login/flow'));
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
		return $serverPath;
	}
}
