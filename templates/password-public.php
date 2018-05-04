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

 script('preferred_providers', 'password-public');
?>
<form action="<?php print_unescaped($_['link']) ?>" id="set-password" method="post">
	<fieldset>
		<div class="grouptop">
			<input type="hidden" value="<?php print_unescaped($_['ocsapirequest']) ?>" name="ocsapirequest">
			<input type="email" name="email" id="email" value="<?php print_unescaped($_['email']) ?>" readonly />
		</div>
		<div class="groupbottom">
			<input type="password" name="password" id="password" value="" placeholder="<?php p($l->t('Enter password')); ?>" required />
			<input type="checkbox" id="show" name="show">
			<label for="show"></label>
		</div>
		<div id="submit-wrapper">
			<input class="login primary" type="submit" id="submit" value="<?php p($l->t('Log in')); ?>" />
			<div class="submit-icon icon-confirm-white"></div>
		</div>
	</fieldset>
	<?php if($_['error']) { ?>
		<p class="warning error">
		   <?php print_unescaped($_['error']) ?>
		</p>
	<?php } ?>
</form>