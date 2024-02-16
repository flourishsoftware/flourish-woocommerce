<?php

namespace FlourishWooCommercePlugin;

defined( 'ABSPATH' ) || exit;

use FlourishWooCommercePlugin\Admin\SettingsPage;
use FlourishWooCommercePlugin\API\FlourishWebhook;
use FlourishWooCommercePlugin\CustomFields\DateOfBirth;
use FlourishWooCommercePlugin\CustomFields\FlourishOrderID;
use FlourishWooCommercePlugin\CustomFields\License;
use FlourishWooCommercePlugin\Handlers\HandlerOrdersOutbound;
use FlourishWooCommercePlugin\Handlers\HandlerOrdersRetail;
use FlourishWooCommercePlugin\Handlers\HandlerOrdersSyncNow;

class FlourishWooCommercePlugin
{
    private static $instance;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init($plugin_basename)
    {
        register_activation_hook(plugin_basename(__FILE__), [$this, 'activate']);
        register_deactivation_hook(plugin_basename(__FILE__), [$this, 'deactivate']);
        register_uninstall_hook(plugin_basename(__FILE__), [$this, 'uninstall']);

        $existing_settings = get_option('flourish_woocommerce_plugin_settings');

        if (!$existing_settings) {
            $existing_settings = [];
        }

        // Register the settings page.
        $settings_page = new SettingsPage($existing_settings, $plugin_basename);
        $settings_page->register_hooks();

        // Register the custom fields and order handler based on the order type.
        if (!isset($existing_settings['flourish_order_type']) || $existing_settings['flourish_order_type'] === 'retail') {
            $custom_field_date_of_birth = new DateOfBirth();
            $custom_field_date_of_birth->register_hooks();

            $handler_orders_retail = new HandlerOrdersRetail($existing_settings);
            $handler_orders_retail->register_hooks();
        } else {
            $custom_field_license = new License();
            $custom_field_license->register_hooks();

            $handler_orders_outbound = new HandlerOrdersOutbound($existing_settings);
            $handler_orders_outbound->register_hooks();
        }

        // Register the webhook handler.
        $flourish_webhook = new FlourishWebhook($existing_settings);
        $flourish_webhook->register_hooks();

        // Register the Flourish ID field on the order page.
        $custom_field_flourish_id = new FlourishOrderID();
        $custom_field_flourish_id->register_hooks();

        // Register the custom order handler to sync orders on demand.
        $handler_orders_sync_now = new HandlerOrdersSyncNow($existing_settings);
        $handler_orders_sync_now->register_hooks();

        // Add our JavaScript
        add_action('admin_enqueue_scripts', function() {
            wp_enqueue_script(
                'flourish-woocommerce-plugin', 
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/flourish-woocommerce-plugin.js', 
                ['jquery'], 
                '1.0.0', 
                true
            );
        });
    }

    public function activate()
    {
    }

    public function deactivate()
    {
    }

    public function uninstall()
    {
    }
}
