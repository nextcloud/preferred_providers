<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Controller;

use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class MailController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IConfig $config,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
		private readonly IUserManager $userManager,
		private readonly ICrypto $crypto,
		private readonly ITimeFactory $timeFactory,
		private readonly IURLGenerator $urlGenerator,
		private readonly VerifyMailHelper $verifyMailHelper,
		private readonly IGroupManager $groupManager,
		private readonly \OCP\IAppConfig $appConfig,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Process email verification
	 *
	 * @param string $email The email to create an account for
	 * @param string $token The security token
	 * @return RedirectResponse|TemplateResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/login/confirm/{email}/{token}')]
	public function confirmMailAddress(string $email, string $token) {
		// process token validation
		try {
			$this->checkVerifyMailAddressToken($token, $email);
		} catch (\Exception $e) {
			return new TemplateResponse('core', 'error', [
				'errors' => [['error' => $e->getMessage()]]
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
			$this->logger->error("Can't send welcome email to $email", ['exception' => $e]);
		}

		// add/remove user to groups
		$unconfirmedGroups = $this->appConfig->getValue($this->appName, 'provider_groups_unconfirmed', '');
		$confirmedGroups = $this->appConfig->getValue($this->appName, 'provider_groups_confirmed', '');

		if ($unconfirmedGroups !== '') {
			$groupIds = explode(',', $unconfirmedGroups);
			foreach ($groupIds as $groupId) {
				if ($this->groupManager->groupExists($groupId)) {
					$this->groupManager->get($groupId)->removeUser($user);
				}
			}
		}
		if ($confirmedGroups !== '') {
			$groupIds = explode(',', $confirmedGroups);
			foreach ($groupIds as $groupId) {
				if ($this->groupManager->groupExists($groupId)) {
					$this->groupManager->get($groupId)->addUser($user);
				}
			}
		}

		// redirect to home, user should already be logged
		return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
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
	protected function checkVerifyMailAddressToken($token, $userId) {
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled()) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}

		$mailAddress = $user->getEMailAddress() ?? '';

		try {
			$encryptedToken = $this->config->getUserValue($userId, $this->appName, 'verify_token');
			$decryptedToken = $this->crypto->decrypt($encryptedToken, $mailAddress . $this->config->getSystemValue('secret'));
		} catch (\Exception) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}

		$splittedToken = explode(':', $decryptedToken);
		if (count($splittedToken) !== 2) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}

		if ($splittedToken[0] < ($this->timeFactory->getTime() - AccountController::validateEmailDelay)) {
			throw new \Exception($this->l10n->t('The token is expired, please contact your provider'));
		}

		if (!hash_equals($splittedToken[1], $token)) {
			throw new \Exception($this->l10n->t('The token is invalid'));
		}
	}
}
