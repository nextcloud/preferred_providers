$('#show').change(function() {
    $('#password').attr('type', $('#show').is(':checked')?'text':'password');
});