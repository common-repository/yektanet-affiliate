<?php
/*
Plugin Name: Chavosh Affiliate
Plugin URI: https://chavosh.org
Description: chavosh woocommerce plugin to send purchase data to chavosh servers
Requires at least: 5.4
Requires PHP: 7.0
Version: 1.1.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author:  Ali Rezaei
Author URI: https://chavosh.org
Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
WC requires at least: 5.1.0
WC tested up to: 5.1.0
*/

/*
chavosh affiliate is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

chavosh affiliate plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with chavosh affiliate plugin. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// Exit if accessed directly
if (!defined('ABSPATH')){
    exit;
}

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    if (!defined('YEKTANETAFFILIATE_VERSION')) {
        define('YEKTANETAFFILIATE_VERSION', '1.1.0');
    }
    if (!defined('YEKTANETAFFILIATE_DB_VERSION')) {
        define('YEKTANETAFFILIATE_DB_VERSION', '1.1');
    }

    // Load Database
    require_once(plugin_dir_path(__FILE__). '/includes/aw_plugin-database.php');

    // Load Settings
    require_once(plugin_dir_path(__FILE__). '/includes/aw_plugin-settings.php');

    // Add Settings Page
    add_action( 'admin_menu', 'yektanetaffiliate_add_admin_menu' );
    add_action( 'admin_init', 'yektanetaffiliate_settings_init' );

    add_action('plugins_loaded', 'yektanetaffiliate_update_db_check');

    add_action('wp_head', 'yektanetaffiliate_add_script');

    add_action( 'woocommerce_order_refunded', 'yektanetaffiliate_status_refunded', 10, 2);

    // plugin init
    register_activation_hook( __FILE__, 'yektanetaffiliate_initialization');

    add_action('woocommerce_order_status_changed', 'yektanetaffiliate_status_change', 10, 3);

    add_action('woocommerce_thankyou', 'yektanetaffiliate_success_page', 10, 1);

    add_action('woocommerce_checkout_order_processed', 'yektanetaffiliate_process', 10, 1);

    add_action('woocommerce_before_cart', 'yektanetaffiliate_cart', 10, 1);

    add_action('woocommerce_review_order_before_payment', 'yektanetaffiliate_checkout', 10, 0);

    add_action('woocommerce_add_to_cart', 'yektanetaffiliate_add_cart', 10, 6);

    add_action('woocommerce_before_add_to_cart_button', 'yektanetaffiliate_product_detail', 10, 0);

    add_action('woocommerce_update_product', 'yektanetaffiliate_product_update', 10, 1);

    // update check
    add_action('plugins_loaded', 'yektanetaffiliate_check_version');

    function yektanetaffiliate_add_script() {
        $app_id = get_option('yektanetaffiliate_settings')['yektanetaffiliate_app_id'];
        ?>
        <script>
            !function (t, e, n) {
                const d = new Date();
                d.setTime(d.getTime() + (4*24*60*60*1000));
                let expires = "expires="+ d.toUTCString();
                document.cookie = 'yn_ch_test' + "=" + 'test_done' + ";" + 4 + ";path=/";
                t.yektanetAnalyticsObject = n
                t[n] = t[n] || function () {
                    t[n].q.push(arguments)
                }
                t[n].q = t[n].q || [];
                var a = new Date
                var app_id = '<?php echo $app_id; ?>';
                r = a.getFullYear().toString() + "0" + a.getMonth() + "0" + a.getDate() + "0" + a.getHours()
                c = e.getElementsByTagName("script")[0]
                s = e.createElement("script");
                s.id = "ua-script-" + app_id;
                s.dataset.analyticsobject = n;
                s.async = 1;
                s.type = "text/javascript";
                s.src = "https://cdn.yektanet.com/rg_woebegone/scripts_v4/" + app_id + "/complete.js?v=" + r
                c.parentNode.insertBefore(s, c)
            }(window, document, "yektanet");
        </script>
    <?php }


    function yektanetaffiliate_product_detail() {
        global $product;
        $id = $product->get_id();
        yektanetaffiliate_send_analytics_data($id, 'detail');
    }

    function yektanetaffiliate_add_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        yektanetaffiliate_send_analytics_data($product_id, 'add', $quantity);
    }

    function yektanetaffiliate_guidv4($data = null) {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    function yektanetaffiliate_send_analytics_data($product_id, $type, $quantity = 0) {
        $product = wc_get_product($product_id);
        $sku = $product->get_sku();
        if (!$sku) {
            $sku = $product->get_id();
        }
        $categories = get_the_terms($product->get_id(), 'product_cat')[0];
        $parentcats = get_ancestors($categories->term_id, 'product_cat');
        $category_data = [];
        array_push($category_data, $categories->name);
        foreach ($parentcats as $cat) {
            array_push($category_data, get_term_by( 'id', $cat, 'product_cat' )->name);
        }
        $category = join(",", $category_data);
        $regular = $product->get_regular_price();
        $sale = $product->get_sale_price();
        if ($regular && $sale) {
            $discount = $regular - $sale;
        } else {
            $discount = 0;
        }
        $utm_data = $_COOKIE['analytics_campaign'];
        if (!$utm_data) {
            $utm_data = $_COOKIE['_ynsrc'];
        }
        $utm_data = json_decode(stripslashes($utm_data), True);
        $session_token = $_COOKIE['analytics_session_token'];
        if (!$session_token) {
            $session_token = yektanetaffiliate_guidv4();
        }
        $params = $_SERVER['QUERY_STRING'];
        $params = explode('&', $params);
        $params_data = [];
        foreach ($params as $par) {
            $par_data = explode('=', $par);
            $params_data[$par_data[0]] = $par_data[1];
        }

        $request_url = 'https://ua.yektanet.com/__fake.gif?';
        $header = array();
        $header['Content-type'] = 'text/plain;charset=UTF-8';
        $header['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
        $header['origin'] = get_site_url();
        $header['referer'] = get_permalink($product->get_id());
        $data = array(
            'acm'     => $type,
            'aa'     => 'product',
            'aca'     => $product->get_title(),
            'acb'     => $sku,
            'acc'     => $category,
            'acd'     => $quantity,
            'ace'     => $product->get_price(),
            'ach'     => $discount,
            'aco'     => wp_get_attachment_image_url( $product->get_image_id(), 'full' ),
            'acq'     => $product->is_in_stock(),
            'ac'     => get_permalink($product->get_id()),
            'ae'     => json_encode($params_data),
            'ad'     => get_site_url(),
            'ba'     => $_COOKIE['_yngt'],
            'as'     => $product->get_title(),
            'aef'     => get_option('yektanetaffiliate_settings')['yektanetaffiliate_app_id'],
            'aaa'     => $utm_data['source'],
            'aab'     => $utm_data['medium'],
            'aac'     => $utm_data['content'],
            'aad'     => $utm_data['campaign'],
            'aae'     => $utm_data['term'],
            'abi'     => $utm_data['yn'],
            'uys'     => $utm_data['yn_source'],
            'uyd'     => $utm_data['yn_data'],
            'ai'     => $session_token,
            'af'     => wp_get_referer(),
            'ag'     => explode('/', wp_get_referer())[2],
        );
        $args = array(
            'headers'     => $header,
            'timeout'     => 1,
        );
        $response = wp_remote_post($request_url . http_build_query($data), $args);
    }

    function yektanetaffiliate_product_update($product_id) {
        $product = wc_get_product($product_id);
        $sku = $product->get_sku();
        if (!$sku) {
            $sku = $product->get_id();
        }
        $regular = $product->get_regular_price();
        $sale = $product->get_sale_price();
        if ($regular && $sale) {
            $discount = $regular - $sale;
        } else {
            $discount = 0;
        }
        $categories = get_the_terms($product->get_id(), 'product_cat')[0];
        $parentcats = get_ancestors($categories->term_id, 'product_cat');
        $category_data = [];
        array_push($category_data, $categories->name);
        foreach ($parentcats as $cat) {
            array_push($category_data, get_term_by( 'id', $cat, 'product_cat' )->name);
        }
        $request_url = 'http://87.247.185.150/products';
        $header = array();
        $header['Content-type'] = 'text/plain;charset=UTF-8';
        $data = array(
            'appId'  => get_option('yektanetaffiliate_settings')['yektanetaffiliate_app_id'],
            'productSku'  => $sku,
            'host'  => get_site_url(),
            'url'  => get_permalink( $product->get_id() ),
            'productTitle'  => $product->get_title(),
            'productImage'  => wp_get_attachment_image_url( $product->get_image_id(), 'full' ),
            'productCategory'  => array_reverse($category_data),
            'productDiscount'  => $discount,
            'productPrice'  => $regular,
            'productCurrency'  => get_woocommerce_currency(),
            'productIsAvailable'  => $product->is_in_stock(),
        );
        $args = array(
            'headers'     => $header,
            'timeout'     => 3,
            'method'      => 'PUT',
            'body'        => json_encode($data)
        );
        $response = wp_remote_request($request_url, $args);
    }

    function yektanetaffiliate_initialization() {
        // create table
        yektanetaffiliate_create_table();
        add_option('redirect_after_activation_option', true);
    }

    function yektanetaffiliate_activation_redirect() {
        if (get_option('redirect_after_activation_option', false)) {
            delete_option('redirect_after_activation_option');
            exit(wp_redirect(admin_url( 'options-general.php?page=chavosh' )));
        }
    }

    function yektanetaffiliate_update_db_check() {
        if (YEKTANETAFFILIATE_DB_VERSION !== get_option('YEKTANETAFFILIATE_DB_VERSION')) {
            update_option('YEKTANETAFFILIATE_DB_VERSION', YEKTANETAFFILIATE_DB_VERSION);
            yektanetaffiliate_create_table();
        }
    }

    function yektanetaffiliate_check_version() {
        if (YEKTANETAFFILIATE_VERSION !== get_option('YEKTANETAFFILIATE_VERSION')) {
            update_option('YEKTANETAFFILIATE_VERSION', YEKTANETAFFILIATE_VERSION);
        }
    }

    function yektanetaffiliate_send_data($order, $partial, $datetime, $type, $status, $new_status) {
        global $wp;
        $raw_cookies = (array_reduce(
            explode(';', $_SERVER['HTTP_COOKIE'] ?? ''),
            function($carry, $item) {
                $pair_arr = explode('=', $item);
                $carry[ trim($pair_arr[0]) ] = trim($pair_arr[1]);
                return $carry;
            },
            []
        ) ?: []);

        if (!isset($_COOKIE["_yngt"]) || empty($_COOKIE["_yngt"])) {
            $yngt = '';
        } else {
            $yngt = $raw_cookies["_yngt"];
        }
        if (isset($_COOKIE["analytics_campaign"]) && !empty($_COOKIE["analytics_campaign"])) {
            $ynsrc = $raw_cookies["analytics_campaign"];
        } else if (isset($_COOKIE["_ynsrc"]) && !empty($_COOKIE["_ynsrc"])) {
            $ynsrc = $raw_cookies["_ynsrc"];
        } else {
            $ynsrc = '';
        }

        $items_data = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = wc_get_product($item->get_product_id());
            $categories = get_the_terms($product->get_id(), 'product_cat');
            $categories_data = array();
            foreach ($categories as $category) {
                $category_data = array();
                $category_data['name'] = $category->name;
                $category_data['id'] = $category->term_id;
                $parentcats = get_ancestors($category->term_id, 'product_cat');
                foreach ($parentcats as $cat) {
                    $category_data['name'] = get_term_by('id', $cat, 'product_cat')->name;
                    $category_data['id'] = $cat;
                }
                array_push($categories_data, $category_data);
            }
            $item_data = array(
                'price'      => $product->get_price(),
                'quantity'      => $item->get_quantity(),
                'product_id'      => $product->get_id(),
                'sku'      => $product->get_sku(),
                'total'      => $item->get_total(),
                'url'      => get_permalink( $product->get_id() ),
                'title'      => $product->get_title(),
            );
            $image = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
            if ($image) {
                $item_data['image'] = $image;
            }

            $item_data['categories'] = $categories_data;
            array_push($items_data, $item_data);
        }
        $field = [
            'currency'      => $order->get_currency(),
            'amount' => $order->get_total(),
            'discount'         => $order->get_total_discount(),
            'ip'         => $order->get_customer_ip_address(),
            'user_agent'         => $order->get_customer_user_agent(),
            'order_id'         => $order->get_id(),
            'previous_status'         => $status,
            'status'         => $new_status,
            'current_url' => add_query_arg($wp->query_vars, home_url( $wp->request )),
            'cookies_count' => count($_COOKIE),
            'yn_ch_test' => $_COOKIE['yn_ch_test'],
            'ynsrc'         => $ynsrc,
            'yngt'         => $yngt,
            'partial'  => $partial,
            'version'  => '1.1.0'
        ];

        if ($datetime) {
            $field['paid_at'] = $datetime->format('Y-m-d\TH:i:sP');
        }
        global $current_user;
        $field['user_id'] = wp_get_current_user()->ID;
        $field['user_roles'] = $current_user->roles;
        $field['items'] = $items_data;
        $fields = array();
        array_push($fields, $field);

        //Collect Data From Database
        foreach ((array) yektanetaffiliate_get_data($type) as $data) {
            array_push($fields, json_decode(((array) $data)['fields']));
        }
        yektanetaffiliate_delete_data($type);

        //Collect Token From settings page
        $token=get_option('yektanetaffiliate_settings')['yektanetaffiliate_token'];

        // Send API Request
        $url = 'https://trk.chavosh.org/api/v1/event/';
        $header = array();
        $header['Content-type'] = 'application/json';
        $header['Authorization'] = 'Token ' . $token;
        $args = array(
            'body'        => json_encode($fields),
            'headers'     => $header,
        );
        $response = wp_remote_post($url ,$args);
        $status = wp_remote_retrieve_response_code($response);
        if (!$status || $status >= 500 || $status == 408 || $status == 429) {
            foreach ($fields as $field_data) {
                yektanetaffiliate_add_data(json_encode($field_data), $type);
            }
        }
    }

    function yektanetaffiliate_send_temp_data($status) {
        global $wp;
        $raw_cookies = (array_reduce(
            explode(';', $_SERVER['HTTP_COOKIE'] ?? ''),
            function($carry, $item) {
                $pair_arr = explode('=', $item);
                $carry[ trim($pair_arr[0]) ] = trim($pair_arr[1]);
                return $carry;
            },
            []
        ) ?: []);

        if (!isset($_COOKIE["_yngt"]) || empty($_COOKIE["_yngt"])) {
            $yngt = '';
        } else {
            $yngt = $raw_cookies["_yngt"];
        }
        if (isset($_COOKIE["analytics_campaign"]) && !empty($_COOKIE["analytics_campaign"])) {
            $ynsrc = $raw_cookies["analytics_campaign"];
        } else if (isset($_COOKIE["_ynsrc"]) && !empty($_COOKIE["_ynsrc"])) {
            $ynsrc = $raw_cookies["_ynsrc"];
        } else {
            $ynsrc = '';
        }
        $field = [
            'ip'         => $_SERVER['REMOTE_ADDR'],
            'user_agent'         => $_SERVER['HTTP_USER_AGENT'],
            'status'         => $status,
            'current_url' => add_query_arg($wp->query_vars, home_url( $wp->request )),
            'cookies_count' => count($_COOKIE),
            'yn_ch_test' => $_COOKIE['yn_ch_test'],
            'ynsrc'         => $ynsrc,
            'yngt'         => $yngt,
            'paid_at'  => $_SERVER['REQUEST_TIME'],
            'version'  => '1.1.0'
        ];
        global $current_user;
        $field['user_id'] = wp_get_current_user()->ID;
        $field['user_roles'] = $current_user->roles;
        $fields = array();
        array_push($fields, $field);
        $token=get_option('yektanetaffiliate_settings')['yektanetaffiliate_token'];
        $url = 'https://trk.chavosh.org/api/v1/event/';
        $header = array();
        $header['Content-type'] = 'application/json';
        $header['Authorization'] = 'Token ' . $token;
        $args = array(
            'body'        => json_encode($fields),
            'headers'     => $header,
        );
        $response = wp_remote_post($url ,$args);
    }

    function yektanetaffiliate_checkout() {
        yektanetaffiliate_send_temp_data('checkout');
    }

    function yektanetaffiliate_cart($temp) {
        yektanetaffiliate_send_temp_data('cart');
    }

    function yektanetaffiliate_process($order_id) {
        $order = wc_get_order($order_id);
        $datetime = $order->get_date_paid();
        yektanetaffiliate_send_data($order, False, $datetime, 'process', 'pending', 'process');
    }

    function yektanetaffiliate_success_page($order_id) {
        $order = wc_get_order($order_id);
        $datetime = $order->get_date_paid();
        yektanetaffiliate_send_data($order, False, $datetime, 'success', 'pending', 'success');
    }

    function yektanetaffiliate_status_refunded( $order_id, $refund_id ) {
        $order = wc_get_order($order_id);
        if ($order->get_status() == 'refunded') {
            return;
        }
        $refund = wc_get_order($refund_id);
        $datetime = $refund->get_date_created();
        yektanetaffiliate_send_data($refund, True, $datetime, 'refund', $order->get_status(), 'refunded');
    }

    function yektanetaffiliate_status_change($id, $status = 'pending', $new_status = 'on-hold'){
        $order = wc_get_order($id);
        $datetime = $order->get_date_paid();
        yektanetaffiliate_send_data($order, False, $datetime, 'status', $status, $new_status);
    }
}
