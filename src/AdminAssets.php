<?php

namespace Doubleedesign\ClassicPress\PluginCompatibility;

class AdminAssets {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void {
        wp_enqueue_style(
            'classicpress-plugin-compatibility-admin',
            plugin_dir_url(dirname(__DIR__)) .
            'classicpress-plugin-compatibility-override/src/assets/admin.css',
            [],
            PluginEntryPoint::get_version());

        wp_enqueue_script(
            'classicpress-plugin-compatibility-admin',
            plugin_dir_url(dirname(__DIR__)) .
            'classicpress-plugin-compatibility-override/src/assets/admin.js',
            ['tippy'],
            PluginEntryPoint::get_version(),
            true
        );

        wp_enqueue_script('popper', 'https://unpkg.com/@popperjs/core@2', [], '2', true);
        wp_enqueue_script('tippy', 'https://unpkg.com/tippy.js@6', ['popper'], '6', true);

    }
}
