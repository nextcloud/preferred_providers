$(document).ready(function() {
	sendEmail = function(event, user) {
		var button = event.target.parentElement;
		var icon = button.querySelector('.icon-mail');

		try {
			button.disabled = true
			$(icon).addClass('icon-loading-small');

			$.post(OC.linkToOCS('apps/preferred_providers/api/v1', 2)+ 'reactivate', {userId: user.id}, function(response) {
				OC.Notification.showTemporary(t('preferred_providers', 'User enabled and verification email sent!'))
				$(icon).removeClass('icon-loading-small');
				button.disabled = false
			}, 'json');
		} catch(error) {
			OC.Notification.showTemporary(t('preferred_providers', 'Error while sending the verification email'))
			console.error(error)
		}
	}

	let registerFunction = function (delay) {
		if(OCA.Settings === undefined) {
			delay = delay * 2;
			if(delay === 0) {
				delay = 15;
			}
			if(delay > 500) {
				console.warn("Could not register resend activation mail action");
				return;
			}
			setTimeout(function() {registerFunction(delay)}, delay);
		} else {
			OCA.Settings.UserList.registerAction('resend_email icon-mail', t('preferred_providers', 'Resend verification email and enable'), sendEmail, (user) => !user.enabled && user.email)
		}
	};
	registerFunction(0);
})
