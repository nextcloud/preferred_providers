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

namespace OCA\Preferred_Providers\AppInfo;

use OCA\Preferred_Providers\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\IServerContainer;
use OCA\Preferred_Providers\Hook\LoginHook;

class Application extends App {

	/** @var string */
	protected $appName = 'preferred_providers';
	
	public function __construct() {
		parent::__construct($this->appName);
	}

	public function register() {
		$this->registerNotifier($this->getContainer()->getServer());
		$this->getContainer()->query(LoginHook::class)->register();
	}

	protected function registerNotifier(IServerContainer $server) {
		$manager = $server->getNotificationManager();
		$manager->registerNotifier(function() use ($server) {
			return $server->query(Notifier::class);
		}, function() use ($server) {
			$l = $server->getL10N($this->appName);
			return [
				'id' => $this->appName,
				'name' => $l->t('Simple signup'),
			];
		});
	}
}
