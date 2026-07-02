/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig, devices } from '@playwright/test'

const isCI = !!process.env.CI

export default defineConfig({
	testDir: './playwright/e2e',
	timeout: 60000,
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',

	use: {
		baseURL: process.env.BASE_URL ?? 'http://localhost:8089/index.php/',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	webServer: {
		command: 'npm run start:nextcloud',
		gracefulShutdown: { signal: 'SIGTERM', timeout: 15000 },
		stderr: 'pipe',
		stdout: 'pipe',
		timeout: 6 * 60 * 1000,
		// Readiness is the start script's explicit marker, printed only once the
		// app is enabled AND fully provisioned. The port answers well before that,
		// so a `url` check would race provisioning — hence stdout-based waiting.
		wait: {
			stdout: /preferred_providers Playwright environment is ready/,
		},
		// Locally (e.g. `--ui`) reuse a server that is already up so re-runs don't
		// tear it down and restart it. In CI every run gets a fresh container.
		...(isCI ? {} : { url: 'http://127.0.0.1:8089', reuseExistingServer: true }),
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
})
