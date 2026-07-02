/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// @ts-expect-error - the harness ships CJS without bundled types for this entrypoint
import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { APP_ID, PROVIDER_TOKEN } from './constants.mjs'

interface UserInfo {
	user_id: string
	email: string | null
	enabled: boolean
	groups: string[]
}

/** Create an enabled user with a known password (via OC_PASS). */
export async function createUser(uid: string, password: string): Promise<void> {
	await runOcc(['user:add', '--password-from-env', uid], { env: [`OC_PASS=${password}`] })
}

/** Restore the shared provider token (undoes an admin-page token regeneration). */
export async function setProviderToken(token: string = PROVIDER_TOKEN): Promise<void> {
	await runOcc(['config:app:set', APP_ID, 'provider_token', `--value=${token}`])
}

export async function userInfo(uid: string): Promise<UserInfo> {
	const out = await runOcc(['user:info', uid, '--output=json'])
	return JSON.parse(out) as UserInfo
}

export async function isEnabled(uid: string): Promise<boolean> {
	return (await userInfo(uid)).enabled
}

export async function inGroup(uid: string, group: string): Promise<boolean> {
	return (await userInfo(uid)).groups.includes(group)
}

/** Force an account into the "auto-disabled by this app" state, no 6h wait. */
export async function seedAutoDisabled(uid: string): Promise<void> {
	await runOcc(['user:disable', uid])
	// user preferences are managed via `user:setting <uid> <app> <key> <value>`
	await runOcc(['user:setting', uid, APP_ID, 'pp_disabled', '1'])
}

/** Disable an account the way an admin would (no pp_disabled flag). */
export async function adminDisable(uid: string): Promise<void> {
	await runOcc(['user:disable', uid])
}

export async function deleteUser(uid: string): Promise<void> {
	await runOcc(['user:delete', uid]).catch(() => {})
}
