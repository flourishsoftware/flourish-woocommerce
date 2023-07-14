<?php
 /**
 * Plugin Name: Flourish WooCommerce Plugin
 * Plugin URI: http://www.flourishsoftware.com/woocommerce-extension/
 * Description: A WooCommerce plugin for your Flourish data.
 * Version: 1.1.0
 * Author: Flourish Software
 * Author URI: https://www.flourishsoftware.com/
 * Developer: Flourish Software
 * Developer URI: https://www.flourishsoftware.com/
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 *
 * Written by ddrake@flourishsoftware.com, 2023
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';

FlourishWooCommercePlugin\FlourishWooCommercePlugin::get_instance()->init(plugin_basename(__FILE__));
