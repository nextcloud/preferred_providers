/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** App id, as enabled in the test container. */
export const APP_ID = 'preferred_providers'

/** Provider token seeded into app config and used by the request-account API. */
export const PROVIDER_TOKEN = 'e2e-provider-token'

/**
 * Base group every provisioned account is added to. Must be non-empty: the
 * controller only runs its group assignment when provider_groups is set.
 */
export const GROUP_BASE = 'pp_all'
/** Groups an unverified account is put into on request, then moved out of on verify. */
export const GROUP_UNCONFIRMED = 'pp_pending'
/** Groups an account is moved into once the email is verified. */
export const GROUP_CONFIRMED = 'pp_members'

/** Docker sidecar names / endpoints for capturing outgoing mail. */
export const MAILPIT_CONTAINER = 'pp-mailpit'
export const MAILPIT_IMAGE = 'axllent/mailpit:latest'
/** User-defined network so the server can resolve Mailpit by name (bridge has no DNS). */
export const MAILPIT_NETWORK = 'pp-e2e'
export const MAILPIT_API = process.env.MAILPIT_API ?? 'http://localhost:8025'
