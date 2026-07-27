<?php
/**
 * Paper.id WooCommerce Payment Gateway Class
 *
 * Extends WC_Payment_Gateway to provide Paper.id payment integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_Paper_ID extends WC_Payment_Gateway {

    /**
     * Environment mode.
     * @var string
     */
    public $environment;

    /**
     * Paper.id Client ID.
     * @var string
     */
    public $client_id;

    /**
     * Paper.id Client Secret.
     * @var string
     */
    public $client_secret;

    /**
     * Logging setting.
     * @var bool
     */
    public $logging;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id                 = 'paper_id';
        $this->icon               = apply_filters( 'woocommerce_paper_id_icon', PAPER_ID_WC_URL . 'assets/images/paper-logo.svg' );
        $this->has_fields         = false;
        $this->method_title       = __( 'Paper.id Payment Gateway', 'paper-id-woocommerce' );
        $this->method_description = __( 'Terima pembayaran Kartu Kredit, QRIS, Virtual Account, dan E-Wallet secara otomatis melalui Paper.id.', 'paper-id-woocommerce' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title         = $this->get_option( 'title' );
        $this->description   = $this->get_option( 'description' );
        $this->environment   = $this->get_option( 'environment', 'sandbox' );
        $this->client_id     = $this->get_option( 'client_id' );
        $this->client_secret = $this->get_option( 'client_secret' );
        $this->logging       = 'yes' === $this->get_option( 'logging', 'no' );

        // Save admin options
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Webhook / Callback Handler
        add_action( 'woocommerce_api_wc_gateway_paper_id', array( $this, 'handle_webhook' ) );
    }

    /**
     * Initialize Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $callback_url = add_query_arg( 'wc-api', 'wc_gateway_paper_id', home_url( '/' ) );

        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Aktifkan/Nonaktifkan', 'paper-id-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Aktifkan Pembayaran Paper.id', 'paper-id-woocommerce' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Judul Pembayaran', 'paper-id-woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Judul yang dilihat pelanggan saat checkout.', 'paper-id-woocommerce' ),
                'default'     => __( 'Kartu Kredit / QRIS / Transfer via Paper.id', 'paper-id-woocommerce' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Deskripsi Pembayaran', 'paper-id-woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'Deskripsi yang dilihat pelanggan saat memilih metode ini.', 'paper-id-woocommerce' ),
                'default'     => __( 'Bayar dengan aman menggunakan Kartu Kredit (Visa/Mastercard), QRIS, Virtual Account, atau E-Wallet via Paper.id.', 'paper-id-woocommerce' ),
                'desc_tip'    => true,
            ),
            'environment' => array(
                'title'       => __( 'Mode Environment', 'paper-id-woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Pilih Sandbox untuk pengujian atau Production untuk transaksi live.', 'paper-id-woocommerce' ),
                'default'     => 'sandbox',
                'desc_tip'    => true,
                'options'     => array(
                    'sandbox'    => __( 'Sandbox (Pengujian)', 'paper-id-woocommerce' ),
                    'production' => __( 'Production (Live / Nyata)', 'paper-id-woocommerce' ),
                ),
            ),
            'client_id' => array(
                'title'       => __( 'Client ID', 'paper-id-woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Client ID dari Settings > API Dashboard di akun Paper.id Anda.', 'paper-id-woocommerce' ),
                'default'     => '',
            ),
            'client_secret' => array(
                'title'       => __( 'Client Secret', 'paper-id-woocommerce' ),
                'type'        => 'password',
                'description' => __( 'Client Secret dari Settings > API Dashboard di akun Paper.id Anda.', 'paper-id-woocommerce' ),
                'default'     => '',
            ),
            'callback_url' => array(
                'title'       => __( 'Callback / Webhook URL', 'paper-id-woocommerce' ),
                'type'        => 'title',
                'description' => sprintf(
                    __( 'Salin URL ini dan daftarkan pada menu <strong>Settings &gt; API Dashboard &gt; Callback URL</strong> di Paper.id:<br><code style="background:#fff;padding:6px 10px;display:inline-block;margin-top:5px;border:1px solid #ccc;font-weight:bold;">%s</code>', 'paper-id-woocommerce' ),
                    esc_url( $callback_url )
                ),
            ),
            'github_section' => array(
                'title'       => __( 'GitHub Auto-Update Settings', 'paper-id-woocommerce' ),
                'type'        => 'title',
                'description' => __( 'Pengaturan agar plugin ini dapat diperbarui secara otomatis dari GitHub saat ada versi baru.', 'paper-id-woocommerce' ),
            ),
            'github_repo' => array(
                'title'       => __( 'GitHub Repository', 'paper-id-woocommerce' ),
                'type'        => 'text',
                'placeholder' => 'TxNixon/paperid-woocommerce-payment-gateway',
                'description' => __( 'Format: <code>username/repository-name</code> (contoh: <code>TxNixon/paperid-woocommerce-payment-gateway</code>).', 'paper-id-woocommerce' ),
                'default'     => 'TxNixon/paperid-woocommerce-payment-gateway',
            ),
            'github_token' => array(
                'title'       => __( 'GitHub Personal Token (Opsional)', 'paper-id-woocommerce' ),
                'type'        => 'password',
                'description' => __( 'Hanya diisi jika repository GitHub Anda bersifat Private (Bukan Public).', 'paper-id-woocommerce' ),
                'default'     => '',
            ),
            'logging' => array(
                'title'       => __( 'Log Debugging', 'paper-id-woocommerce' ),
                'type'        => 'checkbox',
                'label'       => __( 'Aktifkan log transaksi untuk troubleshooting', 'paper-id-woocommerce' ),
                'default'     => 'no',
                'description' => sprintf( __( 'Log disimpan di WooCommerce status log (%s)', 'paper-id-woocommerce' ), '<code>WooCommerce > Status > Logs</code>' ),
            ),
        );
    }

    /**
     * Admin options page layout.
     */
    public function admin_options() {
        ?>
        <h2><?php esc_html_e( 'Paper.id Payment Gateway', 'paper-id-woocommerce' ); ?></h2>
        <div class="notice notice-info inline" style="padding:12px; margin-bottom:15px;">
            <p>
                <strong><?php esc_html_e( 'Informasi Integrasi Paper.id:', 'paper-id-woocommerce' ); ?></strong><br>
                <?php esc_html_e( 'Pastikan Anda telah mendaftar akun Paper.id dan mengaktifkan fitur Open API melalui tim Paper.id untuk mendapatkan Client ID & Client Secret.', 'paper-id-woocommerce' ); ?>
            </p>
        </div>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }

    /**
     * Process payment and redirect to Paper.id.
     *
     * @param int $order_id WooCommerce Order ID.
     * @return array Result status and redirect URL.
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Order tidak ditemukan.', 'paper-id-woocommerce' ), 'error' );
            return array( 'result' => 'fail' );
        }

        if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
            wc_add_notice( __( 'Konfigurasi Paper.id belum lengkap. Silakan masukkan Client ID & Client Secret di Pengaturan Admin.', 'paper-id-woocommerce' ), 'error' );
            return array( 'result' => 'fail' );
        }

        $callback_url = add_query_arg( 'wc-api', 'wc_gateway_paper_id', home_url( '/' ) );

        $api = new Paper_ID_API( $this->client_id, $this->client_secret, $this->environment, $this->logging );
        $result = $api->create_payment_request( $order, $callback_url );

        if ( is_wp_error( $result ) ) {
            wc_add_notice( $result->get_error_message(), 'error' );
            return array( 'result' => 'fail' );
        }

        if ( ! empty( $result['payment_url'] ) ) {
            // Save payment link metadata to order
            $order->update_meta_data( '_paper_id_payment_url', $result['payment_url'] );
            if ( isset( $result['raw']['data']['invoice_id'] ) ) {
                $order->update_meta_data( '_paper_id_invoice_id', $result['raw']['data']['invoice_id'] );
            }
            $order->save();

            $order->add_order_note( sprintf( __( 'Tautan pembayaran Paper.id berhasil dibuat: %s', 'paper-id-woocommerce' ), $result['payment_url'] ) );

            return array(
                'result'   => 'success',
                'redirect' => $result['payment_url'],
            );
        }

        wc_add_notice( __( 'Gagal mendapatkan URL pembayaran dari Paper.id.', 'paper-id-woocommerce' ), 'error' );
        return array( 'result' => 'fail' );
    }

    /**
     * Webhook / Callback Handler for Paper.id payment notifications.
     */
    public function handle_webhook() {
        $raw_input = file_get_contents( 'php://input' );

        if ( $this->logging ) {
            Paper_ID_Helper::log( 'Incoming Callback Payload: ' . $raw_input, 'info' );
        }

        $data = json_decode( $raw_input, true );

        if ( empty( $data ) && ! empty( $_POST ) ) {
            $data = $_POST;
        }

        if ( empty( $data ) ) {
            Paper_ID_Helper::log( 'Callback Error: Data payload kosong.', 'warning' );
            wp_send_json( array( 'status' => 'error', 'message' => 'Empty payload' ), 400 );
        }

        // Extract order identifier & status from Paper.id callback format
        $order_id       = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
        $external_id    = isset( $data['external_id'] ) ? sanitize_text_field( $data['external_id'] ) : '';
        $payment_status = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '';
        $transaction_id = isset( $data['transaction_id'] ) ? sanitize_text_field( $data['transaction_id'] ) : ( isset( $data['payment_id'] ) ? sanitize_text_field( $data['payment_id'] ) : '' );

        // If order_id not in root payload, try parsing from external_id (e.g. WC-123-1627383)
        if ( ! $order_id && ! empty( $external_id ) ) {
            if ( preg_match( '/WC-(\d+)-/', $external_id, $matches ) ) {
                $order_id = (int) $matches[1];
            }
        }

        if ( ! $order_id ) {
            Paper_ID_Helper::log( 'Callback Error: Order ID tidak dapat diidentifikasi.', 'error' );
            wp_send_json( array( 'status' => 'error', 'message' => 'Invalid order ID' ), 400 );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            Paper_ID_Helper::log( "Callback Error: Order #{$order_id} tidak ditemukan.", 'error' );
            wp_send_json( array( 'status' => 'error', 'message' => 'Order not found' ), 404 );
        }

        $wc_status = Paper_ID_Helper::map_status( $payment_status );

        Paper_ID_Helper::log( "Processing Callback for Order #{$order_id}. Paper Status: {$payment_status} -> WC Status: {$wc_status}", 'info' );

        if ( 'processing' === $wc_status ) {
            if ( ! $order->is_paid() ) {
                $order->payment_complete( $transaction_id );
                $order->add_order_note( sprintf( __( 'Pembayaran telah diverifikasi via Paper.id Webhook. Transaction ID: %s', 'paper-id-woocommerce' ), $transaction_id ) );
            }
        } elseif ( 'cancelled' === $wc_status ) {
            if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                $order->update_status( 'cancelled', __( 'Pembayaran Paper.id telah kedaluwarsa atau dibatalkan.', 'paper-id-woocommerce' ) );
            }
        } elseif ( 'failed' === $wc_status ) {
            if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
                $order->update_status( 'failed', __( 'Pembayaran Paper.id gagal.', 'paper-id-woocommerce' ) );
            }
        }

        wp_send_json( array(
            'status'  => 'success',
            'message' => 'Callback processed successfully',
            'order_id'=> $order_id,
        ), 200 );
    }
}
