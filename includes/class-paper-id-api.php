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
        $invoice_payload = array(
            'number'       => 'INV/WC/' . $order_id . '-' . time(),
            'invoice_date' => date( 'Y-m-d' ),
            'due_date'     => date( 'Y-m-d', strtotime( '+1 day' ) ),
            'partner_name'  => $customer_name ? $customer_name : 'Customer WooCommerce',
            'partner_email' => $customer_email,
            'partner_phone' => $customer_phone,
            'items'        => $items,
        );

        if ( $partner_id ) {
            $invoice_payload['partner_id'] = $partner_id;
        }

        $response = wp_remote_post( rtrim( $base_url, '/' ) . '/api/v1/sales-invoices', array(
            'headers' => $headers,
            'body'    => wp_json_encode( $invoice_payload ),
            'timeout' => 30,
        ) );

        $options = get_option( 'woocommerce_paper_id_settings', array() );
        if ( ! empty( $options['custom_payment_url'] ) ) {
            $custom_url  = trim( $options['custom_payment_url'] );
            $payment_url = 0 === strpos( $custom_url, 'http' ) ? $custom_url : 'https://' . $custom_url;
            Paper_ID_Helper::log( "Using Custom PaperPay In Link: {$payment_url}", 'info' );
            return array(
                'success'     => true,
                'payment_url' => $payment_url,
                'raw'         => array( 'source' => 'custom_payment_url' ),
            );
        }

        if ( ! is_wp_error( $response ) ) {
            $status_code = wp_remote_retrieve_response_code( $response );
            $body_str    = wp_remote_retrieve_body( $response );
            $body        = json_decode( $body_str, true );

            Paper_ID_Helper::log( "POST /api/v1/sales-invoices [HTTP {$status_code}]: " . $body_str, $status_code >= 200 && $status_code < 300 ? 'info' : 'error' );

            if ( $status_code >= 200 && $status_code < 300 ) {
                // Fetch invoice list to extract an unpaid invoice payment_link
                $list_res = wp_remote_get( rtrim( $base_url, '/' ) . '/api/v1/sales-invoices', array(
                    'headers' => $headers,
                    'timeout' => 15,
                ) );

                if ( ! is_wp_error( $list_res ) && 200 === wp_remote_retrieve_response_code( $list_res ) ) {
                    $list_body   = json_decode( wp_remote_retrieve_body( $list_res ), true );
                    $target_uuid = null;

                    if ( ! empty( $list_body['invoices'] ) && is_array( $list_body['invoices'] ) ) {
                        foreach ( $list_body['invoices'] as $inv ) {
                            $totals     = isset( $inv['totals'] ) ? $inv['totals'] : array();
                            $amount_due = isset( $totals['amountDueUnformatted'] ) ? (float) $totals['amountDueUnformatted'] : ( isset( $totals['amountDue'] ) ? (float) str_replace( array(',', '.00'), '', $totals['amountDue'] ) : 1 );
                            $status_val = isset( $inv['status'] ) ? (int) $inv['status'] : 0;

                            // Filter out paid invoices (status 2 = paid). Pick an unpaid invoice.
                            if ( 2 !== $status_val && $amount_due > 0 && ! empty( $inv['uuid'] ) ) {
                                $target_uuid = $inv['uuid'];
                                break;
                            }
                        }

                        // Fallback to first invoice only if no unpaid invoice is found
                        if ( ! $target_uuid && ! empty( $list_body['invoices'][0]['uuid'] ) ) {
                            $target_uuid = $list_body['invoices'][0]['uuid'];
                        }
                    }

                    if ( $target_uuid ) {
                        $detail_res = wp_remote_get( rtrim( $base_url, '/' ) . '/api/v1/sales-invoices/' . $target_uuid, array(
                            'headers' => $headers,
                            'timeout' => 15,
                        ) );

                        if ( ! is_wp_error( $detail_res ) && 200 === wp_remote_retrieve_response_code( $detail_res ) ) {
                            $detail_body = json_decode( wp_remote_retrieve_body( $detail_res ), true );
                            if ( ! empty( $detail_body['data']['payment_link'] ) ) {
                                $raw_link    = $detail_body['data']['payment_link'];
                                $payment_url = 0 === strpos( $raw_link, 'http' ) ? $raw_link : 'https://' . $raw_link;
                                Paper_ID_Helper::log( "Payment Link Generated Successfully: {$payment_url}", 'info' );
                                return array(
                                    'success'     => true,
                                    'payment_url' => $payment_url,
                                    'raw'         => $detail_body,
                                );
                            }
                        }
                    }
                }
            }
        }

        // Step 3: Fallback endpoints if direct sales-invoices flow fails
        $endpoints = array(
            rtrim( $base_url, '/' ) . '/api/v1/pay-in/payment-link',
            rtrim( $base_url, '/' ) . '/api/v1/digital-payment',
            rtrim( $base_url, '/' ) . '/api/v1/payment-request',
        );

        $last_error = null;

        foreach ( $endpoints as $endpoint ) {
            $response = wp_remote_post( $endpoint, array(
                'headers' => $headers,
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            ) );

            if ( is_wp_error( $response ) ) {
                $last_error = 'HTTP Error (' . $endpoint . '): ' . $response->get_error_message();
                Paper_ID_Helper::log( $last_error, 'error' );
                continue;
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            $body_str    = wp_remote_retrieve_body( $response );
            $body        = json_decode( $body_str, true );

            Paper_ID_Helper::log( "Fallback API Response from {$endpoint} [HTTP {$status_code}]: " . $body_str, $status_code >= 200 && $status_code < 300 ? 'info' : 'error' );

            if ( $status_code >= 200 && $status_code < 300 ) {
                $payment_url = '';
                if ( ! empty( $body['data']['payment_url'] ) ) {
                    $payment_url = $body['data']['payment_url'];
                } elseif ( ! empty( $body['data']['link_url'] ) ) {
                    $payment_url = $body['data']['link_url'];
                } elseif ( ! empty( $body['payment_url'] ) ) {
                    $payment_url = $body['payment_url'];
                }

                if ( ! empty( $payment_url ) ) {
                    return array(
                        'success'     => true,
                        'payment_url' => 0 === strpos( $payment_url, 'http' ) ? $payment_url : 'https://' . $payment_url,
                        'raw'         => $body,
                    );
                }
            }

            $msg = isset( $body['message'] ) ? ( is_array( $body['message'] ) ? implode( ', ', $body['message'] ) : $body['message'] ) : '';
            if ( isset( $body['error']['message'] ) ) {
                $msg = $body['error']['message'];
            }
            $last_error = sprintf( __( 'Paper.id API Response [HTTP %d]: %s', 'paper-id-woocommerce' ), $status_code, $msg ? $msg : $body_str );

            if ( 404 !== $status_code ) {
                break;
            }
        }

        return new WP_Error( 'paper_id_api_error', $last_error ? $last_error : __( 'Gagal terhubung ke Paper.id API.', 'paper-id-woocommerce' ) );
    }
}
