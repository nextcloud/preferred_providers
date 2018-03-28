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

 ?>
 <div id="security-warning" class="section">
    <h2><?php p($l->t('Preferred providers token')); ?></h2>
    <p class="warning"><?php p($l->t('WARNING! This token is very important and must be handled carefully. You should only give it to a nextcloud official!')); ?></p>
    <p><?php p($l->t('Your provider token')); ?>: <input type="text" value="<?php p($_['provider_token']) ?>" readonly style="width:250px"/></p>
</div>