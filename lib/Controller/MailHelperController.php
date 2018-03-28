<?php
declare(strict_types=1);
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

class MailHelperController {

	/** @var string */
	protected $appName;

	/**
	 * Account constructor.
	 * 
	 * @param string $appName
	 * @param IRequest $request
	 */
	public function __construct(string $appName,
								IRequest $request) {

    }


    /**
     * Validate email
	 * 
	 * @param string $email The email to create an account for
	 * @param string $token The security token
	 * @throws OCSForbiddenException
     */
    public function confirmMailAddress(string $email, string $token) {
		
	}
}
