<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Preferred_Providers\Settings;

use OCA\Preferred_Providers\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Security\ISecureRandom;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	/** @var IConfig */
	private $config;

	/** @var ISecureRandom */
	private $secureRandom;

	/** @var IGroupManager */
	private $groupManager;

	/**
	 * Admin constructor.
	 *
	 * @param IConfig $config
	 * @param ISecureRandom $secureRandom
	 * @param IGroupManager $groupManager
	 */
	public function __construct(
		private readonly string $appName,
		IConfig $config,
		ISecureRandom $secureRandom,
		IGroupManager $groupManager,
	) {
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->groupManager = $groupManager;
	}

	/**
	 * @return TemplateResponse
	 */
	#[\Override]
	public function getForm() {
		// Generate new token if none exists
		$provider_token = $this->config->getAppValue($this->appName, 'provider_token', '');
		if ($provider_token === '') {
			$provider_token = md5((string)$this->secureRandom->generate(10));
			$this->config->setAppValue($this->appName, 'provider_token', $provider_token);
		}

		// Get groups settings
		$provider_groups = $this->config->getAppValue($this->appName, 'provider_groups', '');
		$provider_groups_confirmed = $this->config->getAppValue($this->appName, 'provider_groups_confirmed', '');
		$provider_groups_unconfirmed = $this->config->getAppValue($this->appName, 'provider_groups_unconfirmed', '');

		$parameters = [
			'provider_token' => $provider_token,
			'provider_groups' => explode(',', $provider_groups),
			'provider_groups_confirmed' => explode(',', (string)$provider_groups_confirmed),
			'provider_groups_unconfirmed' => explode(',', (string)$provider_groups_unconfirmed),
			'groups' => $this->groupManager->search('')
		];

		return new TemplateResponse($this->appName, 'settings-admin', $parameters);
	}

	/**
	 * @return string the section ID
	 */
	#[\Override]
	public function getSection() {
		return Application::APP_ID;
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	#[\Override]
	public function getPriority() {
		return 5;
	}
}
