<?php

namespace FlourishWooCommercePlugin\Importer;

defined( 'ABSPATH' ) || exit;

use WC_Product_Simple;

class FlourishItems
{
    public $items = [];

    public function __construct($items) 
    {
        $this->items = $items;
    }

    public function map_items_to_woocommerce_products()
    {
        if (!count($this->items)) {
            throw new \Exception("No items to map.");
        }

        return array_map([$this, 'map_flourish_item_to_woocommerce_product'], $this->items);
    }

    public function save_as_woocommerce_products($item_sync_options = [])
    {
        $imported_count = 0;

        foreach ($this->map_items_to_woocommerce_products() as $product) {
            if (!strlen($product['sku'])) {
                continue;
            }
            // Check if a product with the same SKU already exists.
            $existing_products = wc_get_products([
                'sku' => $product['sku'],
                'limit' => 1,
            ]);

            $new_product = false;
            if (!empty($existing_products)) {
                $wc_product = $existing_products[0];
            } else {
                $wc_product = new WC_Product_Simple();
                $new_product = true;
            }

            if ($new_product || (isset($item_sync_options['name']) && $item_sync_options['name'] == 1)) {
                $wc_product->set_name($product['name']);
            }

            if ($new_product || (isset($item_sync_options['description']) && $item_sync_options['description'] == 1)) {
                $wc_product->set_description($product['description']);
            }

            if ($new_product || (isset($item_sync_options['price']) && $item_sync_options['price'] == 1)) {
                $wc_product->set_price($product['price']);
                $wc_product->set_regular_price($product['price']);
            }

            $wc_product->set_sku($product['sku']);

            // Save the flourish_item_id as a custom meta field using WooCommerce's built-in method.
            $wc_product->update_meta_data('flourish_item_id', $product['flourish_item_id']);

            // Enable stock management.
            $wc_product->set_manage_stock(true);

            // Save the inventory quantity.
            $wc_product->set_stock_quantity($product['inventory_quantity']);

            $product_id = $wc_product->save();

            // Save the category information.
            if ($new_product || (isset($item_sync_options['categories']) && $item_sync_options['categories'] == 1 && !empty($product['item_category']))) {
                $term = term_exists($product['item_category'], 'product_cat');

                if (!$term) {
                    $term = wp_insert_term($product['item_category'], 'product_cat');
                }

                if (!is_wp_error($term)) {
                    $term_id = $term['term_id'] ?? $term['term_taxonomy_id'];
                    wp_set_object_terms($product_id, (int)$term_id, 'product_cat');
                } else {
                    throw new \Exception("Error inserting category term.");
                }
            }

            do_action('flourish_item_imported', $product, $product_id);

            if ($product_id > 0) {
                $imported_count++;
            }
        }

        return $imported_count;
    }

    private function map_flourish_item_to_woocommerce_product($flourish_item)
    {
        return [
            'flourish_item_id' => $flourish_item['id'],
            'item_category' => $flourish_item['item_category'],
            'name' => $flourish_item['item_name'],
            'description' => $flourish_item['item_description'],
            'sku' => $flourish_item['sku'],
            'price' => $flourish_item['price'],
            'inventory_quantity' => $flourish_item['inventory_quantity'],
        ];
    }
}