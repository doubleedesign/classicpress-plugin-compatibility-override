<?php
/**
 * Plugin Name: Plugin Compatibility Override for ClassicPress
 * Description: Allows developers to specify, on a per-site basis, that specific plugins are compatible with ClassicPress despite a WP version requirement mismatch.
 * Requires PHP: 8.3
 * Author: Double-E Design
 * Plugin URI: https://github.com/doubleedesign/classicpress-plugin-compatibility-override
 * Author URI: https://www.doubleedesign.com.au
 * Version: 0.0.1
 * Text domain: classicpress-plugin-compatibility
 */

include __DIR__ . '/vendor/autoload.php';
use Doubleedesign\ClassicPress\PluginCompatibility\PluginEntryPoint;

new PluginEntrypoint();

function activate_new_plugin(): void {
	PluginEntrypoint::activate();
}
function deactivate_new_plugin(): void {
	PluginEntrypoint::deactivate();
}
function uninstall_new_plugin(): void {
	PluginEntrypoint::uninstall();
}
register_activation_hook(__FILE__, 'activate_new_plugin');
register_deactivation_hook(__FILE__, 'deactivate_new_plugin');
register_uninstall_hook(__FILE__, 'uninstall_new_plugin');
