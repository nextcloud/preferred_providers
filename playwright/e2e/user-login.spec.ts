/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test } from '@playwright/test'
import { login } from '../support/login'
import * as occ from '../support/occ'

test('a user can log in through the web UI', async ({ page }) => {
	const uid = `e2e-login-${Date.now()}`
	await occ.createUser(uid, 'S3cr3t-e2e-pw!')

	// login() asserts the session is established as this uid
	await login(page, uid, 'S3cr3t-e2e-pw!')

	await occ.deleteUser(uid)
})
