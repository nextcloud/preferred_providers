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

return [
	'ocs'    => [
		['root' => '/account', 'name' => 'Account#requestAccount', 'url' => '/request/{token}', 'verb' => 'POST'],
		['name' => 'Settings#resetToken', 'url' => '/api/v1/token/new', 'verb' => 'GET'],
		['name' => 'Settings#setGroups', 'url' => '/api/v1/groups', 'verb' => 'POST']
	],
	'routes' => [
		['name' => 'mail#confirm_mail_address', 'url' => '/login/confirm/{email}/{token}', 'verb' => 'GET'],
		['name' => 'password#set_password', 'url' => '/password/set/{email}/{token}', 'verb' => 'GET'],
		['name' => 'password#set_password_ocs', 'url' => '/password/set/{email}/{token}/{ocs}', 'verb' => 'GET'],
		['name' => 'password#submit_password', 'url' => '/password/submit/{token}', 'verb' => 'POST']
	]
];
