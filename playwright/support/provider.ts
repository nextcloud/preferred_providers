/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'
import { expect } from '@playwright/test'
import { PROVIDER_TOKEN } from './constants.mjs'

/**
 * Reduce an absolute Nextcloud URL to an origin-relative path so navigation
 * always goes through the Playwright baseURL host, regardless of the
 * overwrite.cli.url the container reports.
 */
export function toPath(absolute: string): string {
	const url = new URL(absolute)
	return url.pathname + url.search
}

export interface RequestedAccount {
	email: string
	setPasswordUrl: string
}

/**
 * Call the public OCS request-account endpoint for a freshly generated address
 * and return that address plus the set-password URL it hands back directly.
 *
 * A transient 5xx (cold server / sqlite lock) can still create the user before
 * failing, so each retry uses a brand-new address rather than colliding on
 * "user already exists".
 */
export async function requestAccount(request: APIRequestContext, prefix = 'e2e'): Promise<RequestedAccount> {
	let lastText = ''
	for (let attempt = 0; attempt < 4; attempt++) {
		const email = `${prefix}-${Date.now()}-${attempt}@example.com`
		// OCS is served from the origin (…/ocs/v2.php), not under the /index.php/ baseURL.
		// The route declares root '/account', replacing the default /apps/{appid} prefix.
		const res = await request.post(
			`/ocs/v2.php/account/request/${PROVIDER_TOKEN}?format=json`,
			{
				headers: { 'OCS-APIREQUEST': 'true' },
				form: { email },
			},
		)
		if (res.status() >= 500) {
			lastText = await res.text()
			await new Promise((r) => setTimeout(r, 1000))
			continue
		}
		expect(res.status(), await res.text()).toBe(201)
		const body = await res.json()
		// AccountController is an ApiController → the DataResponse is returned as
		// plain JSON ({"data":{"setPassword":…}}), not wrapped in the OCS envelope.
		const setPasswordUrl = body?.data?.setPassword ?? body?.ocs?.data?.data?.setPassword
		expect(setPasswordUrl, `no setPassword URL in response: ${JSON.stringify(body)}`).toBeTruthy()
		return { email, setPasswordUrl: setPasswordUrl as string }
	}
	throw new Error(`request-account kept failing with 5xx: ${lastText}`)
}
