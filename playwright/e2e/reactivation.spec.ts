/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'
import { APP_ID } from '../support/constants.mjs'
import { clearMailbox, extractLink, waitForMailBody } from '../support/mailpit'
import * as occ from '../support/occ'
import { requestAccount, toPath } from '../support/provider'

async function submitReactivation(page: Page, email: string): Promise<void> {
	await page.goto(`apps/${APP_ID}/reactivate`)
	await expect(page.locator('#reactivate-form')).toBeVisible()
	await page.locator('#email').fill(email)
	await page.locator('#submit').click()
	await expect(page.getByText('Check your email')).toBeVisible()
}

test('auto-disabled account can self-reactivate and re-verify', async ({ page, request }) => {
	const { email } = await requestAccount(request, 'e2e-react')
	await occ.seedAutoDisabled(email)
	expect(await occ.isEnabled(email)).toBe(false)

	await clearMailbox()
	await submitReactivation(page, email)

	// reactivation re-enabled the account and sent a fresh verification mail
	expect(await occ.isEnabled(email)).toBe(true)
	const confirmUrl = extractLink(await waitForMailBody(email), 'login/confirm')
	await page.goto(toPath(confirmUrl))
	expect(await occ.isEnabled(email)).toBe(true)

	await occ.deleteUser(email)
})

test('admin-disabled account is NOT reactivatable via self-service', async ({ page, request }) => {
	const { email } = await requestAccount(request, 'e2e-adminoff')
	await occ.adminDisable(email) // no pp_disabled flag
	expect(await occ.isEnabled(email)).toBe(false)

	await clearMailbox()
	await submitReactivation(page, email) // identical "sent" response (no enumeration)

	// still disabled: the flow only touches accounts flagged pp_disabled=1
	expect(await occ.isEnabled(email)).toBe(false)

	await occ.deleteUser(email)
})
