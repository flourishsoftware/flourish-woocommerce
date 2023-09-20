<?php
 /**
 * Plugin Name: Flourish WooCommerce Plugin
 * Plugin URI: http://www.flourishsoftware.com/woocommerce-extension/
 * Description: A WooCommerce plugin for your Flourish data.
 * Version: 1.1.1
 * Author: Flourish Software
 * Author URI: https://www.flourishsoftware.com/
 * Developer: Flourish Software
 * Developer URI: https://www.flourishsoftware.com/
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 *
 * Copyright (C) Flourish Software - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by ddrake@flourishsoftware.com, 2023
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';

FlourishWooCommercePlugin\FlourishWooCommercePlugin::get_instance()->init(plugin_basename(__FILE__));
