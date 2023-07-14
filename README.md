# flourish-woocommerce

👋 Hello!

This repository is to hold the PHP code that's utilized for the [Flourish](https://www.flourishsoftware.com/) WooCommerce WordPress plugin.

Flourish is a software platform that provides services across the United States for cultivation, manufacturing, distribution, and retail sales of cannabis.

This plugin allows users of the Flourish platform to seamlessly integrate items, inventory, customers, and orders into a flexible and powerful website for business to business, or business to consumer sales.

We do this by leveraging the [Flourish External API](https://api-docs.flourishsoftware.com/).

## 🔗 Helpful Links

* Flourish Software: [https://www.flourishsoftware.com](https://www.flourishsoftware.com/)
* WordPress: [https://wordpress.org/](https://wordpress.org/)
* WooCommerce: [https://woocommerce.com/](https://woocommerce.com/)

## 🥅 Goals

The goals of the plugin are to:

* Sync item and item updates from Flourish to WooCommerce
* Sync inventory from Flourish to WooCommerce
* Sync orders and customers / destinations from Flourish to WooCommerce
* Sync order updates from Flourish to WooCommerce

This is to give Flourish users the ability to easily open an online store.

## 🏛 Architecture

This repository is made up of a few important pieces that are for getting this running locally:

* `docker-compose.yml` - the Docker file necessary for running things locally
* `plugins/flourish-woocommerce-plugin` - the actual plugin code
* `WordPress` - a checkout of the WordPress codebase that you will need to perform

## 💻 Developing Locally

1. Checkout this repository: `git clone git@bitbucket.org:wmsight/flourish-wordpress.git`
1. Go into the directory: `cd flourish-wordpress`
1. Checkout the WordPress GitHub clone: `git clone https://github.com/WordPress/WordPress.git`
1. Run the Docker container: `docker compose up`
    * This should give you a running local WordPress instance at [http://127.0.0.1:8080](http://127.0.0.1:8080) 
    * Complete the wordpress installation. `admin` / `admin` should work for the username and password
1. Install the WooCommerce plugin
1. Activate the Flourish WooCommerce Plugin
1. Access the settings and input your username and external API key
1. Select a facility and the type of orders you want to sync
1. Sync your items

## 🔌 Plugin Setup

For everything to work, and to get webhooks rolling, you'll want to get a few things setup.

1. Configure your WordPress installation to use "Post name" permalinks
    * Settings -> Permalinks -> Post name
1. Create webhooks that go to `domain.com/wp-json/flourish-woocommerce-plugin/v1/webhook`
    * Item
    * Retail Order
    * Outbound Order
    * Inventory Summary

## 🗂 Directory Structure
```
.
├── assets
│   └── js
│       └── flourish-woocommerce-plugin.js
├── composer.json
├── composer.lock
├── flourish-woocommerce-plugin.php
├── src
│   ├── API
│   │   ├── FlourishAPI.php
│   │   └── FlourishWebhook.php
│   ├── Admin
│   │   └── SettingsPage.php
│   ├── CustomFields
│   │   ├── DateOfBirth.php
│   │   ├── FlourishOrderID.php
│   │   └── License.php
│   ├── FlourishWooCommercePlugin.php
│   ├── Handlers
│   │   ├── HandlerOrdersOutbound.php
│   │   └── HandlerOrdersRetail.php
│   └── Importer
│       └── FlourishItems.php
└── vendor
    ├── autoload.php
    └── composer
        ├── ClassLoader.php
        ├── InstalledVersions.php
        ├── LICENSE
        ├── autoload_classmap.php
        ├── autoload_namespaces.php
        ├── autoload_psr4.php
        ├── autoload_real.php
        ├── autoload_static.php
        ├── installed.json
        ├── installed.php
        └── platform_check.php

11 directories, 26 files
```

* `composer.json` and `composer.lock` are files managed by [Composer](https://getcomposer.org/), the PHP package manager.
* `vendor` is where Composer packages and files used to manage automagically importing files are stored
* `flourish-woocommerce-plugin.php` is the main plugin file that loads everything
* `API` contains files for interacting with the Flourish API or for receiving webhooks
* `Admin` contains the code necessary for the settings page
* `CustomFields` contains code for putting custom fields throughout the application where necessary
    * Example: Date of Birth for retail orders or License for outbound orders
* `FlourishWooCommercePlugin.php` loads all the components and calls `register_hooks` on them
* `Handlers` for putting together orders to post to Flourish
* `Importer` for importing things; currently just items
* `assets/js` for the tiny amount of JavaScript (jQuery) that we use

## ⚙️ How it Works

Briefly, a WordPress plugin works by attaching to hooks throughout the application.

For example, sending a retail order to Flourish when an order is completed is a matter of:

```php
add_action('woocommerce_order_status_pending', [$this, 'handle_order_retail']);
```

Where `woocommerce_order_status_pending` is the name of the hook we're waiting for, `$this` refers to the current object, and `handle_order_retail` is the function we want to fire when an order hits a status of pending.

So, you'll see that there are numerous places where we're adding filters or actions too hook into various things occurring within the application or things being displayed / handled in the application as well.

## 🪪 License

Copyright (C) 2023 [Flourish Software](https://www.flourishsoftware.com)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).
