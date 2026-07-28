<?php
/**
 * Paper.id GitHub Auto Updater Class
 *
 * Checks GitHub repository releases and enables automatic plugin updates directly from GitHub.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Paper_ID_Updater {

    /**
     * GitHub Repo Identifier (e.g. 'username/repo-name').
     * @var string
     */
    private $github_repo;

    /**
     * GitHub Personal Access Token (optional for private repos).
     * @var string
     */
    private $github_token;

    /**
     * Plugin slug / file path relative to plugins dir.
     * @var string
     */
    private $plugin_file;

    /**
     * Current plugin version.
     * @var string
     */
    private $current_version;

    /**
     * Plugin slug name.
     * @var string
     */
    private $slug;

    /**
     * Constructor.
     *
     * @param string $plugin_file Main plugin file path.
     * @param string $github_repo GitHub repo (username/repository).
     * @param string $github_token Optional GitHub PAT token for private repos.
     */
    public function __construct( $plugin_file, $github_repo, $github_token = '' ) {
        $this->plugin_file  = $plugin_file;
        $repo               = trim( $github_repo );
        $repo               = preg_replace( '#^https?://github\.com/#i', '', $repo );
        $repo               = trim( $repo, '/' );
        $this->github_repo  = ! empty( $repo ) ? $repo : 'TxNixon/wc-paperid';
        $this->github_token = trim( $github_token );
        $this->slug         = plugin_basename( $plugin_file );
        $this->current_version = PAPER_ID_WC_VERSION;

        if ( ! empty( $this->github_repo ) ) {
            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
            add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
            add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
        }
    }

    /**
     * Get Latest Release details from GitHub API.
     *
     * @return object|false Release data or false on failure.
     */
    public function get_latest_release() {
        $transient_key = 'paper_id_gh_release_' . md5( $this->github_repo );
        $cached        = get_transient( $transient_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";

        $headers = array(
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress-PaperID-Updater',
        );

        if ( ! empty( $this->github_token ) ) {
            $headers['Authorization'] = 'token ' . $this->github_token;
        }

        $response = wp_remote_get( $url, array(
            'headers' => $headers,
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            set_transient( $transient_key, false, 600 ); // Cache failure for 10 mins
            return false;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! empty( $release ) && isset( $release->tag_name ) ) {
            set_transient( $transient_key, $release, 43200 ); // Cache 12 hours
            return $release;
        }

        return false;
    }

    /**
     * Check update filter hook for WordPress upgrader.
     *
     * @param object $transient
     * @return object
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();

        if ( ! $release ) {
            return $transient;
        }

        $new_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $this->current_version, $new_version, '<' ) ) {
            $package_url = '';

            // Check if zip asset is attached to release, otherwise fallback to zipball
            if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
                foreach ( $release->assets as $asset ) {
                    if ( isset( $asset->browser_download_url ) && substr( $asset->browser_download_url, -4 ) === '.zip' ) {
                        $package_url = $asset->browser_download_url;
                        break;
                    }
                }
            }

            if ( empty( $package_url ) && ! empty( $release->zipball_url ) ) {
                $package_url = $release->zipball_url;
            }

            $obj              = new stdClass();
            $obj->slug        = dirname( $this->slug );
            $obj->plugin      = $this->slug;
            $obj->new_version = $new_version;
            $obj->url         = "https://github.com/{$this->github_repo}";
            $obj->package     = $package_url;
            $obj->tested      = '6.5';

            $transient->response[ $this->slug ] = $obj;
        }

        return $transient;
    }

    /**
     * Plugin details popup modal in WordPress admin dashboard.
     *
     * @param bool   $result
     * @param string $action
     * @param object $args
     * @return object|bool
     */
    public function plugin_popup( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ! isset( $args->slug ) || $args->slug !== dirname( $this->slug ) ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $new_version = ltrim( $release->tag_name, 'v' );

        $obj                = new stdClass();
        $obj->name          = 'Paper.id Payment Gateway for WooCommerce';
        $obj->slug          = dirname( $this->slug );
        $obj->version       = $new_version;
        $obj->author        = '<a href="https://github.com/' . esc_attr( strtok( $this->github_repo, '/' ) ) . '">Joe</a>';
        $obj->homepage      = "https://github.com/{$this->github_repo}";
        $obj->requires      = '5.0';
        $obj->tested        = '6.5';
        $obj->downloaded    = 1000;
        $obj->last_updated  = isset( $release->published_at ) ? date( 'Y-m-d', strtotime( $release->published_at ) ) : date( 'Y-m-d' );

        $changelog = isset( $release->body ) ? esc_html( $release->body ) : __( 'Pembaruan versi terbaru dari GitHub.', 'paper-id-woocommerce' );

        $obj->sections = array(
            'description' => __( 'Integrasi resmi & mudah untuk menerima pembayaran Kartu Kredit, QRIS, Virtual Account, dan E-Wallet melalui Paper.id di toko WooCommerce Anda.', 'paper-id-woocommerce' ),
            'changelog'   => nl2br( $changelog ),
        );

        return $obj;
    }

    /**
     * Move and rename folder properly after unzipping GitHub release download.
     *
     * @param bool  $response
     * @param array $hook_extra
     * @param array $result
     * @return array
     */
    public function post_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        $target_folder = WP_PLUGIN_DIR . '/' . dirname( $this->slug );
        $proper_destination = $target_folder;

        if ( isset( $result['destination'] ) && $result['destination'] !== $proper_destination ) {
            $wp_filesystem->move( $result['destination'], $proper_destination );
            $result['destination'] = $proper_destination;
        }

        return $result;
    }
}
