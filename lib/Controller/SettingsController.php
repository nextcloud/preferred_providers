<?php
declare (strict_types = 1);
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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager;
use OCP\Security\ISecureRandom;
use phpDocumentor\Reflection\Types\Boolean;

class SettingsController extends OCSController {

	/** @var string */
	protected $appName;

	/** @var string */
	protected $serverRoot;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var ILogger */
	private $logger;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ITimeFactory */
	protected $timeFactory;

	/** @var IUserSession */
	private $userSession;

	/** @var IAppManager */
	private $appManager;

	/** @var ISecureRandom */
	private $secureRandom;

	/** @var IManager */
	private $notificationsManager;

	/** @var VerifyMailHelper */
	private $verifyMailHelper;

	/**
	 * Account constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param ILogger $logger
	 * @param IURLGenerator $urlGenerator
	 * @param ITimeFactory $timeFactory
	 * @param IUserSession $userSession
	 * @param IAppManager $appManager
	 * @param ISecureRandom $secureRandom
	 * @param IManager $notificationsManager
	 * @param VerifyMailHelper $verifyMailHelper
	 */
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IUserManager $userManager,
								IGroupManager $groupManager,
								ILogger $logger,
								IURLGenerator $urlGenerator,
								ITimeFactory $timeFactory,
								IUserSession $userSession,
								IAppManager $appManager,
								ISecureRandom $secureRandom,
								IManager $notificationsManager,
								VerifyMailHelper $verifyMailHelper) {
		parent::__construct($appName, $request);
		$this->appName              = $appName;
		$this->config               = $config;
		$this->userManager          = $userManager;
		$this->groupManager         = $groupManager;
		$this->logger               = $logger;
		$this->urlGenerator         = $urlGenerator;
		$this->timeFactory          = $timeFactory;
		$this->userSession          = $userSession;
		$this->appManager           = $appManager;
		$this->secureRandom         = $secureRandom;
		$this->notificationsManager = $notificationsManager;
		$this->verifyMailHelper     = $verifyMailHelper;

		$this->serverRoot = \OC::$SERVERROOT;
		$this->appRoot    = $this->appManager->getAppPath($this->appName);
	}

	/**
	 * Reset the token
	 *
	 * @return DataResponse
	 */
	public function resetToken(): DataResponse {
		$provider_token = md5($this->secureRandom->generate(10));
		$this->config->setAppValue('preferred_providers', 'provider_token', $provider_token);

		return new DataResponse(['token' => $provider_token]);
	}

	/**
	 * Define the default groups for a new user
	 *
	 * @param array $groups the groups to set
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 */
	public function setGroups(array $groups): DataResponse {
		foreach ($groups as $groupId) {
			if (!$this->groupManager->groupExists($groupId)) {
				throw new OCSNotFoundException($groupId . ' does not exists');
			}
		}
		$this->config->setAppValue('preferred_providers', 'provider_groups', implode(',', $groups));

		return new DataResponse(['groups' => $groups]);
	}

	/**
	 * Enable a user and resend activation email
	 *
	 * @param string $userId the user to reactivate
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 * @throws OCSBadRequestException
	 */
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

		try {
			// send email without the reset password link
			$emailTemplate = $this->verifyMailHelper->generateTemplate($user);
			$this->verifyMailHelper->sendMail($user, $emailTemplate);
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => "Can't send new user mail to $email",
				'level'   => \OCP\Util::ERROR,
				'app'     => $this->appName
			]);
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

		return new JSONResponse(['status' => 'success']);
	}

}