<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
				<input type="hidden" value="<?php p($_['flow']) ?>" name="flow">
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
