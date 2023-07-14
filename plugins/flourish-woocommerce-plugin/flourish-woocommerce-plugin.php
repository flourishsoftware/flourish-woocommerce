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
 *     Copyright (C) 2023 Flourish Software
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';

FlourishWooCommercePlugin\FlourishWooCommercePlugin::get_instance()->init(plugin_basename(__FILE__));
