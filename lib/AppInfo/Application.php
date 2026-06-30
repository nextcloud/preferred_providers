<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);

		$context->registerEventListener(BeforeUserLoggedInEvent::class, LoginListener::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		$container = $context->getServerContainer();

		$dispatcher = $container->get(IEventDispatcher::class);
		$dispatcher->addListener('OC\Settings\Users::loadAdditionalScripts', function (): void {
			Util::addScript(self::APP_ID, 'users-management');
		});
	}
}
