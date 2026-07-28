<?php
/**
 * Plugin Name: Paper.id Payment Gateway for WooCommerce
 * Plugin URI: https://paper.id
 * Description: Accept Credit Cards, QRIS, Virtual Account, and E-Wallet payments in WooCommerce using Paper.id Payment Gateway.
 * Version: 1.0.6
 * Author: Joe
 * Author URI: https://paper.id
 * Text Domain: paper-id-woocommerce
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tests up to: 8.9
 * PHP requires at least: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'PAPER_ID_WC_VERSION', '1.0.6' );
define( 'PAPER_ID_WC_FILE', __FILE__ );
define( 'PAPER_ID_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'PAPER_ID_WC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize Paper.id WooCommerce Payment Gateway.
 */
function paper_id_wc_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'paper_id_wc_missing_wc_notice' );
        return;
    }

    // Include required classes
    require_once PAPER_ID_WC_PATH . 'includes/class-paper-id-helper.php';
    require_once PAPER_ID_WC_PATH . 'includes/class-paper-id-api.php';
    require_once PAPER_ID_WC_PATH . 'includes/class-paper-id-updater.php';
    require_once PAPER_ID_WC_PATH . 'includes/class-wc-gateway-paper-id.php';

    // Register Gateway to WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'paper_id_wc_add_gateway' );

    // Initialize GitHub Auto Updater if GitHub Repository is configured
    $options      = get_option( 'woocommerce_paper_id_settings', array() );
    $github_repo  = ! empty( $options['github_repo'] ) ? trim( $options['github_repo'] ) : 'TxNixon/paperid-woocommerce-payment-gateway';
    $github_token = ! empty( $options['github_token'] ) ? trim( $options['github_token'] ) : '';
    if ( ! empty( $github_repo ) ) {
        new Paper_ID_Updater( PAPER_ID_WC_FILE, $github_repo, $github_token );
    }
}
add_action( 'plugins_loaded', 'paper_id_wc_init', 11 );

/**
 * Add Paper.id Gateway to WooCommerce.
 *
 * @param array $gateways Registered WooCommerce gateways.
 * @return array Updated gateways list.
 */
function paper_id_wc_add_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Paper_ID';
    return $gateways;
}

/**
 * Display notice if WooCommerce is not active.
 */
function paper_id_wc_missing_wc_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php esc_html_e( 'Paper.id Payment Gateway requires WooCommerce to be installed and active.', 'paper-id-woocommerce' ); ?></p>
    </div>
    <?php
}

/**
 * Declare HPOS (High-Performance Order Storage) compatibility for WooCommerce.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PAPER_ID_WC_FILE, true );
    }
} );
