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

use OCA\Preferred_Providers\Controller\AccountController;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Security\ICrypto;

class MailController extends Controller {

	/** @var string */
	protected $appName;
	/** @var IConfig */
	private $config;
	/** @var IL10N */
	private $l10n;
	/** @var IUserManager */
	private $userManager;
	/** @var ICrypto */
	private $crypto;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var VerifyMailHelper */
	private $verifyMailHelper;


	/**
	 * Account constructor.
	 * 
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IL10N $l10n
	 * @param IUserManager $userManager
	 * @param ICrypto $crypto
	 * @param ITimeFactory $timeFactory
	 * @param VerifyMailHelper $verifyMailHelper
	 * 
	 */
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IL10N $l10n,
								IUserManager $userManager,
								ICrypto $crypto,
								ITimeFactory $timeFactory,
								VerifyMailHelper $verifyMailHelper) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->crypto = $crypto;
		$this->timeFactory = $timeFactory;
		$this->verifyMailHelper = $verifyMailHelper;
	}


	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 * 
	 * Process email verification
	 * 
	 * @param string $email The email to create an account for
	 * @param string $token The security token
	 * @return RedirectResponse|TemplateResponse
	 */
	public function confirmMailAddress(string $email, string $token) {
		// process token validation
		try {
			$this->checkVerifyMailAddressToken($token, $email);
		} catch (\Exception $e) {
			return new TemplateResponse('core', 'error', [
				'errors' => array(array('error' => $e->getMessage()))
			], 'guest');
		}

		// remove user deadline & token
		$this->config->deleteUserValue($email, $this->appName, 'disable_user_after');
		$this->config->deleteUserValue($email, $this->appName, 'verify_token');

		// send welcome email
		try {
			$user = $this->userManager->get($email);
			$emailTemplate = $this->verifyMailHelper->generateTemplate($user, true);
			$this->verifyMailHelper->sendMail($user, $emailTemplate);
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => "Can't send welcome email to $email",
				'level' => \OCP\Util::ERROR,
				'app' => $this->appName,
			]);
		}
		
		// redirect to home, user should already be logged
		return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
	}

	/**
	 * Check token authenticity
	 * 
	 * @param string $token
	 * @param string $userId the user mail address / id
	 * @throws \Exception
	 */
	protected function checkVerifyMailAddressToken($token, $userId) {
		$user = $this->userManager->get($userId);
		if($user === null || !$user->isEnabled()) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
		try {
			$encryptedToken = $this->config->getUserValue($userId, $this->appName, 'verify_token');
			$decryptedToken = $this->crypto->decrypt($encryptedToken, $userId.$this->config->getSystemValue('secret'));
		} catch (\Exception $e) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
		$splittedToken = explode(':', $decryptedToken);
		if(count($splittedToken) !== 2) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
		if ($splittedToken[0] < ($this->timeFactory->getTime() - AccountController::validateEmailDelay) ||
			$user->getLastLogin() > $splittedToken[0]) {
			throw new \Exception($this->l10n->t('The token is expired, please contact your provider'));
		}
		if (!hash_equals($splittedToken[1], $token)) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
	}
}
