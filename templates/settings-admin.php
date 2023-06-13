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

script('preferred_providers', 'admin-settings');
style('preferred_providers', 'admin-settings');
?>

<div id="token-section" class="section">
    <h2><?php p($l->t('Preferred providers token')); ?></h2>
    <p class="warning"><?php p($l->t('WARNING! This token is very important and must be handled carefully. You should only give it to a nextcloud official!')); ?></p>
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