$('#token-reset:not(:disabled)').click(_.debounce(function() {
	$('#token-reset').attr('disabled', true);
	$('#token').addClass('icon-loading');

	$.get(OC.linkToOCS('apps/preferred_providers/api/v1/token', 2)+ 'new', function(response) {
		$('#token').val(response.ocs.data.token);
		$('#token-reset').attr('disabled', false);
		$('#token').removeClass('icon-loading');
	}, 'json');
}, 250));

$('#groups:not(:disabled)').change(_.debounce(function() {
	$('#groups').attr('disabled', true).addClass('icon-loading');
	var groups = $('#groups').val();
	$.post(OC.linkToOCS('apps/preferred_providers/api/v1', 2)+ 'groups', {groups: groups}, function(response) {
		$('#groups').attr('disabled', false).removeClass('icon-loading');
	}, 'json');
}, 250));
