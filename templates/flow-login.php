<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2026
 *
 * @license GNU AGPL version 3 or any later version
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
