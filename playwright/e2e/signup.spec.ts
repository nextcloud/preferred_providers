/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { GROUP_CONFIRMED, GROUP_UNCONFIRMED } from '../support/constants.mjs'
import { expectLoggedIn } from '../support/login'
import { clearMailbox, extractLink, waitForMailBody } from '../support/mailpit'
import * as occ from '../support/occ'
import { requestAccount, toPath } from '../support/provider'

test('full signup → set password → login → verify email → group move', async ({ page, request }) => {
	await clearMailbox()

	// 1. provisioning API creates the account and hands back the set-password URL directly
	const { email, setPasswordUrl } = await requestAccount(request, 'e2e-signup')
	test.info().annotations.push({ type: 'account', description: email })
	expect(await occ.inGroup(email, GROUP_UNCONFIRMED)).toBe(true)

	// 2. set the password in the browser → auto-login + redirect home
	await page.goto(toPath(setPasswordUrl))
	await page.locator('#password').fill('S3cr3t-e2e-pw!')
	await page.locator('#submit').click()
	// submit redirects away from the set-password page and auto-logs the user in
	await page.waitForURL((url) => !url.pathname.includes('/password/set'), { timeout: 30000 })
	await expectLoggedIn(page, email)

	// 3. the verification mail was sent on request → follow its confirm link
	const body = await waitForMailBody(email)
	const confirmUrl = extractLink(body, 'login/confirm')
	await page.goto(toPath(confirmUrl))

	// 4. verified: account enabled and moved from unconfirmed → confirmed group
	expect(await occ.isEnabled(email)).toBe(true)
	expect(await occ.inGroup(email, GROUP_CONFIRMED)).toBe(true)
	expect(await occ.inGroup(email, GROUP_UNCONFIRMED)).toBe(false)

	await occ.deleteUser(email)
})
