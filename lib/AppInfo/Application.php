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

use OCA\Preferred_Providers\Event\LoginListener;
use OCA\Preferred_Providers\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\User\Events\BeforeUserLoggedInEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'preferred_providers';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		
		$context->registerEventListener(BeforeUserLoggedInEvent::class, LoginListener::class);
	}

	public function boot(IBootContext $context): void {
		$container = $context->getServerContainer();

		$dispatcher = $container->query(IEventDispatcher::class);
		$dispatcher->addListener('OC\Settings\Users::loadAdditionalScripts', function () {
			Util::addScript(self::APP_ID, 'users-management');
		});
	}
}
