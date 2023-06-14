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

\OCP\Util::addScript('preferred_providers', 'password-public');
\OCP\Util::addStyle('preferred_providers', 'password-public');

?>
<div class="guest-box login-box">
	<form action="<?php print_unescaped($_['link']) ?>" id="set-password" class="set-password-form" method="post">
		<fieldset>
			<!-- Title -->
			<h2><?php p($l->t('Set your password')); ?></h2>

			<!-- Email -->
			<div>
				<label for="email"><?php p($l->t('Account name or email')); ?></label>
				<input type="email" name="email" id="email" value="<?php print_unescaped($_['email']) ?>" readonly />
			</div>

			<!-- Password -->
			<div>
				<label for="password"><?php p($l->t('Password')); ?></label>
				<input type="password" maxlength="100" name="password" id="password" value="" placeholder="<?php p($l->t('Set password')); ?>" required />
				<input type="checkbox" id="show" name="show">
				<label for="show"></label>
			</div>

			<!-- Submit -->
			<div id="submit-wrapper">
				<input type="hidden" value="<?php print_unescaped($_['ocsapirequest']) ?>" name="ocsapirequest">
				<input class="login primary" type="submit" id="submit" value="<?php p($l->t('Log in')); ?>" />
				<div class="submit-icon icon-confirm-white"></div>
			</div>
		</fieldset>
		<p class="info"><?php p($l->t('After this step, you will receive a mail to verify your account within 6 hours.')); ?></p>
		<?php if ($_['error']) { ?>
			<p class="warning error">
			<?php print_unescaped($_['error']) ?>
			</p>
		<?php } ?>
	</form>
</div>
