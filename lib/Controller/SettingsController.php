<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Controller;

use OCA\Preferred_Providers\AppInfo\Application;
use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class SettingsController extends OCSController {

	protected string $appRoot;

	protected string $serverRoot;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IConfig $config,
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
		protected ITimeFactory $timeFactory,
		private readonly IAppManager $appManager,
		private readonly ISecureRandom $secureRandom,
		private readonly IManager $notificationsManager,
		private readonly VerifyMailHelper $verifyMailHelper,
		private readonly \OCP\IAppConfig $appConfig,
	) {
		parent::__construct($appName, $request);

		$this->serverRoot = \OC::$SERVERROOT;
		$this->appRoot = $this->appManager->getAppPath($this->appName);
	}

	/**
	 * Reset the token
	 *
	 * @return DataResponse
	 */
	#[ApiRoute(verb: 'GET', url: '/api/v1/token/new')]
	public function resetToken(): DataResponse {
		$provider_token = md5($this->secureRandom->generate(10));
		$this->appConfig->setValueString(Application::APP_ID, 'provider_token', $provider_token);

		return new DataResponse(['token' => $provider_token]);
	}

	/**
	 * Define the default groups for a new user
	 *
	 * @param array $groups the groups to set
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 */
	#[ApiRoute(verb: 'POST', url: '/api/v1/groups')]
	public function setGroups(array $groups = [], string $for = 'all'): DataResponse {
		foreach ($groups as $groupId) {
			if (!$this->groupManager->groupExists($groupId)) {
				throw new OCSNotFoundException($groupId . ' does not exists');
			}
		}

		if ($for === 'all') {
			$this->appConfig->setValueString(Application::APP_ID, 'provider_groups', implode(',', $groups));
		} elseif ($for === 'confirmed' || $for === 'unconfirmed') {
			$this->appConfig->setValueString(Application::APP_ID, 'provider_groups_' . $for, implode(',', $groups));
		} else {
			throw new OCSBadRequestException();
		}

		return new DataResponse(['groups' => $groups]);
	}

	/**
	 * Enable a user and resend activation email
	 *
	 * @param string $userId the user to reactivate
	 *
	 * @throws OCSNotFoundException
	 * @throws OCSBadRequestException
	 */
	#[ApiRoute(verb: 'POST', url: '/api/v1/reactivate')]
	public function reactivate(string $userId): JSONResponse {
		$user = $this->userManager->get($userId);
		if (!$user) {
			throw new OCSNotFoundException($userId . ' does not exists');
		}

		$email = $user->getEMailAddress();

		if (!$email) {
			throw new OCSBadRequestException($userId . ' does not have an email address');
		}

		// enable if the user was disabled
		if (!$user->isEnabled()) {
			$user->setEnabled(true);
		}

		// an admin reactivation supersedes the self-service flow: drop the
		// auto-disabled flag so it cannot linger and be acted on later
		$this->config->deleteUserValue($userId, $this->appName, 'pp_disabled');

		try {
			// send email without the reset password link
			$emailTemplate = $this->verifyMailHelper->generateTemplate($user);
			$this->verifyMailHelper->sendMail($user, $emailTemplate);
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
			->setObject('verify_email', sha1((string)$email));
		$this->notificationsManager->notify($notification);

		return new JSONResponse(['status' => 'success']);
	}
}
