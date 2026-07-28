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
     * Get Base API URLs depending on environment.
     *
     * @return array Base URLs list.
     */
    public function get_base_urls() {
        if ( 'production' === $this->environment ) {
            return array(
                'https://open-api.paper.id',
                'https://api.paper.id',
            );
        }
        return array(
            'https://open-api.stag-v2.paper.id',
            'https://api-sandbox.paper.id',
        );
    }

    /**
     * Get primary Base API URL.
     *
     * @return string Base URL.
     */
    public function get_base_url() {
        $urls = $this->get_base_urls();
        return $urls[0];
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

        $base_urls = $this->get_base_urls();
        $paths     = array(
            '/api/v1/auth/login',
            '/open-api/v1/auth/login',
            '/api/v1/auth',
        );

        foreach ( $base_urls as $base_url ) {
            foreach ( $paths as $path ) {
                $url  = rtrim( $base_url, '/' ) . $path;
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
                    'timeout' => 15,
                ) );

                if ( is_wp_error( $response ) ) {
                    continue;
                }

                $status_code   = wp_remote_retrieve_response_code( $response );
                $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( $status_code >= 200 && $status_code < 300 ) {
                    $token = '';
                    if ( ! empty( $response_body['data']['token'] ) ) {
                        $token = $response_body['data']['token'];
                    } elseif ( ! empty( $response_body['token'] ) ) {
                        $token = $response_body['token'];
                    } elseif ( ! empty( $response_body['data']['access_token'] ) ) {
                        $token = $response_body['data']['access_token'];
                    }

                    if ( ! empty( $token ) ) {
                        $expires_in = isset( $response_body['data']['expires_in'] ) ? (int) $response_body['data']['expires_in'] - 60 : 3500;
                        set_transient( $transient_key, $token, max( 60, $expires_in ) );
                        return $token;
                    }
                }
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
            'client_id'      => $this->client_id,
            'client_secret'  => $this->client_secret,
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

        $base_url = $this->get_base_url();
        $headers  = array(
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
        );

        $token = $this->get_auth_token();
        if ( is_string( $token ) && ! empty( $token ) ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        // Step 1: Get or verify partner ID if needed
        $partner_id = null;
        $partner_res = wp_remote_get( rtrim( $base_url, '/' ) . '/api/v1/partners', array(
            'headers' => $headers,
            'timeout' => 15,
        ) );
        if ( ! is_wp_error( $partner_res ) && 200 === wp_remote_retrieve_response_code( $partner_res ) ) {
            $p_data = json_decode( wp_remote_retrieve_body( $partner_res ), true );
            if ( ! empty( $p_data['partners'][0]['uuid'] ) ) {
                $partner_id = $p_data['partners'][0]['uuid'];
            }
        }

        // Step 2: Build Sales Invoice payload
        $inv_number      = 'INV/WC/' . $order_id . '-' . time();
        $invoice_payload = array(
            'number'        => $inv_number,
            'invoice_date'  => date( 'Y-m-d' ),
            'due_date'      => date( 'Y-m-d', strtotime( '+1 day' ) ),
            'partner_name'  => $customer_name ? $customer_name : 'Customer WooCommerce',
            'partner_email' => $customer_email,
            'partner_phone' => $customer_phone,
            'partner'       => array(
                'name'  => $customer_name ? $customer_name : 'Customer WooCommerce',
                'email' => $customer_email,
                'phone' => $customer_phone,
            ),
            'items'         => $items,
            'notes'         => sprintf( __( 'Pembayaran Pesanan #%s di %s', 'paper-id-woocommerce' ), $order_number, get_bloginfo( 'name' ) ),
            'terms'         => 'WooCommerce Order #' . $order_number,
        );

        if ( $partner_id ) {
            $invoice_payload['partner_id'] = $partner_id;
        }

        $base_url = $this->get_base_url();
        $response = wp_remote_post( rtrim( $base_url, '/' ) . '/api/v1/sales-invoices', array(
            'headers' => $headers,
            'body'    => wp_json_encode( $invoice_payload ),
            'timeout' => 30,
        ) );

        if ( ! is_wp_error( $response ) ) {
            $status_code = wp_remote_retrieve_response_code( $response );
            $body_str    = wp_remote_retrieve_body( $response );
            $body        = json_decode( $body_str, true );

            Paper_ID_Helper::log( "POST /api/v1/sales-invoices [HTTP {$status_code}]: " . $body_str, $status_code >= 200 && $status_code < 300 ? 'info' : 'error' );

            if ( $status_code >= 200 && $status_code < 300 && is_array( $body ) ) {
                // A. Check for direct payment URL field in POST response body
                $direct_link = self::extract_payment_url_from_data( $body );
                if ( $direct_link ) {
                    Paper_ID_Helper::log( "Payment Link extracted directly from POST response: {$direct_link}", 'info' );
                    return array(
                        'success'     => true,
                        'payment_url' => $direct_link,
                        'raw'         => $body,
                    );
                }

                // B. Check for invoice UUID in POST response body
                $created_uuid = self::extract_uuid_from_data( $body );
                if ( $created_uuid ) {
                    Paper_ID_Helper::log( "Sales Invoice UUID created: {$created_uuid}. Fetching invoice details...", 'info' );
                    $detail_res = wp_remote_get( rtrim( $base_url, '/' ) . '/api/v1/sales-invoices/' . $created_uuid, array(
                        'headers' => $headers,
                        'timeout' => 15,
                    ) );

                    if ( ! is_wp_error( $detail_res ) && 200 === wp_remote_retrieve_response_code( $detail_res ) ) {
                        $detail_body = json_decode( wp_remote_retrieve_body( $detail_res ), true );
                        $detail_link = self::extract_payment_url_from_data( $detail_body );
                        if ( $detail_link ) {
                            Paper_ID_Helper::log( "Payment Link generated via invoice details API: {$detail_link}", 'info' );
                            return array(
                                'success'     => true,
                                'payment_url' => $detail_link,
                                'raw'         => $detail_body,
                            );
                        }
                    }
                }
            }
        } else {
            Paper_ID_Helper::log( "POST /api/v1/sales-invoices WP_Error: " . $response->get_error_message(), 'error' );
        }

        // Check if merchant configured a custom PaperPay In URL in settings
        $options    = get_option( 'woocommerce_paper_id_settings', array() );
        $custom_url = ! empty( $options['custom_payment_url'] ) ? trim( $options['custom_payment_url'] ) : '';

        if ( ! empty( $custom_url ) && false === strpos( $custom_url, 'sl-surabaya' ) ) {
            $base_pay_url    = 0 === strpos( $custom_url, 'http' ) ? $custom_url : 'https://' . $custom_url;
            $dynamic_pay_url = add_query_arg( array(
                'amount'       => (int) round( $amount ),
                'order_id'     => $order_id,
                'order_number' => $order_number,
            ), $base_pay_url );

            Paper_ID_Helper::log( "Using Custom PaperPay Link for Order #{$order_id}: {$dynamic_pay_url}", 'info' );

            return array(
                'success'     => true,
                'payment_url' => $dynamic_pay_url,
                'raw'         => array( 'source' => 'custom_payment_url', 'url' => $dynamic_pay_url ),
            );
        }

        $error_msg = sprintf(
            __( 'Gagal mendapatkan URL pembayaran dari Paper.id untuk Order #%s. Silakan periksa log di WooCommerce > Status > Logs atau pastikan Client ID & Client Secret Anda di Pengaturan Paper.id sudah valid.', 'paper-id-woocommerce' ),
            $order_number
        );
        Paper_ID_Helper::log( "Payment Request Failed: {$error_msg}", 'error' );

        return new WP_Error( 'paper_id_payment_failed', $error_msg );
    }

    /**
     * Helper to extract payment URL / link from API response arrays.
     *
     * @param array $data API response array.
     * @return string|null Formatted payment URL or null.
     */
    private static function extract_payment_url_from_data( $data ) {
        if ( ! is_array( $data ) ) {
            return null;
        }

        $candidates = array();

        if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
            $d = $data['data'];
            if ( ! empty( $d['payment_link'] ) ) { $candidates[] = $d['payment_link']; }
            if ( ! empty( $d['payper_url'] ) ) { $candidates[] = $d['payper_url']; }
            if ( ! empty( $d['payment_url'] ) ) { $candidates[] = $d['payment_url']; }
            if ( ! empty( $d['url'] ) ) { $candidates[] = $d['url']; }
        }

        if ( ! empty( $data['payment_link'] ) ) { $candidates[] = $data['payment_link']; }
        if ( ! empty( $data['payper_url'] ) ) { $candidates[] = $data['payper_url']; }
        if ( ! empty( $data['payment_url'] ) ) { $candidates[] = $data['payment_url']; }
        if ( ! empty( $data['url'] ) ) { $candidates[] = $data['url']; }

        foreach ( $candidates as $candidate ) {
            if ( is_string( $candidate ) && ! empty( trim( $candidate ) ) ) {
                $url = trim( $candidate );
                return 0 === strpos( $url, 'http' ) ? $url : 'https://' . $url;
            }
        }

        return null;
    }

    /**
     * Helper to extract invoice UUID from API response arrays.
     *
     * @param array $data API response array.
     * @return string|null Invoice UUID or null.
     */
    private static function extract_uuid_from_data( $data ) {
        if ( ! is_array( $data ) ) {
            return null;
        }

        if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
            $d = $data['data'];
            if ( ! empty( $d['uuid'] ) ) { return $d['uuid']; }
            if ( ! empty( $d['id'] ) ) { return $d['id']; }
            if ( isset( $d['invoice'] ) && is_array( $d['invoice'] ) && ! empty( $d['invoice']['uuid'] ) ) {
                return $d['invoice']['uuid'];
            }
        }

        if ( ! empty( $data['uuid'] ) ) { return $data['uuid']; }
        if ( ! empty( $data['id'] ) ) { return $data['id']; }
        if ( isset( $data['invoice'] ) && is_array( $data['invoice'] ) && ! empty( $data['invoice']['uuid'] ) ) {
            return $data['invoice']['uuid'];
        }

        return null;
    }
}
