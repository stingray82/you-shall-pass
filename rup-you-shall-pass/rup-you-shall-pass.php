<?php
/**
 * Plugin Name: You Shall Pass
 * Description: Bypass Protect the Shire!
 * Version: 1.0.1
 * Author:            ReallyUsefulPlugins.com
 * Author URI:        https://Reallyusefulplugins.com
 * Text Domain: rup-you-shall-pass
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * Website:           https://reallyusefulplugins.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RUP_YSP_VERSION', '1.0.0' );
define( 'RUP_YSP_FILE', __FILE__ );
define( 'RUP_YSP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RUP_YSP_SLUG', 'rup-you-shall-pass' );

require_once RUP_YSP_DIR . 'includes/updater.php';

final class RUP_You_Shall_Pass {
	const OPTION = 'rup_ysp_settings';
	const NONCE  = 'rup_ysp_save_settings';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_init', [ $this, 'handle_save' ] );
		add_action( 'admin_post_rup_ysp_clear_cache', [ $this, 'handle_clear_cache' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 100 );
		add_action( 'admin_init', [ $this, 'register_all_selected_updaters' ], 20 );
	}

	public static function defaults() {
		return [
			'mode'             => 'selected', // all|selected. Default is selected with nothing selected, so the plugin is off by default.
			'selected_plugins' => [],
			'selected_themes'  => [],
			'show_admin_bar'  => false,
		];
	}

	public static function settings() {
		$settings = get_option( self::OPTION, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$settings = wp_parse_args( $settings, self::defaults() );
		$settings['mode'] = in_array( $settings['mode'], [ 'all', 'selected' ], true ) ? $settings['mode'] : 'selected';
		$settings['selected_plugins'] = array_values( array_filter( array_map( 'sanitize_text_field', (array) $settings['selected_plugins'] ) ) );
		$settings['selected_themes']  = array_values( array_filter( array_map( 'sanitize_key', (array) $settings['selected_themes'] ) ) );
		$settings['show_admin_bar']  = ! empty( $settings['show_admin_bar'] );
		return $settings;
	}

	public function admin_menu() {
		add_options_page(
			'You Shall Pass',
			'You Shall Pass',
			'manage_options',
			'rup-you-shall-pass',
			[ $this, 'render_settings_page' ]
		);
	}

	public function handle_save() {
		if ( ! isset( $_POST['rup_ysp_action'] ) || $_POST['rup_ysp_action'] !== 'save_settings' ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'rup-you-shall-pass' ) );
		}
		check_admin_referer( self::NONCE );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'selected';
		if ( ! in_array( $mode, [ 'all', 'selected' ], true ) ) {
			$mode = 'selected';
		}

		$selected_plugins = isset( $_POST['selected_plugins'] ) ? (array) wp_unslash( $_POST['selected_plugins'] ) : [];
		$selected_plugins = array_values( array_filter( array_map( 'sanitize_text_field', $selected_plugins ) ) );

		$selected_themes = isset( $_POST['selected_themes'] ) ? (array) wp_unslash( $_POST['selected_themes'] ) : [];
		$selected_themes = array_values( array_filter( array_map( 'sanitize_key', $selected_themes ) ) );

		$show_admin_bar = ! empty( $_POST['show_admin_bar'] );

		update_option( self::OPTION, [
			'mode'             => $mode,
			'selected_plugins' => $selected_plugins,
			'selected_themes'  => $selected_themes,
			'show_admin_bar'  => $show_admin_bar,
		], false );

		// Clear native and YSP/UUPD caches so the new targeting applies quickly.
		$this->clear_update_caches();

		wp_safe_redirect( add_query_arg( 'rup_ysp_saved', '1', wp_get_referer() ?: admin_url( 'options-general.php?page=rup-you-shall-pass' ) ) );
		exit;
	}

	private function cache_ttl() {
		/**
		 * Controls how long WordPress.org metadata is cached by You Shall Pass.
		 *
		 * Default: 6 hours.
		 */
		$ttl = (int) apply_filters( 'rup_ysp_cache_ttl', 6 * HOUR_IN_SECONDS );
		return max( 0, $ttl );
	}

	private function metadata_cache_key( $type, $slug ) {
		return 'rup_ysp_' . sanitize_key( $type ) . '_' . sanitize_key( $slug );
	}

	private function get_cached_metadata( $type, $slug ) {
		$ttl = $this->cache_ttl();
		if ( $ttl <= 0 ) {
			return false;
		}
		return get_site_transient( $this->metadata_cache_key( $type, $slug ) );
	}

	private function set_cached_metadata( $type, $slug, $metadata ) {
		$ttl = $this->cache_ttl();
		if ( $ttl <= 0 ) {
			return;
		}
		set_site_transient( $this->metadata_cache_key( $type, $slug ), $metadata, $ttl );
	}

	public function clear_update_caches() {
		global $wpdb;

		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );

		// Clear You Shall Pass WordPress.org metadata caches.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_rup_ysp_%' OR option_name LIKE '_site_transient_timeout_rup_ysp_%'" );

		// Clear UUPD caches created for this plugin's vendor (`rup`).
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_uupd_rup__%' OR option_name LIKE '_transient_timeout_uupd_rup__%'" );
	}

	public function handle_clear_cache() {
		if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) {
			wp_die( esc_html__( 'You do not have permission to refresh updates.', 'rup-you-shall-pass' ) );
		}

		check_admin_referer( 'rup_ysp_clear_cache' );
		$this->clear_update_caches();

		wp_update_plugins();
		wp_update_themes();

		$redirect = wp_get_referer() ?: admin_url( 'options-general.php?page=rup-you-shall-pass' );
		wp_safe_redirect( add_query_arg( 'rup_ysp_cache_cleared', '1', $redirect ) );
		exit;
	}

	private function clear_cache_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=rup_ysp_clear_cache' ), 'rup_ysp_clear_cache' );
	}

	public function admin_bar_menu( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) ) {
			return;
		}

		$settings = self::settings();
		if ( empty( $settings['show_admin_bar'] ) ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id'    => 'rup-ysp-check-palantir',
			'title' => 'Check the Palantír',
			'href'  => $this->clear_cache_url(),
			'meta'  => [
				'title' => 'Clear You Shall Pass caches and check updates',
			],
		] );
	}

	private function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
	}

	private function plugin_slug_from_file( $plugin_file ) {
		$dir = dirname( $plugin_file );
		if ( '.' !== $dir && '' !== $dir ) {
			return sanitize_key( basename( $dir ) );
		}
		return sanitize_key( basename( $plugin_file, '.php' ) );
	}

	private function rest_metadata_url( $type, $slug ) {
		return rest_url( sprintf( 'rup-ysp/v1/%s/%s.json', rawurlencode( $type ), rawurlencode( $slug ) ) );
	}

	public function register_all_selected_updaters() {
		if ( ! is_admin() || ! class_exists( '\\RUP\\YouShallPass\\Updater\\Updater_V2' ) ) {
			return;
		}

		$settings = self::settings();
		$plugins  = $this->get_plugins();
		$self     = plugin_basename( RUP_YSP_FILE );

		foreach ( $plugins as $plugin_file => $data ) {
			if ( $plugin_file === $self ) {
				continue;
			}
			$slug = $this->plugin_slug_from_file( $plugin_file );
			if ( ! $this->is_targeted( 'plugin', $plugin_file, $settings, $slug ) ) {
				continue;
			}

			\RUP\YouShallPass\Updater\Updater_V2::register( [
				'vendor'      => 'rup',
				'plugin_file' => $plugin_file,
				'slug'        => $slug,
				'name'        => ! empty( $data['Name'] ) ? $data['Name'] : $slug,
				'version'     => ! empty( $data['Version'] ) ? $data['Version'] : '0.0.0',
				'server'      => $this->rest_metadata_url( 'plugin', $slug ),
				'mode'        => 'json',
			] );
		}

		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$slug = sanitize_key( $stylesheet );
			if ( ! $this->is_targeted( 'theme', $slug, $settings, $slug ) ) {
				continue;
			}

			\RUP\YouShallPass\Updater\Updater_V2::register( [
				'vendor'    => 'rup',
				'slug'      => $slug,
				'real_slug' => $slug,
				'name'      => $theme->get( 'Name' ) ?: $slug,
				'version'   => $theme->get( 'Version' ) ?: '0.0.0',
				'server'    => $this->rest_metadata_url( 'theme', $slug ),
				'mode'      => 'json',
			] );
		}
	}

	private function is_targeted( $type, $key, array $settings, $slug = '' ) {
		$slug = sanitize_key( $slug ?: $key );

		/**
		 * Force all repository update overrides on programmatically.
		 *
		 * Return true to target every installed plugin and theme, regardless of the
		 * saved UI mode. This is useful for fleet/automated deployments.
		 *
		 * Example:
		 * add_filter( 'rup_ysp_apply_all_updates', '__return_true' );
		 */
		$apply_all = apply_filters( 'rup_ysp_apply_all_updates', 'all' === $settings['mode'], $settings );
		if ( $apply_all ) {
			return true;
		}

		if ( 'plugin' === $type ) {
			/**
			 * Add plugin basenames to the selected list programmatically.
			 * Example value: [ 'woocommerce/woocommerce.php' ]
			 */
			$selected_plugin_files = apply_filters( 'rup_ysp_selected_plugins', $settings['selected_plugins'], $settings );
			$selected_plugin_files = array_values( array_filter( array_map( 'sanitize_text_field', (array) $selected_plugin_files ) ) );

			/**
			 * Add WordPress.org plugin slugs programmatically.
			 * Example value: [ 'woocommerce', 'fluent-cart' ]
			 */
			$selected_plugin_slugs = apply_filters( 'rup_ysp_selected_plugin_slugs', [], $settings );
			$selected_plugin_slugs = array_values( array_filter( array_map( 'sanitize_key', (array) $selected_plugin_slugs ) ) );

			return in_array( $key, $selected_plugin_files, true ) || in_array( $slug, $selected_plugin_slugs, true );
		}

		/**
		 * Add WordPress.org theme slugs programmatically.
		 * Example value: [ 'twentytwentyfive', 'astra' ]
		 */
		$selected_theme_slugs = apply_filters( 'rup_ysp_selected_theme_slugs', $settings['selected_themes'], $settings );
		$selected_theme_slugs = array_values( array_filter( array_map( 'sanitize_key', (array) $selected_theme_slugs ) ) );

		return in_array( $slug, $selected_theme_slugs, true );
	}

	public function register_rest_routes() {
		register_rest_route( 'rup-ysp/v1', '/plugin/(?P<slug>[a-z0-9_\-.]+)\.json', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_plugin_metadata' ],
			'permission_callback' => '__return_true',
			'args'                => [ 'slug' => [ 'sanitize_callback' => 'sanitize_key' ] ],
		] );

		register_rest_route( 'rup-ysp/v1', '/theme/(?P<slug>[a-z0-9_\-.]+)\.json', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_theme_metadata' ],
			'permission_callback' => '__return_true',
			'args'                => [ 'slug' => [ 'sanitize_callback' => 'sanitize_key' ] ],
		] );
	}

	public function rest_plugin_metadata( WP_REST_Request $request ) {
		$slug = sanitize_key( $request['slug'] );
		$cached = $this->get_cached_metadata( 'plugin', $slug );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$url  = sprintf( 'https://api.wordpress.org/plugins/info/1.0/%s.json', rawurlencode( $slug ) );
		$response = wp_remote_get( $url, [ 'timeout' => 15, 'headers' => [ 'Accept' => 'application/json' ] ] );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'rup_ysp_wporg_error', $response->get_error_message(), [ 'status' => 502 ] );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'rup_ysp_wporg_http_error', 'WordPress.org returned HTTP ' . $code, [ 'status' => 502 ] );
		}
		$meta = json_decode( $body, true );
		if ( ! is_array( $meta ) || empty( $meta['version'] ) ) {
			return new WP_Error( 'rup_ysp_wporg_not_found', 'No plugin metadata found for ' . $slug, [ 'status' => 404 ] );
		}
		$normalized = $this->normalize_plugin_metadata( $meta );
		$this->set_cached_metadata( 'plugin', $slug, $normalized );
		return rest_ensure_response( $normalized );
	}

	public function rest_theme_metadata( WP_REST_Request $request ) {
		$slug = sanitize_key( $request['slug'] );
		$cached = $this->get_cached_metadata( 'theme', $slug );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$url  = add_query_arg( [
			'action' => 'theme_information',
			'request' => [
				'slug'   => $slug,
				'fields' => [
					'sections'     => true,
					'tags'         => true,
					'screenshot_url'=> true,
				],
			],
		], 'https://api.wordpress.org/themes/info/1.2/' );

		$response = wp_remote_get( $url, [ 'timeout' => 15, 'headers' => [ 'Accept' => 'application/json' ] ] );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'rup_ysp_wporg_error', $response->get_error_message(), [ 'status' => 502 ] );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'rup_ysp_wporg_http_error', 'WordPress.org returned HTTP ' . $code, [ 'status' => 502 ] );
		}
		$meta = json_decode( $body, true );
		if ( ! is_array( $meta ) || empty( $meta['version'] ) ) {
			return new WP_Error( 'rup_ysp_wporg_not_found', 'No theme metadata found for ' . $slug, [ 'status' => 404 ] );
		}
		$normalized = $this->normalize_theme_metadata( $meta, $slug );
		$this->set_cached_metadata( 'theme', $slug, $normalized );
		return rest_ensure_response( $normalized );
	}

	private function normalize_plugin_metadata( array $m ) {
		$download = $m['download_link'] ?? '';
		return [
			'name'           => $m['name'] ?? ( $m['slug'] ?? '' ),
			'slug'           => $m['slug'] ?? '',
			'version'        => $m['version'] ?? '',
			'author'         => $m['author'] ?? '',
			'author_profile' => $m['author_profile'] ?? '',
			'homepage'       => $m['homepage'] ?? ( isset( $m['slug'] ) ? 'https://wordpress.org/plugins/' . $m['slug'] . '/' : '' ),
			'requires'       => $m['requires'] ?? '',
			'tested'         => $m['tested'] ?? '',
			'requires_php'   => $m['requires_php'] ?? '',
			'last_updated'   => $m['last_updated'] ?? '',
			'download_url'   => $download,
			'download_link'  => $download,
			'package'        => $download,
			'sections'       => $m['sections'] ?? [],
			'icons'          => $m['icons'] ?? [],
			'banners'        => $m['banners'] ?? [],
			'screenshots'    => $m['screenshots'] ?? [],
		];
	}

	private function normalize_theme_metadata( array $m, $slug ) {
		$download = $m['download_link'] ?? $m['download_url'] ?? '';
		if ( ! $download && ! empty( $m['version'] ) ) {
			$download = sprintf( 'https://downloads.wordpress.org/theme/%s.%s.zip', rawurlencode( $slug ), rawurlencode( $m['version'] ) );
		}
		return [
			'name'          => $m['name'] ?? $slug,
			'slug'          => $m['slug'] ?? $slug,
			'version'       => $m['version'] ?? '',
			'author'        => $m['author'] ?? '',
			'homepage'      => $m['homepage'] ?? ( 'https://wordpress.org/themes/' . $slug . '/' ),
			'requires'      => $m['requires'] ?? '',
			'tested'        => $m['tested'] ?? '',
			'requires_php'  => $m['requires_php'] ?? '',
			'last_updated'  => $m['last_updated'] ?? '',
			'download_url'  => $download,
			'download_link' => $download,
			'package'       => $download,
			'screenshot'    => $m['screenshot_url'] ?? $m['screenshot'] ?? '',
			'sections'      => $m['sections'] ?? [],
		];
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = self::settings();
		$plugins  = $this->get_plugins();
		$themes   = wp_get_themes();
		?>
		<div class="wrap">
			<h1>RUP – You Shall Pass</h1>
			<?php if ( isset( $_GET['rup_ysp_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Settings saved. WordPress update caches were cleared.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['rup_ysp_cache_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>The Palantír was checked. You Shall Pass, UUPD, and WordPress update caches were cleared and update checks were refreshed.</p></div>
			<?php endif; ?>

			<?php if ( 'selected' === $settings['mode'] && empty( $settings['selected_plugins'] ) && empty( $settings['selected_themes'] ) ) : ?>
				<div class="notice notice-info"><p><strong>You Shall Pass is currently inactive.</strong> No plugins or themes are selected, so no update overrides will run.</p></div>
			<?php endif; ?>

			<p>You Shall Pass is off by default. It only registers update overrides for individually selected plugins/themes, unless you deliberately switch to all-items mode. It uses UUPD to serve normalized metadata from WordPress.org repository APIs into the native dashboard update system.</p>
			<p><a class="button button-secondary" href="<?php echo esc_url( $this->clear_cache_url() ); ?>">Check the Palantír</a> <span class="description">Clears YSP/UUPD/native update caches and forces a fresh update check. WordPress.org metadata is cached for <?php echo esc_html( human_time_diff( 0, $this->cache_ttl() ) ); ?> by default.</span></p>

			<h2>Developer Filters</h2>
			<p>Automated setups can enable targeting without changing the saved UI settings:</p>
			<pre><code>add_filter( 'rup_ysp_selected_plugin_slugs', function( $slugs ) {
    return array_merge( $slugs, [ 'fluent-cart', 'woocommerce' ] );
} );

add_filter( 'rup_ysp_selected_theme_slugs', function( $slugs ) {
    return array_merge( $slugs, [ 'astra' ] );
} );

add_filter( 'rup_ysp_apply_all_updates', '__return_true' );

add_filter( 'rup_ysp_cache_ttl', function() {
    return 6 * HOUR_IN_SECONDS;
} );</code></pre>

			<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=rup-you-shall-pass' ) ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="rup_ysp_action" value="save_settings" />

				<h2>Targeting</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Apply updates to</th>
						<td>
							<label><input type="radio" name="mode" value="selected" <?php checked( $settings['mode'], 'selected' ); ?> /> Only selected plugins and themes below (default/off until selections are made)</label><br />
							<label><input type="radio" name="mode" value="all" <?php checked( $settings['mode'], 'all' ); ?> /> All installed plugins and themes</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Admin bar shortcut</th>
						<td>
							<label><input type="checkbox" name="show_admin_bar" value="1" <?php checked( ! empty( $settings['show_admin_bar'] ) ); ?> /> Show “Check the Palantír” in the admin bar</label>
						</td>
					</tr>
				</table>

				<h2>Plugins</h2>
				<table class="widefat striped">
					<thead><tr><th style="width:48px">Use</th><th>Plugin</th><th>Installed version</th><th>Repo slug</th></tr></thead>
					<tbody>
					<?php foreach ( $plugins as $file => $data ) : if ( $file === plugin_basename( RUP_YSP_FILE ) ) { continue; } $slug = $this->plugin_slug_from_file( $file ); ?>
						<tr>
							<td><input type="checkbox" name="selected_plugins[]" value="<?php echo esc_attr( $file ); ?>" <?php checked( in_array( $file, $settings['selected_plugins'], true ) ); ?> /></td>
							<td><strong><?php echo esc_html( $data['Name'] ?? $file ); ?></strong><br /><code><?php echo esc_html( $file ); ?></code></td>
							<td><?php echo esc_html( $data['Version'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( $slug ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h2>Themes</h2>
				<table class="widefat striped">
					<thead><tr><th style="width:48px">Use</th><th>Theme</th><th>Installed version</th><th>Repo slug</th></tr></thead>
					<tbody>
					<?php foreach ( $themes as $stylesheet => $theme ) : ?>
						<tr>
							<td><input type="checkbox" name="selected_themes[]" value="<?php echo esc_attr( $stylesheet ); ?>" <?php checked( in_array( $stylesheet, $settings['selected_themes'], true ) ); ?> /></td>
							<td><strong><?php echo esc_html( $theme->get( 'Name' ) ?: $stylesheet ); ?></strong><br /><code><?php echo esc_html( $stylesheet ); ?></code></td>
							<td><?php echo esc_html( $theme->get( 'Version' ) ); ?></td>
							<td><code><?php echo esc_html( $stylesheet ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}
}

RUP_You_Shall_Pass::instance();

