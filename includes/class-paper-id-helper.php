<?php
/**
 * Paper.id Helper Class
 *
 * Utility functions for Paper.id WooCommerce integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Paper_ID_Helper {

    /**
     * Format phone number to standard Indonesian phone format if needed.
     *
     * @param string $phone Customer phone number.
     * @return string Formatted phone number.
     */
    public static function format_phone( $phone ) {
        $phone = preg_replace( '/[^0-9]/', '', $phone );
        if ( substr( $phone, 0, 1 ) === '0' ) {
            $phone = '62' . substr( $phone, 1 );
        }
        return $phone;
    }

    /**
     * Map Paper.id transaction status to WooCommerce status.
     *
     * @param string $paper_status Status received from Paper.id callback/API.
     * @return string WooCommerce order status slug.
     */
    public static function map_status( $paper_status ) {
        $status_upper = strtoupper( trim( $paper_status ) );

        switch ( $status_upper ) {
            case 'PAID':
            case 'SETTLED':
            case 'SUCCESS':
            case 'COMPLETED':
                return 'processing';

            case 'PENDING':
            case 'UNPAID':
                return 'pending';

            case 'EXPIRED':
                return 'cancelled';

            case 'FAILED':
            case 'DENIED':
                return 'failed';

            default:
                return 'on-hold';
        }
    }

    /**
     * Get clean items payload for Paper.id API from WooCommerce Order.
     *
     * @param WC_Order $order WooCommerce Order object.
     * @return array List of items formatted for API payload.
     */
    public static function get_order_items_payload( $order ) {
        $items = array();

        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $items[] = array(
                'name'     => mb_substr( $item->get_name(), 0, 100 ),
                'quantity' => (int) $item->get_quantity(),
                'price'    => (float) $order->get_item_total( $item, false, true ),
                'sku'      => $product ? $product->get_sku() : '',
            );
        }

        // Add shipping if any
        if ( (float) $order->get_shipping_total() > 0 ) {
            $items[] = array(
                'name'     => __( 'Shipping Fee', 'paper-id-woocommerce' ),
                'quantity' => 1,
                'price'    => (float) $order->get_shipping_total(),
                'sku'      => 'SHIPPING',
            );
        }

        // Add fee/taxes adjustment if total discrepancy exists
        return $items;
    }

    /**
     * Logger utility wrapper for WooCommerce.
     *
     * @param string $message Message to log.
     * @param string $level Log level (info, debug, warning, error).
     */
    public static function log( $message, $level = 'info' ) {
        if ( class_exists( 'WC_Logger' ) ) {
            $logger = wc_get_logger();
            $context = array( 'source' => 'paper-id-woocommerce' );
            $logger->log( $level, $message, $context );
        }
    }
}
