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

namespace OCA\Preferred_Providers\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Security\ISecureRandom;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	/** @var string */
	private $appName;

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
	public function __construct(string $appName,
		IConfig $config,
		ISecureRandom $secureRandom,
		IGroupManager $groupManager) {
		$this->appName = $appName;
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->groupManager = $groupManager;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		// Generate new token if none exists
		$provider_token = $this->config->getAppValue($this->appName, 'provider_token', '');
		if ($provider_token === '') {
			$provider_token = md5($this->secureRandom->generate(10));
			$this->config->setAppValue($this->appName, 'provider_token', $provider_token);
		}

		// Get groups settings
		$provider_groups = $this->config->getAppValue($this->appName, 'provider_groups', '');
		$provider_groups_confirmed = $this->config->getAppValue($this->appName, 'provider_groups_confirmed', '');
		$provider_groups_unconfirmed = $this->config->getAppValue($this->appName, 'provider_groups_unconfirmed', '');

		$parameters = [
			'provider_token' => $provider_token,
			'provider_groups' => explode(',', $provider_groups),
			'provider_groups_confirmed' => explode(',', $provider_groups_confirmed),
			'provider_groups_unconfirmed' => explode(',', $provider_groups_unconfirmed),
			'groups' => $this->groupManager->search('')
		];

		return new TemplateResponse($this->appName, 'settings-admin', $parameters);
	}

	/**
	 * @return string the section ID
	 */
	public function getSection() {
		return 'preferred_providers';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 5;
	}
}
