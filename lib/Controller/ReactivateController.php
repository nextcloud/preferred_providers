<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Seyed Masih Sajadi <smasihsajadi@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Controller;

use OCA\Preferred_Providers\Mailer\VerifyMailHelper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ReactivateController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IConfig $config,
		private readonly IUserManager $userManager,
		private readonly ITimeFactory $timeFactory,
		private readonly VerifyMailHelper $verifyMailHelper,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Render the self-service reactivation form.
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[FrontpageRoute(verb: 'GET', url: '/reactivate')]
	public function showForm(): TemplateResponse {
		return new TemplateResponse($this->appName, 'reactivate-public', ['status' => 'form'], 'guest');
	}

	/**
	 * Re-enable an account that this app auto-disabled and send a fresh
	 * verification mail. The response is identical whether or not the address
	 * matched an eligible account, to prevent account enumeration.
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	#[AnonRateLimit(limit: 3, period: 60)]
	#[FrontpageRoute(verb: 'POST', url: '/reactivate')]
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
