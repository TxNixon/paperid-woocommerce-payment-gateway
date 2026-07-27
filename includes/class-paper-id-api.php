<?php
/**
 * Paper.id API Client
 *
 * Handles HTTP requests to Paper.id Open API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Paper_ID_API {

    /**
     * Client ID.
     * @var string
     */
    private $client_id;

    /**
     * Client Secret.
     * @var string
     */
    private $client_secret;

    /**
     * Environment mode ('sandbox' or 'production').
     * @var string
     */
    private $environment;

    /**
     * Enable debug logging.
     * @var bool
     */
    private $debug;

    /**
     * Constructor.
     *
     * @param string $client_id
     * @param string $client_secret
     * @param string $environment
     * @param bool   $debug
     */
    public function __construct( $client_id, $client_secret, $environment = 'sandbox', $debug = false ) {
        $this->client_id     = trim( $client_id );
        $this->client_secret = trim( $client_secret );
        $this->environment   = $environment;
        $this->debug         = (bool) $debug;
    }

    /**
     * Get Base API URL depending on environment.
     *
     * @return string Base URL.
     */
    public function get_base_url() {
        if ( 'production' === $this->environment ) {
            return 'https://api.paper.id';
        }
        return 'https://api-sandbox.paper.id';
    }

    /**
     * Retrieve Bearer Token / Auth Headers for Paper.id API.
     *
     * @return string|null Token string or null.
     */
    public function get_auth_token() {
        if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
            return null;
        }

        $transient_key = 'paper_id_token_' . md5( $this->client_id . $this->environment );
        $cached_token  = get_transient( $transient_key );

        if ( $cached_token ) {
            return $cached_token;
        }

        $url  = rtrim( $this->get_base_url(), '/' ) . '/open-api/v1/auth/login';
        $body = array(
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
        );

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            Paper_ID_Helper::log( 'Auth Error: ' . $response->get_error_message(), 'error' );
            return null;
        }

        $status_code   = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code >= 200 && $status_code < 300 ) {
            if ( ! empty( $response_body['data']['token'] ) ) {
                $token      = $response_body['data']['token'];
                $expires_in = isset( $response_body['data']['expires_in'] ) ? (int) $response_body['data']['expires_in'] - 60 : 3500;
                set_transient( $transient_key, $token, max( 60, $expires_in ) );
                return $token;
            }
        }

        return null;
    }

    /**
     * Generate Payment Link / PayIn Invoice on Paper.id.
     *
     * @param WC_Order $order WooCommerce Order object.
     * @param string   $callback_url Webhook/Callback URL.
     * @return array|WP_Error Response array with payment_url or WP_Error.
     */
    public function create_payment_request( $order, $callback_url ) {
        $order_id     = $order->get_id();
        $order_number = $order->get_order_number();
        $amount       = (float) $order->get_total();
        $currency     = $order->get_currency();

        if ( 'IDR' !== $currency ) {
            return new WP_Error( 'paper_id_currency_error', __( 'Paper.id payment gateway hanya mendukung mata uang IDR (Rupiah). Mata uang toko Anda saat ini: ', 'paper-id-woocommerce' ) . $currency );
        }

        if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
            return new WP_Error( 'paper_id_config_error', __( 'Client ID atau Client Secret Paper.id belum diisi di Pengaturan WooCommerce.', 'paper-id-woocommerce' ) );
        }

        $customer_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $customer_email = $order->get_billing_email();
        $customer_phone = Paper_ID_Helper::format_phone( $order->get_billing_phone() );

        $items = Paper_ID_Helper::get_order_items_payload( $order );

        $payload = array(
            'external_id'    => 'WC-' . $order_id . '-' . time(),
            'order_id'       => (string) $order_id,
            'order_number'   => (string) $order_number,
            'amount'         => (int) round( $amount ),
            'currency'       => 'IDR',
            'customer'       => array(
                'name'  => $customer_name ? $customer_name : 'Customer WooCommerce',
                'email' => $customer_email,
                'phone' => $customer_phone,
            ),
            'items'          => $items,
            'description'    => sprintf( __( 'Pembayaran Pesanan #%s di %s', 'paper-id-woocommerce' ), $order_number, get_bloginfo( 'name' ) ),
            'callback_url'   => $callback_url,
            'redirect_url'   => $order->get_checkout_order_received_url(),
            'payment_method' => array( 'credit_card', 'qris', 'virtual_account', 'ewallet' ),
        );

        Paper_ID_Helper::log( "Creating Payment Request for Order #{$order_id} (Amount: Rp " . number_format($amount, 0, ',', '.') . ")", 'info' );

        if ( $this->debug ) {
            Paper_ID_Helper::log( 'Request Payload: ' . wp_json_encode( $payload ), 'info' );
        }

        // Endpoint list for Paper.id API
        $endpoints = array(
            rtrim( $this->get_base_url(), '/' ) . '/open-api/v1/pay-in/payment-link',
            rtrim( $this->get_base_url(), '/' ) . '/open-api/v1/digital-payment',
            rtrim( $this->get_base_url(), '/' ) . '/open-api/v1/invoices/generate-payment-link',
        );

        $token   = $this->get_auth_token();
        $headers = array(
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'client-id'     => $this->client_id,
            'client-secret' => $this->client_secret,
        );

        if ( is_string( $token ) && ! empty( $token ) ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $last_error = null;

        foreach ( $endpoints as $endpoint ) {
            $response = wp_remote_post( $endpoint, array(
                'headers' => $headers,
                'body'    => wp_json_encode( $payload ),
                'timeout' => 45,
            ) );

            if ( is_wp_error( $response ) ) {
                $last_error = 'HTTP Error (' . $endpoint . '): ' . $response->get_error_message();
                Paper_ID_Helper::log( $last_error, 'error' );
                continue;
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            $body_str    = wp_remote_retrieve_body( $response );
            $body        = json_decode( $body_str, true );

            Paper_ID_Helper::log( "API Response from {$endpoint} [HTTP {$status_code}]: " . $body_str, $status_code >= 200 && $status_code < 300 ? 'info' : 'error' );

            if ( $status_code >= 200 && $status_code < 300 ) {
                $payment_url = '';

                if ( ! empty( $body['data']['payment_url'] ) ) {
                    $payment_url = $body['data']['payment_url'];
                } elseif ( ! empty( $body['data']['link_url'] ) ) {
                    $payment_url = $body['data']['link_url'];
                } elseif ( ! empty( $body['data']['url'] ) ) {
                    $payment_url = $body['data']['url'];
                } elseif ( ! empty( $body['payment_url'] ) ) {
                    $payment_url = $body['payment_url'];
                } elseif ( ! empty( $body['url'] ) ) {
                    $payment_url = $body['url'];
                }

                if ( ! empty( $payment_url ) ) {
                    return array(
                        'success'     => true,
                        'payment_url' => $payment_url,
                        'raw'         => $body,
                    );
                }
            }

            $msg = isset( $body['message'] ) ? ( is_array($body['message']) ? implode(', ', $body['message']) : $body['message'] ) : '';
            if ( isset( $body['error']['message'] ) ) {
                $msg = $body['error']['message'];
            }
            $last_error = sprintf( __( 'Paper.id API Response [HTTP %d]: %s', 'paper-id-woocommerce' ), $status_code, $msg ? $msg : $body_str );
        }

        return new WP_Error( 'paper_id_api_error', $last_error ? $last_error : __( 'Gagal terhubung ke Paper.id API.', 'paper-id-woocommerce' ) );
    }
}
