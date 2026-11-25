<?php

class GFAPI
{
    public static function get_form($form_id)
    {
        return array('id' => $form_id, 'is_active' => $form_id === 11, 'is_trash' => false);
    }
}

class ApbctWooCommerceReviewFixture
{
    public function api_request_url($gateway)
    {
        return home_url('/wc-api/' . strtolower($gateway) . '/');
    }

    public function payment_gateways()
    {
        add_action('woocommerce_api_fixture_gateway', '__return_null');
    }

    public function get_status()
    {
        return 'publish';
    }

    public function get_permalink()
    {
        return home_url('/product/fixture/');
    }
}

function WC()
{
    return new ApbctWooCommerceReviewFixture();
}

function wc_get_product($product_id)
{
    return $product_id === 42 ? WC() : false;
}

function wc_get_page_id($page)
{
    return $page === 'myaccount' ? 1 : -1;
}

function wc_get_account_endpoint_url($endpoint)
{
    return home_url('/my-account/' . $endpoint . '/');
}
