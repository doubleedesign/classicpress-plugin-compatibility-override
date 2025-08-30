<?php

namespace Doubleedesign\ClassicPress\PluginCompatibility;
use Exception;

class Overrider {
    private array $overrides = [];

    public function __construct() {
        add_action('admin_init', [$this, 'confirm_overrides'], 50, 0);
        add_filter('all_plugins', [$this, 'clear_wp_version_requirement_in_plugins_list']);
        add_action('wp_error_added', [$this, 'catch_activation_error'], 20, 4);
        add_action('wp_ajax_cp_force_activate_plugin', [$this, 'attempt_force_activation']);
        add_action('plugin_auto_update_setting_html', [$this, 'remove_auto_update_toggle'], 10, 3);
        add_action('init', [$this, 'disable_auto_update_for_overridden_plugins'], 50, 0); // this needs to run late to ensure it is the final decider
    }

    /**
     * Check the list of overrides to ensure they really do need overriding,
     * only adding those that do not pass built-in compatibility checks to the internal $overrides array
     *
     * @return void
     */
    public function confirm_overrides(): void {
        $overrides = apply_filters('cp_plugin_compatibility_overrides', []);
        foreach ($overrides as $plugin) {
            // If the plugin doesn't exist, skip
            if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                continue;
            }

            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
            $is_wp_compatible = isset($plugin_data['RequiresWP']) && version_compare(get_bloginfo('version'), $plugin_data['RequiresWP'], '>=');
            if (!$is_wp_compatible) {
                $this->overrides[] = $plugin;
            }
        }
    }

    /**
     * Clear the 'RequiresWP' field from specified plugins to allow activation in ClassicPress.
     *
     * @param  array  $plugins
     *
     * @return array
     */
    public function clear_wp_version_requirement_in_plugins_list(array $plugins): array {
        foreach ($this->overrides as $override) {
            if (isset($plugins[$override]['RequiresWP'])) {
                unset($plugins[$override]['RequiresWP']);
            }
        }

        return $plugins;
    }

    /**
     * When the expected activation error is thrown and directs to the wp_die handler screen,
     * we catch it here and modify the message to include more information (as set in ErrorPage.php)
     *
     * @param  $code
     * @param  $message
     * @param  $data
     * @param  $wp_error
     *
     * @return void
     */
    public function catch_activation_error($code, $message, $data, $wp_error): void {
        if ($code === 'plugin_wp_incompatible') {
            $plugin = $_GET['plugin'] ?? '';

            if (!in_array($plugin, $this->overrides)) {
                return; // Not one of our overrides, bail early
            }

            // Show the default message with extra info plus a link to force activation
            $default_message = $wp_error->errors[$code][0];
            $wp_error->errors[$code][0] = apply_filters('cp_force_activation_invitation_message', $default_message, $plugin);
        }
    }

    /**
     * Respond to AJAX request to force-activate a plugin
     * Temporarily clears the global $wp_version to bypass the check and try to activate, and then restore it
     *
     * @return void
     */
    public function attempt_force_activation(): void {
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }

        $request = $_POST;
        $data = json_decode(stripslashes($_POST['body']), true);
        $plugin = $data['plugin'] ?? null;
        if (!isset($plugin) || !isset($request['nonce']) || !wp_verify_nonce($request['nonce'], 'cp-force-activate-nonce')) {
            wp_send_json_error('Invalid request');
        }

        global $wp_version;
        $temp = $wp_version; // store so we can reset it
        $wp_version = "10"; // Set to a high value so the is_wp_version_compatible() function logic will pass
        global $wp_version;

        try {
            $result = activate_plugin($plugin);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            wp_send_json_success(json_encode([
                'message'  => 'Plugin activated successfully',
                'plugin'   => $plugin,
                'redirect' => admin_url('plugins.php')
            ]), 200);
            // TODO: This doesn't actually show after the redirect, so needs to be handled differently
            wp_admin_notice(
                'Plugin activated successfully',
                ['type' => 'success']
            );
        }
        catch (Exception $e) {
            wp_send_json_error(json_encode([
                'message'  => $e->getMessage(),
                'plugin'   => $plugin,
                'redirect' => admin_url('plugins.php')
            ]),
                500);
            // TODO: This doesn't actually show after the redirect, so needs to be handled differently
            wp_admin_notice(
                $e->getMessage(),
                ['type' => 'error']
            );
        }
        finally {
            // restore the global
            $wp_version = $temp;
            global $wp_version;
        }
    }

    public function remove_auto_update_toggle($html, $plugin_file, $plugin_data): void {
        if (in_array($plugin_file, $this->overrides)) {
            $tooltip = 'This plugin is not officially compatible with ClassicPress. All updates should be manually tested for compatibility before rolling out to your live site.';
            echo <<<HTML
				Auto-updates disabled
				<span class="cp-auto-update-disabled" data-tippy-content="$tooltip" style="cursor: help;" tabindex="0">
					<span class="dashicons dashicons-info"></span>
				</span>
			HTML;
        }
        else {
            echo $html;
        }
    }

    /**
     * If auto-updates somehow get enabled, disable them
     *
     * @return void
     */
    public function disable_auto_update_for_overridden_plugins(): void {
        $auto_updates = get_site_option('auto_update_plugins', []);
        $filtered = array_filter($auto_updates, function($plugin) {
            return !in_array($plugin, $this->overrides);
        }, ARRAY_FILTER_USE_KEY);

        update_site_option('auto_update_plugins', $filtered);
    }
}
