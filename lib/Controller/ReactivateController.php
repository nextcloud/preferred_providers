<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Seyed Masih Sajadi <smasihsajadi@gmail.com>
 *
 * @author Seyed Masih Sajadi <smasihsajadi@gmail.com>
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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ReactivateController extends Controller {

	/** @var string */
	protected $appName;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var VerifyMailHelper */
	private $verifyMailHelper;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(string $appName,
		IRequest $request,
		IConfig $config,
		IUserManager $userManager,
		ITimeFactory $timeFactory,
		VerifyMailHelper $verifyMailHelper,
		LoggerInterface $logger) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->timeFactory = $timeFactory;
		$this->verifyMailHelper = $verifyMailHelper;
		$this->logger = $logger;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function showForm(): TemplateResponse {
		return new TemplateResponse($this->appName, 'reactivate-public', ['status' => 'form'], 'guest');
	}

	/**
	 * @PublicPage
	 */
	public function requestReactivation(string $email = ''): TemplateResponse {
		$user = $this->userManager->get($email);

		if ($user !== null && $this->config->getUserValue($email, $this->appName, 'pp_disabled', '') === '1') {
			$user->setEnabled(true);
			$this->config->setUserValue(
				$email,
				$this->appName,
				'disable_user_after',
				(string)($this->timeFactory->getTime() + AccountController::validateEmailDelay)
			);
			$this->config->deleteUserValue($email, $this->appName, 'pp_disabled');

			try {
				$emailTemplate = $this->verifyMailHelper->generateTemplate($user);
				$this->verifyMailHelper->sendMail($user, $emailTemplate);
			} catch (\Exception $e) {
				$this->logger->error("Can't send reactivation mail to $email", ['exception' => $e]);
			}

			$this->logger->info("User $email requested self-reactivation");
		}

		// Always return the same response to prevent email enumeration.
		return new TemplateResponse($this->appName, 'reactivate-public', ['status' => 'sent'], 'guest');
	}
}
