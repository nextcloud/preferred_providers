/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	configureNextcloud,
	getContainerName,
	runExec,
	runOcc,
	startNextcloud,
	stopNextcloud,
	waitOnNextcloud,
} from '@nextcloud/e2e-test-server/docker'
import { execSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import {
	APP_ID,
	GROUP_BASE,
	GROUP_CONFIRMED,
	GROUP_UNCONFIRMED,
	MAILPIT_CONTAINER,
	MAILPIT_IMAGE,
	MAILPIT_NETWORK,
	PROVIDER_TOKEN,
} from './support/constants.mjs'

/** Run a docker command, ignoring failures (e.g. "already exists"). */
function quietExec(cmd) {
	try {
		execSync(cmd, { stdio: 'ignore' })
	} catch {
		// ignore
	}
}

/** Resolve the server branch from env or the app's info.xml max-version. */
function resolveBranch() {
	if (process.env.SERVER_BRANCH) {
		return process.env.SERVER_BRANCH
	}
	const appinfo = readFileSync('appinfo/info.xml').toString()
	const maxVersion = appinfo.match(/max-version="(\d\d+)"/)?.[1]
	if (maxVersion) {
		try {
			// Query only the candidate branch — a bare `git ls-remote` returns every
			// ref (~1MB) and overflows execSync's default buffer (ENOBUFS).
			const ref = execSync(
				`git ls-remote --heads https://github.com/nextcloud/server.git stable${maxVersion}`,
				{ encoding: 'utf-8' },
			)
			if (ref.includes(`refs/heads/stable${maxVersion}`)) {
				return `stable${maxVersion}`
			}
		} catch {
			// network/git failure → fall back to the minimum supported branch
		}
	}
	return 'stable31'
}

/**
 * Run Mailpit on a user-defined network shared with the server so the server can
 * resolve it by name (the default bridge network has no container DNS), and
 * publish the REST API on the host for the test process to poll.
 */
function startMailpit() {
	quietExec(`docker network create ${MAILPIT_NETWORK}`)
	quietExec(`docker network connect ${MAILPIT_NETWORK} ${getContainerName()}`)
	quietExec(`docker rm -f ${MAILPIT_CONTAINER}`)
	execSync(
		`docker run -d --name ${MAILPIT_CONTAINER} --network ${MAILPIT_NETWORK} -p 8025:8025 ${MAILPIT_IMAGE}`,
		{ stdio: 'inherit' },
	)
}

async function configureMail() {
	// Nextcloud reaches Mailpit by container name on the shared network (SMTP :1025);
	// the test process reaches Mailpit's REST API on the host (:8025).
	await runOcc(['config:system:set', 'mail_smtpmode', '--value', 'smtp'])
	await runOcc(['config:system:set', 'mail_smtphost', '--value', MAILPIT_CONTAINER])
	await runOcc(['config:system:set', 'mail_smtpport', '--value', '1025'])
	await runOcc(['config:system:set', 'mail_from_address', '--value', 'no-reply'])
	await runOcc(['config:system:set', 'mail_domain', '--value', 'example.com'])
}

async function provisionApp() {
	// Ensure occ-written app config is visible to the web SAPI: with a local
	// memcache, CLI and web caches diverge and Admin::getForm would see an empty
	// provider_token and regenerate (clobbering) it.
	await runOcc(['config:system:delete', 'memcache.local']).catch(() => {})

	// groups used by the provider flow
	for (const group of [GROUP_BASE, GROUP_UNCONFIRMED, GROUP_CONFIRMED]) {
		await runOcc(['group:add', group]).catch(() => {})
	}
	await runOcc(['config:app:set', APP_ID, 'provider_token', `--value=${PROVIDER_TOKEN}`])
	// provider_groups must be non-empty or the controller skips group assignment entirely
	await runOcc(['config:app:set', APP_ID, 'provider_groups', `--value=${GROUP_BASE}`])
	await runOcc(['config:app:set', APP_ID, 'provider_groups_unconfirmed', `--value=${GROUP_UNCONFIRMED}`])
	await runOcc(['config:app:set', APP_ID, 'provider_groups_confirmed', `--value=${GROUP_CONFIRMED}`])

	const seededToken = (await runOcc(['config:app:get', APP_ID, 'provider_token'])).trim()
	process.stdout.write(`[provision] provider_token=${seededToken}\n`)

	// Warm up routing/OCS/DB so the first real test doesn't hit a cold 500.
	await runExec([
		'curl', '-s', '-o', '/dev/null', '-X', 'POST', '-H', 'OCS-APIREQUEST: true',
		`http://localhost/ocs/v2.php/account/request/${PROVIDER_TOKEN}?format=json&email=warmup@example.com`,
	]).catch(() => {})
}

async function stop() {
	process.stderr.write('Stopping Nextcloud server…\n')
	quietExec(`docker rm -f ${MAILPIT_CONTAINER}`)
	quietExec(`docker network rm ${MAILPIT_NETWORK}`)
	await stopNextcloud()
	// eslint-disable-next-line n/no-process-exit
	process.exit(0)
}

process.on('SIGINT', stop)
process.on('SIGTERM', stop)

const branch = resolveBranch()
const ip = await startNextcloud(branch, true, { exposePort: 8089, forceRecreate: true })
await waitOnNextcloud(ip)
await configureNextcloud([APP_ID], branch === 'master' ? 'master' : branch)

startMailpit()
await configureMail()
await provisionApp()

process.stdout.write('preferred_providers Playwright environment is ready\n')

// Idle until Playwright signals shutdown.
// eslint-disable-next-line no-constant-condition
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
