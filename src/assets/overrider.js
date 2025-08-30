/* global cpForceActivate */
jQuery(document).ready(function($) {

	const button = $('button[data-action="force-activate-plugin"]');
	button.on('click', function(event) {
		event.preventDefault();

		$.ajax({
			url: cpForceActivate.ajaxUrl,
			type: 'POST',
			data: {
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				},
				// This action name must match the PHP action hook, without the wp_ajax_ prefix
				action: 'cp_force_activate_plugin',
				nonce: cpForceActivate.nonce,
				body: JSON.stringify({
					plugin: button.data('plugin')
				})
			},
			success: function(response) {
				const data = JSON.parse(response.data);
				if(data.redirect) {
					window.location = data.redirect;
				}
			},
			error: function(error) {
				console.error(error);
				const data = JSON.parse(error.data);
				if(data.redirect) {
					window.location = data.redirect;
				}
			}
		});

	});
});
