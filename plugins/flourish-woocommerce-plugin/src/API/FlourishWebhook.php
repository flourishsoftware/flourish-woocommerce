<?php

namespace FlourishWooCommercePlugin\API;

defined( 'ABSPATH' ) || exit;

use FlourishWooCommercePlugin\Importer\FlourishItems;
use WP_REST_Request;
use WP_REST_Server;
use WP_REST_Response;

class FlourishWebhook
{
    public $existing_settings;

    public function __construct($existing_settings)
    {
        $this->existing_settings = $existing_settings;
    }

    public function authenticate($request_body, $signature)
    {
        // Check if the request is coming from Flourish
        return $signature === hash_hmac('sha256', $request_body, $this->existing_settings['webhook_key']);
    }

    public function register_hooks()
    {
        add_action('rest_api_init', function() {
            register_rest_route('flourish-woocommerce-plugin/v1', '/webhook', [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_webhook'],
            ]);
        });
    }

    public function handle_webhook(WP_REST_Request $request)
    {
        $body = $request->get_body();
        $headers = $request->get_headers();

        if (!$this->authenticate($body, $headers['auth_signature'][0])) {
            return new WP_REST_Response(['message' => 'Could not verify signature.'], 403);
        }

        $body = json_decode($body, true);

        switch($body['resource_type']) {
            case 'item':
                // Check if this item is ecommerce active
                if (!$body['data']['ecommerce_active']) {
                    return new WP_REST_Response(['message' => 'Item is not ecommerce active. Not handling.'], 200);
                }

                // Check if this item has a sku
                if (!$body['data']['sku']) {
                    return new WP_REST_Response(['message' => 'Item does not have a SKU. Not handling.'], 200);
                }

                $brands = isset($this->existing_settings['brands']) ? $this->existing_settings['brands'] : [];
                $filter_brands = isset($this->existing_settings['filter_brands']) ? $this->existing_settings['filter_brands'] : false;
                if ($filter_brands && !in_array($body['data']['brand'], $brands)) {
                    return new WP_REST_Response(['message' => 'Item does not match brand filter. Not handling.'], 200);
                }

                $items = [];
                $items[] = $body['data'];
                
                // Grab the current inventory of this item
                $api_key = isset($this->existing_settings['api_key']) ? $this->existing_settings['api_key'] : '';
                $username = isset($this->existing_settings['username']) ? $this->existing_settings['username'] : '';
                $url = isset($this->existing_settings['url']) ? $this->existing_settings['url'] : '';
                $facility_id = isset($this->existing_settings['facility_id']) ? $this->existing_settings['facility_id'] : '';

                // Perform the API call to fetch the inventory of the item
                $flourish_api = new FlourishAPI($username, $api_key, $url, $facility_id);
                $inventory_records = $flourish_api->fetch_inventory($body['data']['id']);
                $inventory_quantity = 0;
                foreach ($inventory_records as $inventory) {
                    // There are item variations sometimes so we'll get more than one inventory record back
                    // for a single item. We only want the one that matches the SKU.
                    if ($inventory['sku'] === $body['data']['sku']) {
                        $inventory_quantity = $inventory['sellable_qty'];
                        break;
                    }
                }
                $items[0]['inventory_quantity'] = $inventory_quantity;

                $item_sync_options = isset($this->existing_settings['item_sync_options']) ? $this->existing_settings['item_sync_options'] : [];
                $flourish_items = new FlourishItems($items);
                $flourish_items->save_as_woocommerce_products($item_sync_options);
                break;
            case 'retail_order':
                $wc_order = wc_get_order($body['data']['original_order_id']);
                if (!$wc_order) {
                    return new WP_REST_Response(['message' => 'Order not found.'], 404);
                }
                switch($body['data']['order_status']) {
                    case 'Packed':
                    case 'Out for Delivery':
                    case 'Completed':
                        $wc_order->update_status('completed', 'Flourish order has been ' . $body['data']['order_status'] . '. Updated by API webhook.');
                        break;
                    case 'Cancelled':
                        $wc_order->update_status('cancelled', 'Flourish order has been cancelled. Updated by API webhook.');
                        break;
                    default:
                        $wc_order->update_status('created', 'Flourish order status has been updated to created. Updated by API webhook.');
                        break;
                }
                break;
            case 'order':
                $wc_order = wc_get_order($body['data']['original_order_id']);
                if (!$wc_order) {
                    return new WP_REST_Response(['message' => 'Order not found.'], 404);
                }

                switch($body['data']['order_status']) {
                    case 'Shipped':
                    case 'Completed':
                        $wc_order->update_status('completed', 'Flourish order has been ' . $body['data']['order_status'] . '. Updated by API webhook.');
                        break;
                    case 'Cancelled':
                        $wc_order->update_status('cancelled', 'Flourish order has been cancelled. Updated by API webhook.');
                        break;
                    default:
                        $wc_order->update_status('created', 'Flourish order status has been updated to created. Updated by API webhook.');
                        break;
                }
                break;
            case 'inventory_summary':
                $brands = isset($this->existing_settings['brands']) ? $this->existing_settings['brands'] : [];
                $filter_brands = isset($this->existing_settings['filter_brands']) ? $this->existing_settings['filter_brands'] : false;
                if ($filter_brands && !in_array($body['data']['brand'], $brands)) {
                    return new WP_REST_Response(['message' => 'Item does not match brand filter. Not handling.'], 200);
                }

                $wc_product = wc_get_products([
                    'sku' => $body['data']['sku'],
                    'limit' => 1,
                ]);

                if (empty($wc_product)) {
                    return new WP_REST_Response(['message' => 'Product not found.'], 404);
                }

                $wc_product = $wc_product[0];
                $wc_product->set_stock_quantity($body['data']['sellable_qty']);
                $wc_product->save();
                break;
            default:
                return new WP_REST_Response(['message' => 'Unknown resource type.'], 400);
        }

        return new WP_REST_Response(['message' => 'OK'], 200);
    }
}