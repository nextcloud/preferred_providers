<?php
declare (strict_types = 1);
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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
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
use OCP\Security\ISecureRandom;

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
								ISecureRandom $secureRandom) {
		parent::__construct($appName, $request);
		$this->appName      = $appName;
		$this->config       = $config;
		$this->userManager  = $userManager;
		$this->groupManager = $groupManager;
		$this->logger       = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory  = $timeFactory;
		$this->userSession  = $userSession;
		$this->appManager   = $appManager;
		$this->secureRandom = $secureRandom;

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
	 * @return DataDisplayResponse
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

}