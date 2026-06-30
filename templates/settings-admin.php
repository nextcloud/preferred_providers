<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

\OCP\Util::addScript('preferred_providers', 'admin-settings');
\OCP\Util::addStyle('preferred_providers', 'admin-settings');
?>

<div id="token-section" class="section">
    <h2><?php p($l->t('Preferred providers token')); ?></h2>
    <p class="warning"><?php p($l->t('WARNING! This token is very important and must be handled carefully. You should only give it to a Nextcloud official!')); ?></p>
    <p><?php p($l->t('Your provider token')); ?>:</p>
    <div class="token-container">
        <input type="text" id="token" value="<?php p($_['provider_token']) ?>" readonly />
        <input type="button" id="token-reset" class="icon-history" title="<?php p($l->t('Reset your token')); ?>" />
    </div>
</div>

<div id="groups-section" class="section">
    <h2><?php p($l->t('Preferred providers groups')); ?></h2>
    <p><?php p($l->t('Select the groups to which each new account will be added.')); ?>:</p>
    <select multiple id="groups">
        <?php foreach ($_['groups'] as $group) {
        	$gid = $group->getGid();
        	$gname = $group->getDisplayName(); ?>
        <option value="<?php p($gid); ?>" <?php p(in_array($gid, $_['provider_groups']) ? 'selected="selected"' : ''); ?>><?php p($gname); ?></option>
        <?php
        }?>
    </select>

    <p><?php p($l->t('Select the groups that will be assigned to each unconfirmed account. They will be deleted once the account has been verified.')); ?>:</p>
    <select multiple id="groups_unconfirmed">
        <?php foreach ($_['groups'] as $group) {
        	$gid = $group->getGid();
        	$gname = $group->getDisplayName(); ?>
        <option value="<?php p($gid); ?>" <?php p(in_array($gid, $_['provider_groups_unconfirmed']) ? 'selected="selected"' : ''); ?>><?php p($gname); ?></option>
        <?php
        }?>
    </select>

    <p><?php p($l->t('Select the groups that will be assigned to each confirmed account.')); ?>:</p>
    <select multiple id="groups_confirmed">
        <?php foreach ($_['groups'] as $group) {
        	$gid = $group->getGid();
        	$gname = $group->getDisplayName(); ?>
        <option value="<?php p($gid); ?>" <?php p(in_array($gid, $_['provider_groups_confirmed']) ? 'selected="selected"' : ''); ?>><?php p($gname); ?></option>
        <?php
        }?>
    </select>
</div>
