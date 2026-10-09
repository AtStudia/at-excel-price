<?php
/**
 * Update the plugin from GitHub Releases.
 *
 * Bypasses Easy Updates Manager gaps by:
 * - injecting into update_plugins at very high priority
 * - drawing our own update row on the Plugins screen
 * - offering a one-click install from the plugin settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATEP_Updater {

	const CACHE = 'atep_github_release';

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'offer' ), 10, 4 );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ), 99999 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject' ), 99999 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'source' ), 10, 4 );
		add_filter( 'http_request_args', array( __CLASS__, 'auth_headers' ), 10, 2 );
		add_action( 'load-plugins.php', array( __CLASS__, 'prepare_plugins_screen' ) );
		add_action( 'after_plugin_row_' . plugin_basename( ATEP_FILE ), array( __CLASS__, 'plugin_row' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	/**
	 * @return string owner/repo or empty.
	 */
	public static function repo() {
		$settings = ATEP_Plugin::settings();
		$repo     = '';
		if ( ! empty( $settings['github_repo'] ) ) {
			$repo = $settings['github_repo'];
		} elseif ( defined( 'ATEP_GITHUB_REPO' ) ) {
			$repo = ATEP_GITHUB_REPO;
		}
		return self::sanitize_repo( $repo );
	}

	/**
	 * @param mixed $repo Raw value.
	 * @return string
	 */
	public static function sanitize_repo( $repo ) {
		$repo = trim( (string) $repo );
		$repo = preg_replace( '#^https?://github\.com/#i', '', $repo );
		$repo = preg_replace( '#\.git$#i', '', (string) $repo );
		$repo = trim( (string) $repo, "/ \t\n\r" );
		if ( ! preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ) {
			return '';
		}
		return $repo;
	}

	public static function flush_cache() {
		delete_site_transient( self::CACHE );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Make sure the transient contains our package before Plugins list renders.
	 */
	public static function prepare_plugins_screen() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		self::force_transient();
	}

	/**
	 * Write/update the update_plugins transient with our payload when available.
	 */
	public static function force_transient() {
		$payload = self::payload();
		$plugin  = plugin_basename( ATEP_FILE );

		$transient = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}
		if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
			$transient->checked = array();
		}
		$transient->checked[ $plugin ] = self::installed_version();

		if ( $payload ) {
			unset( $transient->no_update[ $plugin ] );
			$transient->response[ $plugin ] = $payload;
		} else {
			unset( $transient->response[ $plugin ] );
			$transient->no_update[ $plugin ] = self::current_item();
		}

		set_site_transient( 'update_plugins', $transient );
	}

	/**
	 * Installed plugin version from the header (not only the constant).
	 *
	 * @return string
	 */
	public static function installed_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data( ATEP_FILE, false, false );
		if ( ! empty( $data['Version'] ) ) {
			return (string) $data['Version'];
		}
		return ATEP_VERSION;
	}

	/**
	 * @param array|false          $update      Update payload.
	 * @param array<string,string> $plugin_data Headers.
	 * @param string               $plugin_file Plugin file.
	 * @param string[]             $locales     Locales.
	 * @return array|false|object
	 */
	public static function offer( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );

		if ( plugin_basename( ATEP_FILE ) !== $plugin_file ) {
			return $update;
		}

		$payload = self::payload();
		return $payload ? $payload : $update;
	}

	/**
	 * Inject update into the plugins transient.
	 *
	 * @param mixed $transient Transient value.
	 * @return mixed
	 */
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$plugin  = plugin_basename( ATEP_FILE );
		$payload = self::payload();

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		if ( $payload ) {
			unset( $transient->no_update[ $plugin ] );
			$transient->response[ $plugin ] = $payload;
			return $transient;
		}

		unset( $transient->response[ $plugin ] );
		if ( ! isset( $transient->no_update[ $plugin ] ) ) {
			$transient->no_update[ $plugin ] = self::current_item();
		}

		return $transient;
	}

	/**
	 * Custom update row if core / Easy Updates Manager hide the default one.
	 *
	 * @param string               $file        Plugin basename.
	 * @param array<string,string> $plugin_data Headers.
	 */
	public static function plugin_row( $file, $plugin_data ) {
		unset( $plugin_data );

		if ( plugin_basename( ATEP_FILE ) !== $file || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$payload = self::payload();
		if ( ! $payload ) {
			return;
		}

		self::force_transient();

		$wp_list_table = function_exists( '_get_list_table' ) ? _get_list_table( 'WP_Plugins_List_Table' ) : null;
		$colspan       = ( $wp_list_table && method_exists( $wp_list_table, 'get_column_count' ) ) ? (int) $wp_list_table->get_column_count() : 4;
		$upgrade_url   = wp_nonce_url(
			self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ),
			'upgrade-plugin_' . $file
		);
		$settings_url  = admin_url( 'admin.php?page=at-excel-price-style' );

		printf(
			'<tr class="plugin-update-tr active" id="at-excel-price-update" data-slug="at-excel-price" data-plugin="%1$s"><td colspan="%2$d" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p>',
			esc_attr( $file ),
			(int) $colspan
		);
		echo esc_html(
			sprintf(
				/* translators: 1: current version, 2: new version */
				__( 'AT Excel Price %1$s → доступна %2$s с GitHub.', 'at-excel-price' ),
				self::installed_version(),
				$payload->new_version
			)
		);
		echo ' ';
		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $upgrade_url ),
			esc_html__( 'Обновить сейчас', 'at-excel-price' )
		);
		echo ' | ';
		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Открыть настройки', 'at-excel-price' )
		);
		echo '</p></div></td></tr>';
	}

	/**
	 * Notice on plugin settings / plugins screens.
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$ok = in_array( $screen->id, array( 'plugins', 'toplevel_page_at-excel-price', 'at-excel-price_page_at-excel-price-style' ), true );
		if ( ! $ok ) {
			return;
		}

		$payload = self::payload();
		if ( ! $payload ) {
			return;
		}

		$file        = plugin_basename( ATEP_FILE );
		$upgrade_url = wp_nonce_url(
			self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ),
			'upgrade-plugin_' . $file
		);

		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: new version */
				__( 'Доступно обновление AT Excel Price %s.', 'at-excel-price' ),
				$payload->new_version
			)
		);
		echo ' ';
		printf(
			'<a class="button button-primary" href="%1$s">%2$s</a>',
			esc_url( $upgrade_url ),
			esc_html__( 'Обновить', 'at-excel-price' )
		);
		echo '</p></div>';
	}

	/**
	 * Install/upgrade from GitHub package via Plugin_Upgrader.
	 *
	 * @return true|WP_Error
	 */
	public static function install_from_github() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return new WP_Error( 'atep_cap', __( 'Недостаточно прав для обновления плагинов.', 'at-excel-price' ) );
		}

		self::flush_cache();
		$payload = self::payload();
		if ( ! $payload || empty( $payload->package ) ) {
			$remote = self::remote();
			if ( ! empty( $remote['error'] ) ) {
				return new WP_Error( 'atep_remote', $remote['error'] );
			}
			return new WP_Error( 'atep_no_update', __( 'Новая версия на GitHub не найдена.', 'at-excel-price' ) );
		}

		self::force_transient();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( plugin_basename( ATEP_FILE ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			$messages = method_exists( $skin, 'get_upgrade_messages' ) ? $skin->get_upgrade_messages() : array();
			$detail   = is_array( $messages ) ? implode( ' ', $messages ) : '';
			return new WP_Error(
				'atep_upgrade_failed',
				$detail ? $detail : __( 'Не удалось установить обновление.', 'at-excel-price' )
			);
		}

		activate_plugin( plugin_basename( ATEP_FILE ), '', false, true );
		self::flush_cache();
		return true;
	}

	/**
	 * @return object|false
	 */
	public static function payload() {
		$remote = self::remote();
		if ( empty( $remote['version'] ) || empty( $remote['package'] ) ) {
			return false;
		}

		if ( version_compare( $remote['version'], self::installed_version(), '<=' ) ) {
			return false;
		}

		return (object) array(
			'id'            => 'github.com/' . self::repo(),
			'slug'          => 'at-excel-price',
			'plugin'        => plugin_basename( ATEP_FILE ),
			'version'       => $remote['version'],
			'new_version'   => $remote['version'],
			'url'           => $remote['url'],
			'package'       => $remote['package'],
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'tested'        => '',
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'compatibility' => new stdClass(),
		);
	}

	/**
	 * @return object
	 */
	private static function current_item() {
		$version = self::installed_version();
		return (object) array(
			'id'            => 'github.com/' . self::repo(),
			'slug'          => 'at-excel-price',
			'plugin'        => plugin_basename( ATEP_FILE ),
			'version'       => $version,
			'new_version'   => $version,
			'url'           => 'https://github.com/' . self::repo(),
			'package'       => '',
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'tested'        => '',
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'compatibility' => new stdClass(),
		);
	}

	/**
	 * @param false|object|array $result Result.
	 * @param string             $action Action.
	 * @param object             $args   Args.
	 * @return false|object|array
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$slug = ( is_object( $args ) && ! empty( $args->slug ) ) ? (string) $args->slug : '';
		if ( 'at-excel-price' !== $slug ) {
			return $result;
		}

		$remote = self::remote();
		if ( empty( $remote['version'] ) ) {
			return $result;
		}

		$notes = ! empty( $remote['notes'] ) ? $remote['notes'] : __( 'Изменения описаны в релизе GitHub.', 'at-excel-price' );

		return (object) array(
			'name'          => 'AT Excel Price',
			'slug'          => 'at-excel-price',
			'version'       => $remote['version'],
			'author'        => 'AT',
			'homepage'      => $remote['url'],
			'download_link' => $remote['package'],
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'sections'      => array(
				'description' => __( 'Вывод прайса Excel на странице WordPress.', 'at-excel-price' ),
				'changelog'   => wpautop( esc_html( $notes ) ),
			),
		);
	}

	/**
	 * Point WordPress at the folder that contains the plugin bootstrap file.
	 *
	 * @param string      $source        Extracted path.
	 * @param string      $remote_source Remote source.
	 * @param WP_Upgrader $upgrader      Upgrader.
	 * @param array       $hook_extra    Extra.
	 * @return string
	 */
	public static function source( $source, $remote_source, $upgrader, $hook_extra ) {
		unset( $remote_source, $upgrader );

		if ( empty( $hook_extra['plugin'] ) || plugin_basename( ATEP_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}

		$source = trailingslashit( $source );
		if ( file_exists( $source . 'at-excel-price.php' ) ) {
			return $source;
		}

		$nested = $source . 'at-excel-price/at-excel-price.php';
		if ( file_exists( $nested ) ) {
			return trailingslashit( dirname( $nested ) );
		}

		return $source;
	}

	/**
	 * Attach a token when downloading a private release.
	 *
	 * @param array<string,mixed> $args Request args.
	 * @param string              $url  URL.
	 * @return array<string,mixed>
	 */
	public static function auth_headers( $args, $url ) {
		$repo  = self::repo();
		$token = self::token();
		if ( '' === $repo || '' === $token || false === strpos( $url, $repo ) ) {
			return $args;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $host, array( 'github.com', 'api.github.com', 'codeload.github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com' ), true ) ) {
			return $args;
		}

		if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		if ( empty( $args['headers']['Authorization'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		return $args;
	}

	/**
	 * @return array{version:string,package:string,url:string,notes:string,error:string}
	 */
	public static function remote() {
		$empty = array(
			'version' => '',
			'package' => '',
			'url'     => '',
			'notes'   => '',
			'error'   => '',
		);

		$repo = self::repo();
		if ( '' === $repo ) {
			$empty['error'] = __( 'Укажите репозиторий GitHub в формате owner/repo.', 'at-excel-price' );
			return $empty;
		}

		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) && array_key_exists( 'version', $cached ) ) {
			return array_merge( $empty, $cached );
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . $repo . '/releases/latest',
			array(
				'timeout' => 20,
				'headers' => self::headers(),
			)
		);

		$result        = $empty;
		$result['url'] = 'https://github.com/' . $repo;

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			set_site_transient( self::CACHE, $result, 15 * MINUTE_IN_SECONDS );
			return $result;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 404 === $code ) {
			$result['error'] = __( 'Релиз на GitHub не найден. Опубликуйте тег, например v1.3.2.', 'at-excel-price' );
			set_site_transient( self::CACHE, $result, 15 * MINUTE_IN_SECONDS );
			return $result;
		}

		if ( 200 !== $code || ! is_array( $body ) ) {
			$result['error'] = sprintf(
				/* translators: %d: HTTP status */
				__( 'GitHub ответил кодом %d. Возможны лимит API или блокировка исходящих запросов на сервере.', 'at-excel-price' ),
				$code
			);
			set_site_transient( self::CACHE, $result, 15 * MINUTE_IN_SECONDS );
			return $result;
		}

		$tag     = isset( $body['tag_name'] ) ? (string) $body['tag_name'] : '';
		$version = ltrim( $tag, 'vV' );
		$package = '';

		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( empty( $asset['name'] ) || 'at-excel-price.zip' !== $asset['name'] ) {
					continue;
				}
				if ( ! empty( $asset['browser_download_url'] ) ) {
					$package = (string) $asset['browser_download_url'];
					break;
				}
			}
		}

		if ( '' === $package && '' !== $tag ) {
			$package = 'https://github.com/' . $repo . '/archive/refs/tags/' . rawurlencode( $tag ) . '.zip';
		}

		$result['version'] = $version;
		$result['package'] = $package;
		if ( ! empty( $body['html_url'] ) ) {
			$result['url'] = (string) $body['html_url'];
		}
		$result['notes'] = isset( $body['body'] ) ? (string) $body['body'] : '';

		set_site_transient( self::CACHE, $result, HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * @return string
	 */
	private static function token() {
		$settings = ATEP_Plugin::settings();
		return isset( $settings['github_token'] ) ? trim( (string) $settings['github_token'] ) : '';
	}

	/**
	 * @return array<string,string>
	 */
	private static function headers() {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'AT-Excel-Price/' . ATEP_VERSION,
		);
		$token   = self::token();
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		return $headers;
	}
}
