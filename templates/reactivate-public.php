<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Seyed Masih Sajadi <smasihsajadi@gmail.com>
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
<div class="guest-box login-box">
<?php if ($_['status'] === 'sent') { ?>
	<h2><?php p($l->t('Check your email')); ?></h2>
	<p class="info"><?php p($l->t('If a deactivated account for that address exists, we\'ve sent a new verification link. It expires in 6 hours.')); ?></p>
<?php } else { ?>
	<form action="" id="reactivate-form" method="post">
		<fieldset>
			<h2><?php p($l->t('Reactivate your account')); ?></h2>
			<div>
				<label for="email"><?php p($l->t('Account name or email')); ?></label>
				<input type="email" name="email" id="email" value="" placeholder="<?php p($l->t('Enter your email address')); ?>" required autofocus />
			</div>
			<div id="submit-wrapper">
				<input class="login primary" type="submit" id="submit" value="<?php p($l->t('Send reactivation email')); ?>" />
			</div>
		</fieldset>
	</form>
<?php } ?>
</div>
