<?php

namespace FlourishWooCommercePlugin\Admin;

defined( 'ABSPATH' ) || exit;

use FlourishWooCommercePlugin\API\FlourishAPI;
use FlourishWooCommercePlugin\Importer\FlourishItems;

class SettingsPage
{
    public $plugin_basename;
    public $existing_settings;

    public function __construct($existing_settings, $plugin_basename)
    {
        $this->existing_settings = $existing_settings ? $existing_settings : [];
        $this->plugin_basename = $plugin_basename;
    }

    public function register_hooks()
    {
        // Get the settings page to show up in the admin menu
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'add_settings_link']);

        // Handling importing products button being pushed
        if (isset($_POST['action']) && $_POST['action'] === 'import_products') {
            add_action('admin_init', [$this, 'handle_import_products_form_submission']);
        }
    }

    public function add_settings_page()
    {
        add_options_page(
            'Flourish WooCommerce Plugin Settings',
            '🌱 Flourish',
            'manage_options',
            'flourish-woocommerce-plugin-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting('flourish-woocommerce-plugin-settings-group', 'flourish_woocommerce_plugin_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [],
            'show_in_rest' => false,
        ]);

        add_settings_section(
            'flourish_woocommerce_plugin_section', 
            '⚙️ Settings from Flourish',
            null,
            'flourish-woocommerce-plugin-settings',
        );

        // Do our regular text input based settings
        $settings = [
            'username' => 'Username',
            'api_key' => 'External API Key',
            'url' => 'API URL',
            'webhook_key' => 'Webhook Signing Key',
        ];

        foreach ($settings as $key => $label) {
            $setting_value = isset($this->existing_settings[$key]) ? $this->existing_settings[$key] : '';
            add_settings_field(
                $key, 
                $label, 
                function() use ($key, $setting_value) {
                    $this->render_setting_field($key, $setting_value);
                }, 
                'flourish-woocommerce-plugin-settings', 
                'flourish_woocommerce_plugin_section',
            );
        }

        if (empty($this->existing_settings['username']) || empty($this->existing_settings['api_key'])) {
            $facilities = [];
        } else {
            try {
                $facilities = $this->get_facilities();
            } catch (\Exception $e) {
                // Show a dismissable error message with the admin notice
                add_action('admin_notices', function() use ($e) {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php echo $e->getMessage(); ?></p>
                    </div>
                    <?php
                });
            }
        }

        $setting_value = isset($this->existing_settings['facility_id']) ? $this->existing_settings['facility_id'] : '';

        add_settings_field(
            'facility_id',
            'Facility',
            function() use ($setting_value, $facilities) {
                $this->render_facility_id($setting_value, $facilities);
            },
            'flourish-woocommerce-plugin-settings',
            'flourish_woocommerce_plugin_section',
        );

        // Add our radio button settings for Flourish order type
        $setting_value = isset($this->existing_settings['flourish_order_type']) ? $this->existing_settings['flourish_order_type'] : '';

        add_settings_field(
            'flourish_order_type',
            'Order Type',
            function() use ($setting_value) {
                $this->render_flourish_order_type($setting_value);
            },
            'flourish-woocommerce-plugin-settings',
            'flourish_woocommerce_plugin_section',
        );

        $item_sync_options = isset($this->existing_settings['item_sync_options']) ? $this->existing_settings['item_sync_options'] : [];

        add_settings_field(
            'item_sync_options',
            'Item Sync Options',
            function() use ($item_sync_options) {
                $this->render_item_sync_options($item_sync_options);
            },
            'flourish-woocommerce-plugin-settings',
            'flourish_woocommerce_plugin_section',
        );

        // Get our brands
        if (empty($this->existing_settings['username']) || empty($this->existing_settings['api_key'])) {
            $brands = [];
        } else {
            try {
                $brands = $this->get_brands();
            } catch (\Exception $e) {
                // Show a dismissable error message with the admin notice
                add_action('admin_notices', function() use ($e) {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php echo $e->getMessage(); ?></p>
                    </div>
                    <?php
                });
            }
        }

        // Fetch the saved brands from the settings.
        $saved_brands = isset($this->existing_settings['brands']) ? $this->existing_settings['brands'] : [];
        $filter_brands = isset($this->existing_settings['filter_brands']) ? $this->existing_settings['filter_brands'] : false;

        // Add a settings field for brand checkboxes.
        add_settings_field(
            'brands',
            'Filter Brands',
            function() use ($filter_brands, $saved_brands, $brands) {
                $this->render_brands($filter_brands, $saved_brands, $brands);
            },
            'flourish-woocommerce-plugin-settings',
            'flourish_woocommerce_plugin_section'
        );
    }

    public function sanitize_settings($settings)
    {
        $sanitized_settings = [];
        $sanitized_settings['api_key'] = !empty($settings['api_key']) ? sanitize_text_field($settings['api_key']) : '';
        $sanitized_settings['username'] = !empty($settings['username']) ? sanitize_text_field($settings['username']) : '';
        $sanitized_settings['facility_id'] = !empty($settings['facility_id']) ? sanitize_text_field($settings['facility_id']) : '';
        $sanitized_settings['webhook_key'] = !empty($settings['webhook_key']) ? sanitize_text_field($settings['webhook_key']) : '';

        // Default to retail
        $sanitized_settings['flourish_order_type'] = !empty($settings['flourish_order_type']) ? sanitize_text_field($settings['flourish_order_type']) : 'retail';

        // Default to production API
        $sanitized_settings['url'] = !empty($settings['url']) ? esc_url_raw($settings['url']) : 'https://app.flourishsoftware.com';

        // Sanitize the brands
        $sanitized_settings['brands'] = !empty($settings['brands']) ? array_map('sanitize_text_field', $settings['brands']) : [];

        // Sanitize the filter brands
        $sanitized_settings['filter_brands'] = !empty($settings['filter_brands']) ? sanitize_text_field($settings['filter_brands']) : false;

        // Sanitize the item sync options
        if (!isset($settings['item_sync_options'])) {
            $sanitized_settings['item_sync_options'] = [
                'name' => 0,
                'description' => 0,
                'price' => 0,
                'categories' => 0,
            ];
        } else {
            $sanitized_settings['item_sync_options'] = array_map('sanitize_text_field', $settings['item_sync_options']);
        }

        return $sanitized_settings;
    }

    public function render_setting_field($key, $setting_value)
    {
        $input_type = 'text';
        $readonly = '';
        if ($key === 'url') {
            $input_type = 'url';

            if (empty($setting_value)) {
                $setting_value = 'https://app.flourishsoftware.com';
            }
        } elseif ($key === 'api_key' && strlen($setting_value)) {
            $input_type = 'password';
        } elseif ($key === 'webhook_key') {
            $readonly = 'readonly';
            if (!isset($this->existing_settings['username']) || !strlen($this->existing_settings['username']) || !isset($this->existing_settings['api_key']) || !strlen($this->existing_settings['api_key'])) {
                $setting_value = 'Provide your Username and API key';
            } else {
                $setting_value = sha1(sha1($this->existing_settings['username']) . sha1($this->existing_settings['api_key']));
            }
        }

        ?>
        <input type="<?php echo $input_type; ?>" id="<?php echo $key; ?>" name="flourish_woocommerce_plugin_settings[<?php echo $key; ?>]" value="<?php echo esc_attr($setting_value); ?>" size="42" <?php echo $readonly; ?>/>
        <?php
        if ($key === 'webhook_key') {
            ?>
            <?php
        }
    }

    public function render_flourish_order_type($setting_value)
    {
        ?>
        <input type="radio" id="flourish_order_type_retail" name="flourish_woocommerce_plugin_settings[flourish_order_type]" value="retail" <?php checked($setting_value, 'retail'); ?> <?php checked($setting_value, ''); ?> />
        <label for="flourish_order_type_retail">Retail</label>
        <p class="description">Orders will be created in Flourish as retail orders from customers.</p>
        <ul>
            <li>• Facility must be of type "retail"</li>
            <li>• Date of birth will be required and collected from the customer</li>
        </ul>
        <br>
        <input type="radio" id="flourish_order_type_outbound" name="flourish_woocommerce_plugin_settings[flourish_order_type]" value="outbound" <?php checked($setting_value, 'outbound'); ?> />
        <label for="flourish_order_type_outbound">Outbound</label>
        <p class="description">Orders will be created in Flourish as outbound orders from destinations.</p>
        <ul><li>• License will be required and collected from the destination</li></ul>
        <?php
    }

    public function render_settings_page()
    {
        $import_products_button_active = true;
        foreach (['username', 'api_key', 'facility_id', 'url'] as $required_setting) {
            if (!isset($this->existing_settings[$required_setting]) || !strlen($this->existing_settings[$required_setting])) {
                $import_products_button_active = false;
                break;
            }
        }

        if (isset($this->existing_settings['filter_brands']) && $this->existing_settings['filter_brands'] && !count($this->existing_settings['brands'])) {
            $import_products_button_active = false;
        }

        if (isset($_SERVER['HTTPS']) &&
            ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        $site_url = $protocol . $_SERVER['HTTP_HOST'];
        ?>
        <div class="wrap">
            <h1>Flourish WooCommerce Plugin Settings</h1>
            <p>
                Retrieve your Username and External API Key from Flourish. <a href="https://docs.flourishsoftware.com/article/xsefgb8b0s-external-api-generate-or-reset-api-key">Generate or Reset External API key</a>
            </p>
            <h2>🪝 Webhooks</h2>
            <p class="description">
                More information about webhooks in Flourish can be found here: <a href="https://docs.flourishsoftware.com/article/am15rjpmvg-flourish-webhooks">Flourish Webhooks</a>.
            </p>
            <p class="description">
                More information about securing webhooks with a signing key in Flourish can be found here: <a href="https://docs.flourishsoftware.com/article/gr4ipg7jcv-securing-your-webhooks">Securing your webhooks</a>.
            </p>
            <p>
                1. Configure Wordpress so that your Webhook endpoints are available by using "Post name" permalinks. 
                <ul>
                    <li>• Settings → Permalinks → Post name.</li>
                </ul>
            </p>
            <p>
                2. Configure your Flourish webhooks so that Flourish can communicate with your shop. You need to create them for:
            </p>
            <ul>
                <li>• Item</li>
                <li>• Retail Order</li>
                <li>• Outbound Order</li>
                <li>• Inventory Summary</li>
            </ul>
            <p>
                <strong>🔗 Endpoint URL:</strong> <span style="font-family: 'Courier New', Courier, monospace; white-space: nowrap;"><?php echo $site_url; ?>/wp-json/flourish-woocommerce-plugin/v1/webhook</span>
            </p>
            <p>
                <strong>🔑 Signing Key:</strong> Copy the key generated from "Webhook Signing Key" below as your "Signing Key" in Flourish. "Save Changes" here when complete.
            </p>
            <form method="post" action="options.php">
                <?php
                settings_fields('flourish-woocommerce-plugin-settings-group');
                do_settings_sections('flourish-woocommerce-plugin-settings');
                submit_button();
                ?>
            </form>

            <hr>

            <div class="wrap">
                <h2>↔️ Import Flourish Items to WooCommerce Products</h2>
                <p class="description">Once you have provided your username and external API key above, use this button to import items from the Flourish API into WooCommerce products.</p>
                <br>
                <form method="post">
                    <?php wp_nonce_field('flourish-woocommerce-plugin-import-products', 'import_nonce'); ?>
                    <input type="hidden" name="action" value="import_products">
                    <input type="submit" id="import-products" class="button button-primary" value="Import Products" <?php echo $import_products_button_active ? '' : 'disabled'; ?>>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_facility_id($setting_value, $facilities)
    {
        $disabled = '';
        $message = 'Select a Flourish facility to sync with';
        if (!isset($this->existing_settings['username']) || !strlen($this->existing_settings['username']) || !isset($this->existing_settings['api_key']) || !strlen($this->existing_settings['api_key'])) {
            $disabled = 'disabled';
            $message = 'Provide your Username and API key to select a facility.';
        } 
        ?>
        <select id="facility_id" name="flourish_woocommerce_plugin_settings[facility_id]" <?php echo $disabled; ?> width="50px">
            <option value=""><?php echo $message; ?></option>
            <?php foreach ($facilities as $facility) : ?>
                <option value="<?php echo $facility['id']; ?>" <?php selected($setting_value, $facility['id']); ?>><?php echo sprintf('%s - %s', $facility['facility_name'], $facility['facility_type']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_brands($filter_brands, $saved_brands, $brands) 
    {
        ?>
        <label>
            <input id="flourish-woocommerce-plugin-filter-brands" type="checkbox" name="flourish_woocommerce_plugin_settings[filter_brands]" value="1" <?php checked($filter_brands); ?>>
            Filter brands to sync with WooCommerce
        </label>
        <br>
        <br>
        <div id="flourish-woocommerce-plugin-brand-selection"<?php echo $filter_brands ? '' : ' style="display: none;"'; ?>>
            <p class="description">Select the brands you would like to sync with WooCommerce.</p>
            <br>
            <?php
            // For each brand, render a checkbox.
            foreach ($brands as $brand) {
                $brand_name = $brand['brand_name'];
                ?>
                <label>
                    <input type="checkbox" name="flourish_woocommerce_plugin_settings[brands][]" value="<?php echo esc_attr($brand_name); ?>" <?php checked(empty($saved_brands) || in_array($brand_name, $saved_brands)); ?>>
                    <?php echo esc_html($brand_name); ?>
                </label><br>
                <?php
            }
        ?>
        </div>
        <?php
    }

    // Render item sync options for: name, description, price, and categories
    public function render_item_sync_options($item_sync_options)
    {
        if (empty($item_sync_options)) {
            $item_sync_options = [
                'name' => 1,
                'description' => 1,
                'price' => 1,
                'categories' => 1
            ];
        }
        ?>
        <p class="description">Select the item data you would like to sync from Flourish to WooCommerce.<br><em>This will overwrite WooCommerce data on product import or update.</em></p>
        <br>
        <label>
            <input type="checkbox" name="flourish_woocommerce_plugin_settings[item_sync_options][name]" value="1" <?php checked($item_sync_options['name']); ?>>
            Names
        </label>
        <br>
        <label>
            <input type="checkbox" name="flourish_woocommerce_plugin_settings[item_sync_options][description]" value="1" <?php checked($item_sync_options['description']); ?>>  
            Descriptions
        </label>
        <br>
        <label>
            <input type="checkbox" name="flourish_woocommerce_plugin_settings[item_sync_options][price]" value="1" <?php checked($item_sync_options['price']); ?>>
            Prices
        </label>
        <br>
        <label>
            <input type="checkbox" name="flourish_woocommerce_plugin_settings[item_sync_options][categories]" value="1" <?php checked($item_sync_options['categories']); ?>>
            Categories
        </label>
        <?php
    }

    public function add_settings_link($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=flourish-woocommerce-plugin-settings'),
            __('Settings', 'flourish-woocommerce-plugin')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    public function handle_import_products_form_submission()
    {
        // Check the nonce for security.
        check_admin_referer('flourish-woocommerce-plugin-import-products', 'import_nonce');

        // Check if the user has the necessary capability.
        if (!current_user_can('manage_options')) {
            wp_die('You do not have the necessary permissions to import products.');
        }

        // Call the import_products method.
        try {
            $imported_count = $this->import_products();

            add_action('admin_notices', function () use ($imported_count) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><span style="color: #00a32a;"><strong>Success!</strong></span> <?php _e($imported_count . ' Flourish items successfully synced with WooCommerce products.'); ?></p>
                </div>
                <?php
            });
        } catch (\Exception $e) {
            add_action('admin_notices', function () use ($e) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('An error has occurred attempting to import the items: ' . $e->getMessage()); ?></p>
                </div>
                <?php
            });
        }
    }

    public function import_products()
    {
        // Retrieve the saved settings.
        $api_key = isset($this->existing_settings['api_key']) ? $this->existing_settings['api_key'] : '';
        $username = isset($this->existing_settings['username']) ? $this->existing_settings['username'] : '';
        $url = isset($this->existing_settings['url']) ? $this->existing_settings['url'] : '';
        $facility_id = isset($this->existing_settings['facility_id']) ? $this->existing_settings['facility_id'] : '';
        $brands = isset($this->existing_settings['brands']) ? $this->existing_settings['brands'] : [];
        $filter_brands = isset($this->existing_settings['filter_brands']) ? $this->existing_settings['filter_brands'] : false;
        $item_sync_options = isset($this->existing_settings['item_sync_options']) ? $this->existing_settings['item_sync_options'] : [];

        // Perform the API call to fetch the products.
        $flourish_api = new FlourishAPI($username, $api_key, $url, $facility_id);
        $flourish_items = new FlourishItems($flourish_api->fetch_products($filter_brands, $brands));

        // Import the products into WooCommerce.
        $imported_count = $flourish_items->save_as_woocommerce_products($item_sync_options);

        return $imported_count;
    }

    public function get_facilities()
    {
        $api_key = isset($this->existing_settings['api_key']) ? $this->existing_settings['api_key'] : '';
        $username = isset($this->existing_settings['username']) ? $this->existing_settings['username'] : '';
        $url = isset($this->existing_settings['url']) ? $this->existing_settings['url'] : '';
        $facility_id = isset($this->existing_settings['facility_id']) ? $this->existing_settings['facility_id'] : '';

        $flourish_api = new FlourishAPI($username, $api_key, $url, $facility_id);

        return $flourish_api->fetch_facilities();
    }

    public function get_brands()
    {
        $api_key = isset($this->existing_settings['api_key']) ? $this->existing_settings['api_key'] : '';
        $username = isset($this->existing_settings['username']) ? $this->existing_settings['username'] : '';
        $url = isset($this->existing_settings['url']) ? $this->existing_settings['url'] : '';
        $facility_id = isset($this->existing_settings['facility_id']) ? $this->existing_settings['facility_id'] : '';

        $flourish_api = new FlourishAPI($username, $api_key, $url, $facility_id);

        return $flourish_api->fetch_brands();
    }
}
