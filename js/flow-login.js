(function() {
	var flowLogin = document.getElementById('flow-login')
	if (!flowLogin) {
		return
	}

	var ncLoginUrl = flowLogin.getAttribute('data-nc-login-url')
	var redirectUrl = flowLogin.getAttribute('data-redirect-url')

	if (ncLoginUrl) {
		window.location.assign(ncLoginUrl)
	}

	if (redirectUrl) {
		window.setTimeout(function() {
			window.location.assign(redirectUrl)
		}, 1000)
	}
})()
