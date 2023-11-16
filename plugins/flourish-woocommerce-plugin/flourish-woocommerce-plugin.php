<?php
 /**
 * Plugin Name: Flourish WooCommerce Plugin
 * Plugin URI: http://www.flourishsoftware.com/woocommerce-extension/
 * Description: A WooCommerce plugin for your Flourish data.
 * Version: 1.2.4
 * Author: Flourish Software
 * Author URI: https://www.flourishsoftware.com/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';

FlourishWooCommercePlugin\FlourishWooCommercePlugin::get_instance()->init(plugin_basename(__FILE__));
