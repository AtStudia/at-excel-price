<?php
/**
 * Update the plugin from GitHub Releases.
 *
 * WordPress 5.8+ calls update_plugins_{hostname} when the plugin header
 * contains "Update URI". Hostname here is github.com.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATEP_Updater {

	const CACHE = 'atep_github_release';

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'offer' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'source' ), 10, 4 );
		add_filter( 'http_request_args', array( __CLASS__, 'auth_headers' ), 10, 2 );
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
	 * @param array|false          $update      Update payload.
	 * @param array<string,string> $plugin_data Headers.
	 * @param string               $plugin_file Plugin file.
	 * @param string[]             $locales     Locales.
	 * @return array|false
	 */
	public static function offer( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );

		if ( plugin_basename( ATEP_FILE ) !== $plugin_file ) {
			return $update;
		}

		$remote = self::remote();
		if ( empty( $remote['version'] ) || empty( $remote['package'] ) ) {
			return $update;
		}

		if ( version_compare( $remote['version'], ATEP_VERSION, '<=' ) ) {
			return $update;
		}

		return (object) array(
			'id'           => 'https://github.com/' . self::repo(),
			'slug'         => 'at-excel-price',
			'plugin'       => plugin_basename( ATEP_FILE ),
			'version'      => $remote['version'],
			'new_version'  => $remote['version'],
			'url'          => $remote['url'],
			'package'      => $remote['package'],
			'requires_php' => '7.4',
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
		if ( ! in_array( $host, array( 'github.com', 'api.github.com', 'codeload.github.com' ), true ) ) {
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
		if ( is_array( $cached ) ) {
			return array_merge( $empty, $cached );
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . $repo . '/releases/latest',
			array(
				'timeout' => 15,
				'headers' => self::headers(),
			)
		);

		$result = $empty;
		$result['url'] = 'https://github.com/' . $repo;

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			set_site_transient( self::CACHE, $result, HOUR_IN_SECONDS );
			return $result;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 404 === $code ) {
			$result['error'] = __( 'Релиз на GitHub не найден. Опубликуйте тег, например v1.2.0.', 'at-excel-price' );
			set_site_transient( self::CACHE, $result, HOUR_IN_SECONDS );
			return $result;
		}

		if ( 200 !== $code || ! is_array( $body ) ) {
			$result['error'] = sprintf(
				/* translators: %d: HTTP status */
				__( 'GitHub ответил кодом %d.', 'at-excel-price' ),
				$code
			);
			set_site_transient( self::CACHE, $result, HOUR_IN_SECONDS );
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

		set_site_transient( self::CACHE, $result, 12 * HOUR_IN_SECONDS );
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
