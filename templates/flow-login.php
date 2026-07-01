<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addScript('preferred_providers', 'flow-login');
\OCP\Util::addStyle('preferred_providers', 'password-public');

?>
<div class="guest-box login-box" id="flow-login"
	data-nc-login-url="<?php p($_['ncLoginUrl']); ?>"
	data-redirect-url="<?php p($_['redirectUrl']); ?>">
	<h2><?php p($l->t('Log in')); ?></h2>
	<p class="info"><?php p($l->t('Your account is ready.')); ?></p>
</div>
