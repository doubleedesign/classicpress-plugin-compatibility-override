<?php

namespace Doubleedesign\ClassicPress\PluginCompatibility;

class ErrorPage {
	protected $default_handler;

	public function __construct() {
		add_action('error_page_enqueue_scripts', [$this, 'enqueue_assets']); // Custom action so our assets only load on the error page
		add_filter('wp_die_handler', [$this, 'custom_wp_die_handler'], 20, 3);
		add_filter('cp_force_activation_invitation_message', [$this, 'force_activation_invitation_message'], 10, 2);
	}

	/**
	 * Prepare CSS and JS for the error page
	 * @return void
	 */
	function enqueue_assets(): void {
		wp_enqueue_script('cp-force-activate', plugin_dir_url(__FILE__) . 'assets/overrider.js', ['jquery'], '1.0', true);
		wp_localize_script('cp-force-activate', 'cpForceActivate', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('cp-force-activate-nonce'),
		]);

		wp_enqueue_style('cp-force-activate-custom-error-page', plugin_dir_url(__FILE__) . 'assets/error-page.css', [], '1.0');
	}

	/**
	 * Override the default wp_die handler to show our customised error page
	 * @param $default
	 * @return array
	 */
	function custom_wp_die_handler($default): array {
		$this->default_handler = $default;
		return [$this, 'custom_error_page'];
	}

	/**
	 * The function to actually render the custom error page where applicable
	 * (or the default any other time)
	 * @param $message
	 * @param $title
	 * @param $args
	 * @return void
	 */
	function custom_error_page($message, $title, $args): void {
		if(isset($message->errors['plugin_wp_incompatible'])) {
			do_action('error_page_enqueue_scripts');
			do_action('admin_head');
			echo <<<HTML
				<!DOCTYPE html>
				<head>
					<meta http-equiv="Content-Type" content="text/html">
					<meta name="viewport" content="width=device-width">
					<title>$title</title>
				</head>
				<body id="error-page">
			HTML;
			do_action('admin_print_footer_scripts');
			call_user_func($this->default_handler, $message, $title, $args);
		}
		else {
			call_user_func($this->default_handler, $message, $title, $args);
		}
	}

	/**
	 * Modify the default error message to include a warning and a button to force activation
	 * (the filter this is added to should be used within conditional checks to ensure it only applies to the relevant error screens)
	 * @param $message
	 * @param $plugin
	 * @return string
	 */
	function force_activation_invitation_message($message, $plugin): string {
		$can_activate = current_user_can('activate_plugins');
		if(!$can_activate) {
			return $message; // User can't activate plugins, so no point showing the extra info
		}

		return <<<MESSAGE
			$message
			However, your developer has indicated that it should work on your site.
			Before activating, please ensure you have a recent backup of your site and a way to easily remove this plugin (e.g., FTP or cPanel access) just in case it triggers a fatal error.

			<p>
				<button type="button" class="button button-primary" data-action="force-activate-plugin" data-plugin="$plugin">
					Activate anyway
				</button>
			</p>
		MESSAGE;
	}
}
