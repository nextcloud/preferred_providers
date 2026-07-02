/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { MAILPIT_API } from './constants.mjs'

interface MessageSummary {
	ID: string
	To: { Address: string }[]
	Subject: string
}

/** Delete all captured messages — call before a step that expects a fresh mail. */
export async function clearMailbox(): Promise<void> {
	await fetch(`${MAILPIT_API}/api/v1/messages`, { method: 'DELETE' })
}

/** Poll until a message addressed to `recipient` arrives, then return its full text body. */
export async function waitForMailBody(recipient: string): Promise<string> {
	let lastError = 'no message'
	for (let attempt = 0; attempt < 30; attempt++) {
		const res = await fetch(`${MAILPIT_API}/api/v1/messages?query=${encodeURIComponent('to:' + recipient)}`)
		if (res.ok) {
			const list = (await res.json()) as { messages: MessageSummary[] }
			const match = list.messages?.find((m) => m.To?.some((t) => t.Address === recipient))
			if (match) {
				const full = await fetch(`${MAILPIT_API}/api/v1/message/${match.ID}`)
				const body = (await full.json()) as { Text: string, HTML: string }
				return body.Text || body.HTML || ''
			}
			lastError = `no message to ${recipient} yet`
		} else {
			lastError = `mailpit ${res.status}`
		}
		await new Promise((r) => setTimeout(r, 1000))
	}
	throw new Error(`Timed out waiting for mail to ${recipient}: ${lastError}`)
}

/** Extract the first absolute URL whose path matches `pathFragment`. */
export function extractLink(body: string, pathFragment: string): string {
	// mail templates may percent/entity-encode; normalise the common cases
	const normalised = body.replace(/&amp;/g, '&').replace(/=\r?\n/g, '')
	const re = new RegExp(`https?://[^\\s"'<>]*${pathFragment}[^\\s"'<>]*`)
	const match = normalised.match(re)
	expect(match, `expected a ${pathFragment} link in the email body`).not.toBeNull()
	return match![0]
}
