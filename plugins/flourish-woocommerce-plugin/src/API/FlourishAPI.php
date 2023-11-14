<?php

namespace FlourishWooCommercePlugin\API;

defined( 'ABSPATH' ) || exit;

class FlourishAPI 
{
    const API_LIMIT = 50;

    public $username;
    public $api_key;
    public $url;
    public $facility_id;
    public $auth_header;

    public function __construct($username, $api_key, $url, $facility_id)
    {
        $this->username = $username;
        $this->api_key = $api_key;
        $this->url = $url;
        $this->facility_id = $facility_id;
        $this->auth_header = base64_encode($username . ':' . $api_key);
    }

    public function fetch_products($filter_brands = false, $brands = [])
    {
        $products = [];
        $offset = 0;
        $limit = self::API_LIMIT; 
        $has_more_products = true;

        while ($has_more_products) {
            $api_url = $this->url . "/external/api/v1/items?active=true&ecommerce_active=true&offset={$offset}&limit={$limit}";

            $headers = [
                'Authorization: Basic ' . $this->auth_header,
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            if ($http_return_code != 200) {
                throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response.");
            }

            $response_data = json_decode($response, true);

            if (isset($response_data['data']) && is_array($response_data['data'])) {
                $flourish_products = $response_data['data'];
                // Grab the inventory for all of them
                foreach ($flourish_products as $key => $flourish_product) {
                    // We need to check if this product belongs to one of the active brands
                    if ($filter_brands && !in_array($flourish_product['brand'], $brands)) {
                        unset($flourish_products[$key]);
                        continue;
                    }

                    $item_id = $flourish_product['id'];
                    $inventory_records = $this->fetch_inventory($item_id);
                    $inventory_quantity = 0;
                    foreach ($inventory_records as $inventory) {
                        // There are item variations sometimes so we'll get more than one inventory record back
                        // for a single item. We only want the one that matches the SKU.
                        if ($inventory['sku'] === $flourish_product['sku']) {
                            $inventory_quantity = $inventory['sellable_qty'];
                            break;
                        }
                    }

                    $flourish_products[$key]['inventory_quantity'] = $inventory_quantity;
                }
                $products = array_merge($products, $flourish_products);
            } else {
                throw new \Exception('Invalid API response format.');
            }

            $has_more_products = isset($response_data['meta']['next']) && !empty($response_data['meta']['next']);

            $offset += $limit;
        }

        return $products;
    }

    public function fetch_facilities()
    {
        $facilities = [];
        $offset = 0;
        $limit = self::API_LIMIT; 
        $has_more_facilities = true;

        while ($has_more_facilities) {
            $api_url = $this->url . "/external/api/v1/facilities?offset={$offset}&limit={$limit}";

            $headers = [
                'Authorization: Basic ' . $this->auth_header,
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            if ($http_return_code != 200) {
                throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response.");
            }

            $response_data = json_decode($response, true);

            if (isset($response_data['data']) && is_array($response_data['data'])) {
                $facilities = array_merge($facilities, $response_data['data']);
            } else {
                throw new \Exception('Invalid API response format.');
            }

            $has_more_facilities = isset($response_data['meta']['next']) && !empty($response_data['meta']['next']);

            $offset += $limit;
        }

        return $facilities;
    }

    public function fetch_facility_config($facility_id)
    {
        $facility_config = false;

        $api_url = $this->url . "/external/api/v1/facilities/{$facility_id}";

        $headers = [
            'Authorization: Basic ' . $this->auth_header,
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception(curl_error($ch), curl_errno($ch));
        }

        if ($http_return_code != 200) {
            throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response.");
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['data']) && is_array($response_data['data'])) {
            $facility_config = $response_data['data'];
        } else {
            throw new \Exception('Invalid API response format.');
        }

        return $facility_config;
    }

    public function fetch_inventory($item_id)
    {
        $api_url = $this->url . "/external/api/v1/inventory/summary?item_id=$item_id";

        $headers = [
            'Authorization: Basic ' . $this->auth_header,
            'FacilityID: ' . $this->facility_id,
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception(curl_error($ch), curl_errno($ch));
        }

        if ($http_return_code != 200) {
            throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response");
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['data']) && is_array($response_data['data'])) {
            return $response_data['data'];
        } else {
            throw new \Exception('Invalid API response format.');
        }
    }

    public function get_or_create_customer_by_email ($customer) {
        $api_url = $this->url . "/external/api/v1/customers?email=" . urlencode($customer['email']);

        $headers = [
            'Authorization: Basic ' . $this->auth_header,
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception(curl_error($ch), curl_errno($ch));
        }

        if ($http_return_code != 200) {
            throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response");
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['data']) && is_array($response_data['data'])) {
            if (count($response_data['data'])) {
                $customer['flourish_customer_id'] = $response_data['data'][0]['id'];
            } else {
                // No customer yet, let's create one
                $api_url = $this->url . "/external/api/v1/customers";

                $headers[] = 'Content-Type: application/json';

                // Send a post of the $customer data
                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customer));
                $response = curl_exec($ch);
                $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($response === false) {
                    throw new \Exception(curl_error($ch), curl_errno($ch));
                }

                if ($http_return_code != 200) {
                    throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response");
                }

                $response_data = json_decode($response, true);

                if (isset($response_data['data']) && is_array($response_data['data'])) {
                    $customer['flourish_customer_id'] = $response_data['data']['id'];
                } else {
                    throw new \Exception('Invalid API response format.');
                }
            }
        } else {
            throw new \Exception('Invalid API response format.');
        }

        return $customer;
    }

    public function create_retail_order($order) {
        $api_url = $this->url . "/external/api/v2/retail-orders";

        $headers = [
            'Authorization: Basic ' . $this->auth_header,
            'FacilityID: ' . $this->facility_id,
            'Content-Type: application/json',
        ];

        // Send a post of the $customer data
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order));
        $response = curl_exec($ch);
        $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception(curl_error($ch), curl_errno($ch));
        }

        if ($http_return_code != 200) {
            throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response");
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['data']) && is_array($response_data['data'])) {
            $order['flourish_order_id'] = $response_data['data']['id'];
        } else {
            throw new \Exception('Invalid API response format.');
        }

        return $response_data['data']['id'];
    }

    public function create_outbound_order($order) {
        // No customer yet, let's create one
        $api_url = $this->url . "/external/api/v1/outbound-orders";

        $headers = [
            'Authorization: Basic ' . $this->auth_header,
            'FacilityID: ' . $this->facility_id,
            'Content-Type: application/json',
        ];

        // Send a post of the $customer data
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order));
        $response = curl_exec($ch);
        $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception(curl_error($ch), curl_errno($ch));
        }

        if ($http_return_code != 200) {
            throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response");
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['data']) && is_array($response_data['data'])) {
            $order['flourish_order_id'] = $response_data['data']['id'];
        } else {
            throw new \Exception('Invalid API response format.');
        }

        return $response_data['data']['id'];
    }

    public function fetch_brands()
    {
        $brands = [];
        $offset = 0;
        $limit = self::API_LIMIT; 
        $has_more_brands = true;

        while ($has_more_brands) {
            $api_url = $this->url . "/external/api/v1/brands?offset={$offset}&limit={$limit}";

            $headers = [
                'Authorization: Basic ' . $this->auth_header,
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            if ($http_return_code != 200) {
                throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response.");
            }

            $response_data = json_decode($response, true);

            if (isset($response_data['data']) && is_array($response_data['data'])) {
                $brands = array_merge($brands, $response_data['data']);
            } else {
                throw new \Exception('Invalid API response format.');
            }

            $has_more_brands = isset($response_data['meta']['next']) && !empty($response_data['meta']['next']);

            $offset += $limit;
        }

        return $brands;
    }

    public function fetch_sales_reps()
    {
        $sales_reps = [];
        $offset = 0;
        $limit = self::API_LIMIT; 
        $has_more_sales_reps = true;

        while ($has_more_sales_reps) {
            $api_url = $this->url . "/external/api/v1/sales-reps?offset={$offset}&limit={$limit}";

            $headers = [
                'Authorization: Basic ' . $this->auth_header,
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $http_return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            if ($http_return_code != 200) {
                throw new \Exception("Did not get 200 response from API. Got: $http_return_code. Response: $response.");
            }

            $response_data = json_decode($response, true);

            if (isset($response_data['data']) && is_array($response_data['data'])) {
                $sales_reps = array_merge($sales_reps, $response_data['data']);
            } else {
                throw new \Exception('Invalid API response format.');
            }

            $has_more_sales_reps = isset($response_data['meta']['next']) && !empty($response_data['meta']['next']);

            $offset += $limit;
        }

        return $sales_reps;
    }
}