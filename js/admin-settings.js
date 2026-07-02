/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Dependency-free: jQuery, underscore and OC.linkToOCS are no longer part of the
// default server bundle on modern Nextcloud, so this uses vanilla DOM + fetch.
(function() {
	'use strict'

	function ocsUrl(path) {
		return OC.getRootPath() + '/ocs/v2.php/apps/preferred_providers/' + path
	}

	function ocs(path, options) {
		return fetch(ocsUrl(path), Object.assign({
			headers: {
				'OCS-APIREQUEST': 'true',
				requesttoken: OC.requestToken,
				Accept: 'application/json',
			},
		}, options)).then(function(response) {
			return response.json()
		})
	}

	function debounce(fn, wait) {
		var timer
		return function() {
			var context = this
			var args = arguments
			clearTimeout(timer)
			timer = setTimeout(function() {
				fn.apply(context, args)
			}, wait)
		}
	}

	function selectedValues(select) {
		return Array.prototype.slice.call(select.selectedOptions).map(function(option) {
			return option.value
		})
	}

	function bindGroupSelect(id, forValue) {
		var select = document.getElementById(id)
		if (!select) {
			return
		}
		select.addEventListener('change', debounce(function() {
			select.disabled = true
			select.classList.add('icon-loading')
			var body = new URLSearchParams()
			selectedValues(select).forEach(function(group) {
				body.append('groups[]', group)
			})
			if (forValue) {
				body.append('for', forValue)
			}
			ocs('api/v1/groups', { method: 'POST', body: body }).finally(function() {
				select.disabled = false
				select.classList.remove('icon-loading')
			})
		}, 250))
	}

	function init() {
		var tokenField = document.getElementById('token')
		var resetButton = document.getElementById('token-reset')
		if (resetButton && tokenField) {
			resetButton.addEventListener('click', debounce(function() {
				resetButton.disabled = true
				tokenField.classList.add('icon-loading')
				ocs('api/v1/token/new').then(function(response) {
					tokenField.value = response.ocs.data.token
				}).finally(function() {
					resetButton.disabled = false
					tokenField.classList.remove('icon-loading')
				})
			}, 250))
		}

		bindGroupSelect('groups', '')
		bindGroupSelect('groups_unconfirmed', 'unconfirmed')
		bindGroupSelect('groups_confirmed', 'confirmed')
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init)
	} else {
		init()
	}
})()
