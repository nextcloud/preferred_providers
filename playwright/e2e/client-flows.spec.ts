/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '@playwright/test'
import { APP_ID } from '../support/constants.mjs'
import * as occ from '../support/occ'
import { requestAccount } from '../support/provider'

/** The set-password token is the last path segment of the set-password URL. */
function tokenFromUrl(setPasswordUrl: string): string {
	const parts = new URL(setPasswordUrl).pathname.split('/').filter(Boolean)
	return parts[parts.length - 1]
}

const PASSWORD = 'S3cr3t-e2e-pw!'

test('client flow V3 renders the nc:// flow-login page', async ({ request }) => {
	const { email, setPasswordUrl } = await requestAccount(request, 'e2e-v3')
	const token = tokenFromUrl(setPasswordUrl)

	const res = await request.post(`apps/${APP_ID}/password/submit/${token}`, {
		form: { email, password: PASSWORD, flow: 'V3' },
	})
	expect(res.status()).toBe(200)
	expect(await res.text()).toContain('nc://login')

	await occ.deleteUser(email)
})

test('client flow with OCS-APIREQUEST redirects to the nc:// app-password URL', async ({ request }) => {
	const { email, setPasswordUrl } = await requestAccount(request, 'e2e-ocs')
	const token = tokenFromUrl(setPasswordUrl)

	const res = await request.post(`apps/${APP_ID}/password/submit/${token}`, {
		headers: { 'OCS-APIREQUEST': 'true' },
		form: { email, password: PASSWORD, ocsapirequest: '1' },
		maxRedirects: 0,
	})
	expect([301, 302, 303, 307, 308]).toContain(res.status())
	expect(res.headers()['location'] ?? '').toMatch(/^nc:\/\/login\//)

	await occ.deleteUser(email)
})
