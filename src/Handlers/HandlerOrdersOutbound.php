<?php

namespace FlourishWooCommercePlugin\Handlers;

defined( 'ABSPATH' ) || exit;

use FlourishWooCommercePlugin\API\FlourishAPI;

class HandlerOrdersOutbound
{
    public $existing_settings;

    public function __construct($existing_settings)
    {
        $this->existing_settings = $existing_settings;
    }

    public function register_hooks()
    {
        add_action('woocommerce_order_status_pending', [$this, 'handle_order_outbound']);
        add_action('woocommerce_order_status_processing', [$this, 'handle_order_outbound']);
    }

    public function handle_order_outbound($order_id)
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

            $billing_address_object = new \StdClass();
            $billing_address_object->address_line_1 = $wc_order->get_billing_address_1();
            $billing_address_object->address_line_2 = $wc_order->get_billing_address_2();
            $billing_address_object->city = $wc_order->get_billing_city();
            $billing_address_object->state = $wc_order->get_billing_state();
            $billing_address_object->zip_code = $wc_order->get_billing_postcode();
            $billing_address_object->country = 'United States';

            $destination = [
                'type' => 'Dispensary',
                'name' => strlen($wc_order->get_shipping_company()) ? $wc_order->get_shipping_company() : $wc_order->get_billing_company(),
                'company_email' => $wc_order->get_billing_email(),
                'company_phone_number' => $wc_order->get_billing_phone(),
                'address_line_1' => strlen($wc_order->get_shipping_address_1()) ? $wc_order->get_shipping_address_1() : $wc_order->get_billing_address_1(),
                'address_line_2' => strlen($wc_order->get_shipping_address_2()) ? $wc_order->get_shipping_address_2() : $wc_order->get_billing_address_2(),
                'city' => strlen($wc_order->get_shipping_city()) ? $wc_order->get_shipping_city() : $wc_order->get_billing_city(),
                'state' => strlen($wc_order->get_shipping_state()) ? $wc_order->get_shipping_state() : $wc_order->get_billing_state(),
                'zip_code' => strlen($wc_order->get_shipping_postcode()) ? $wc_order->get_shipping_postcode() : $wc_order->get_billing_postcode(),
                'country' => 'United States',
                'license_number' => (isset($_POST['license']) && strlen($_POST['license'])) ? $_POST['license'] : get_user_meta($wc_order->get_user_id(), 'license', true),
                'billing' => $billing_address_object,
                'external_id' => $wc_order->get_user_id(),
            ];

            // Lookup the destination in Flourish by license number and use the ID of it if it exists
            $existing_destination = $flourish_api->fetch_destination_by_license($destination['license_number']);
            if ($existing_destination) {
                $destination['id'] = $existing_destination['id'];
            }

            $order_lines_array = [];
            foreach ($wc_order->get_items() as $item) {
                $product = wc_get_product($item->get_product_id());
                if (strlen($product->get_sku())) {
                    $order_line = new \StdClass();
                    $order_line->sku = $product->get_sku();
                    $order_line->order_qty = $item->get_quantity();
                    $order_lines_array[] = $order_line;
                }
            }

            if (count($order_lines_array) == 0) {
                wc_get_logger()->error("No order lines found for order " . $wc_order->get_id(), ['source' => 'flourish-woocommerce-plugin']);
                return;
            }

            $notes = [];
            if (strlen($wc_order->get_customer_note())) {
                $note = new \StdClass();
                $note->subject = "Customer Note from WooCommerce";
                $note->note = $wc_order->get_customer_note();
                $note->internal_only = false;
                $notes[] = $note;
            }

            $order = [
                'original_order_id' => (string)$wc_order->get_id(),
                'order_lines' => $order_lines_array,
                'destination' => $destination,
                'order_timestamp' => gmdate("Y-m-d\TH:i:s.v\Z"),
                'notes' => $notes,
            ];

            $facility_config = $flourish_api->fetch_facility_config($facility_id);
            if (empty($facility_config)) {
                throw new \Exception("No facility config found for facility ID: " . $facility_id);
            }

            if ($facility_config['sales_rep_required_for_outbound']) {
                $sales_rep_id = $this->existing_settings['sales_rep_id'];
                if (!$sales_rep_id) {
                    throw new \Exception("Sales rep is required for outbound orders for this facility");
                }

                $sales_rep = new \StdClass();
                $sales_rep->id = $sales_rep_id;
                $order['sales_rep'] = $sales_rep;
            }

            $flourish_order_id = $flourish_api->create_outbound_order($order);
            $wc_order->update_meta_data('flourish_order_id', $flourish_order_id);
            $wc_order->save();
        } catch (\Exception $e) {
            // Set an admin notice to show the error message.
            wc_get_logger()->error(
                "Error creating outbound order: " . $e->getMessage(), 
                ['source' => 'flourish-woocommerce-plugin']
            );
        }
    }
}
