<?php

namespace FlourishWooCommercePlugin\Handlers;

defined( 'ABSPATH' ) || exit;

use FlourishWooCommercePlugin\API\FlourishAPI;

class HandlerOrdersRetail
{
    public $existing_settings;

    public function __construct($existing_settings)
    {
        $this->existing_settings = $existing_settings;
    }

    public function register_hooks()
    {
        add_action('woocommerce_order_status_pending', [$this, 'handle_order_retail']);
        add_action('woocommerce_order_status_processing', [$this, 'handle_order_retail']);

        // Make the company name mandatory
        add_filter('woocommerce_default_address_fields', [$this, 'require_company_field']);
    }

    public function handle_order_retail($order_id)
    {
        try {
            $wc_order = wc_get_order($order_id);

            if ($wc_order->get_meta('flourish_order_id')) {
                // We've already created this order in Flourish, so we don't need to do anything.
                return;
            }

            // Retrieve the saved settings.
            $api_key = isset($this->existing_settings['api_key']) ? $this->existing_settings['api_key'] : '';
            $username = isset($this->existing_settings['username']) ? $this->existing_settings['username'] : '';
            $url = isset($this->existing_settings['url']) ? $this->existing_settings['url'] : '';
            $facility_id = isset($this->existing_settings['facility_id']) ? $this->existing_settings['facility_id'] : '';

            // Perform the API call to post the order
            $flourish_api = new FlourishAPI($username, $api_key, $url, $facility_id);

            $address_object = new \StdClass();
            $address_object->address_line_1 = $wc_order->get_billing_address_1();
            $address_object->address_line_2 = $wc_order->get_billing_address_2();
            $address_object->city = $wc_order->get_billing_city();
            $address_object->state = $wc_order->get_billing_state();
            $address_object->postcode = $wc_order->get_billing_postcode();
            $address_object->country = $wc_order->get_billing_country();
            $address_object->type = 'billing';

            $address_array = [];
            $address_array[] = $address_object;

            $customer = [
                'first_name' => $wc_order->get_billing_first_name(),
                'last_name' => $wc_order->get_billing_last_name(),
                'email' => $wc_order->get_billing_email(),
                'phone' => $wc_order->get_billing_phone(),
                'dob' => (isset($_POST['dob']) && strlen($_POST['dob'])) ? $_POST['dob'] : get_user_meta($wc_order->get_user_id(), 'dob', true),
                'address' => $address_array,
            ];

            $customer = $flourish_api->get_or_create_customer_by_email($customer);

            $order_lines_array = [];
            foreach ($wc_order->get_items() as $item) {
                $order_line = new \StdClass();
                $product = wc_get_product($item->get_product_id());
                $order_line->sku = $product->get_sku();
                $order_line->order_qty = $item->get_quantity();
                // Use get_total instead of price to account for discounts
                $order_line->unit_price = (float)$item->get_total();
                $order_lines_array[] = $order_line;
            }

            // Now we have the flourish_customer_id, we can create the order
            $order = [
                'original_order_id' => (string)$wc_order->get_id(),
                'customer_id' => $customer['flourish_customer_id'],
                'fulfillment_type' => 'pickup',
                'order_lines' => $order_lines_array,
                'notes' => $wc_order->get_customer_note(),
            ];

            $flourish_order_id = $flourish_api->create_retail_order($order);
            $wc_order->update_meta_data('flourish_order_id', $flourish_order_id);
            $wc_order->save();
        } catch (\Exception $e) {
            wc_get_logger()->error("Error creating retail order: " . $e->getMessage(), ['source' => 'flourish-woocommerce-plugin']);
        }
    }

    public function require_company_field($fields)
    {
        $fields['company']['required'] = true;
        return $fields;
    }
}
