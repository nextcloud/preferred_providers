<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Seyed Masih Sajadi <smasihsajadi@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
