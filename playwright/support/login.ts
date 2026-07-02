/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import { expect } from '@playwright/test'

/** Log in through the standard Nextcloud guest login form. */
export async function login(page: Page, user: string, password: string): Promise<void> {
	await page.goto('login')
	await page.locator('input[name="user"]').fill(user)
	await page.locator('input[name="password"]').fill(password)
	await page.locator('button[type="submit"], input[type="submit"]').first().click()
	await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30000 })
	await expectLoggedIn(page, user)
}

/** Assert the current session is logged in as the given uid. */
export async function expectLoggedIn(page: Page, uid: string): Promise<void> {
	const res = await page.request.get('/ocs/v2.php/cloud/user?format=json', {
		headers: { 'OCS-APIREQUEST': 'true' },
	})
	expect(res.ok()).toBeTruthy()
	const body = await res.json()
	expect(body?.ocs?.data?.id).toBe(uid)
}
