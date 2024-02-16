<?php

namespace FlourishWooCommercePlugin\Handlers;

defined( 'ABSPATH' ) || exit;

class HandlerOrdersSyncNow
{
    public $existing_settings;

    public function __construct($existing_settings)
    {
        $this->existing_settings = $existing_settings;
    }

    public function register_hooks()
    {
        add_action('woocommerce_order_actions', [$this, 'add_sync_now_action']);
        add_action('woocommerce_order_action_sync_order_now', [$this, 'sync_order_now']);
    }

    public function add_sync_now_action($actions)
    {
        $actions['sync_order_now'] = 'Sync order to Flourish';

        return $actions;
    }

    public function sync_order_now($order)
    {
        $order_id = $order->get_id();

        if ($order->get_meta('flourish_order_id')) {
            // We've already created this order in Flourish, so we don't need to do anything.
            return;
        }

        if (!$this->existing_settings) {
            // We don't have any Flourish settings
            return;
        }   

        $order_type = isset($this->existing_settings['flourish_order_type']) ? $this->existing_settings['flourish_order_type'] : false;  
        if (!$order_type) {
            // We don't have an order type set
            return;
        }   

        if ($order_type === 'retail') {
            $handler_orders_retail = new HandlerOrdersRetail($this->existing_settings);
            $handler_orders_retail->handle_order_retail($order_id);
        } else {
            $handler_orders_outbound = new HandlerOrdersOutbound($this->existing_settings);
            $handler_orders_outbound->handle_order_outbound($order_id);
        }

        return;
    }
}