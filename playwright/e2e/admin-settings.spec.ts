/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { login } from '../support/login'
import * as occ from '../support/occ'

test('an admin can open the settings page and reset the provider token', async ({ page }) => {
	await login(page, 'admin', 'admin')
	await page.goto('settings/admin/preferred_providers')

	// token + group sections render
	const tokenField = page.locator('#token')
	await expect(tokenField).toBeVisible()
	const initialToken = await tokenField.inputValue()
	expect(initialToken).not.toBe('')
	await expect(page.locator('#groups')).toBeVisible()
	await expect(page.locator('#groups_unconfirmed')).toBeVisible()
	await expect(page.locator('#groups_confirmed')).toBeVisible()

	try {
		// the reset button (vanilla-JS OCS round-trip) issues a fresh token
		await page.locator('#token-reset').click()
		await expect(tokenField).not.toHaveValue(initialToken, { timeout: 10000 })
		await expect(tokenField).not.toHaveValue('')
	} finally {
		// reset changed the shared token; restore it for the provisioning-dependent specs
		await occ.setProviderToken()
	}
})
