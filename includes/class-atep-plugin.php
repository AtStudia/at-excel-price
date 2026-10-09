<?php
/**
 * Main plugin: CPT, admin, shortcode, assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATEP_Plugin {

	/** @var self|null */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
		add_shortcode( 'at_excel_price', array( $this, 'shortcode' ) );
		ATEP_Updater::init();
	}

	public static function activate() {
		if ( false === get_option( ATEP_OPTION ) ) {
			add_option( ATEP_OPTION, self::defaults() );
		}
		self::instance()->register_cpt();
		flush_rewrite_rules();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'header_bg'        => '#1f4b3a',
			'header_color'     => '#f4f7f2',
			'row_bg'           => '#ffffff',
			'row_color'        => '#1c241c',
			'alt_bg'           => '#eef3ea',
			'alt_color'        => '#1c241c',
			'border_color'     => '#c9d4c4',
			'row_height'       => 40,
			'font_size'        => 14,
			'tab_bg'           => '#e7eee4',
			'tab_color'        => '#243028',
			'tab_active_bg'    => '#1f4b3a',
			'tab_active_color' => '#f7faf5',
			'tab_radius'       => 6,
			'per_page'         => 50,
			'show_search'        => 1,
			'show_sort'          => 1,
			'sticky_header'      => 1,
			'zebra'              => 1,
			'column_width_mode'  => 'auto',
			'column_widths'      => '',
			'tabs_mode'          => 'sheets',
			'tabs_ui'            => 'buttons',
			'category_column'    => 'Категория',
			'github_repo'        => '',
			'github_token'       => '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function settings() {
		$saved = get_option( ATEP_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return self::normalize_settings( array_merge( self::defaults(), $saved ) );
	}

	/**
	 * Color option keys.
	 *
	 * @return string[]
	 */
	public static function color_keys() {
		return array(
			'header_bg',
			'header_color',
			'row_bg',
			'row_color',
			'alt_bg',
			'alt_color',
			'border_color',
			'tab_bg',
			'tab_color',
			'tab_active_bg',
			'tab_active_color',
		);
	}

	/**
	 * Replace empty/invalid values with defaults (empty colors make the table invisible).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public static function normalize_settings( $settings ) {
		$defaults = self::defaults();
		if ( ! is_array( $settings ) ) {
			return $defaults;
		}
		$out = array_merge( $defaults, $settings );
		foreach ( self::color_keys() as $key ) {
			$raw = isset( $out[ $key ] ) ? sanitize_hex_color( (string) $out[ $key ] ) : '';
			$out[ $key ] = $raw ? $raw : $defaults[ $key ];
		}
		foreach ( array( 'row_height', 'font_size', 'tab_radius', 'per_page' ) as $key ) {
			if ( empty( $out[ $key ] ) || (int) $out[ $key ] < 1 ) {
				$out[ $key ] = $defaults[ $key ];
			}
		}
		if ( empty( $out['column_width_mode'] ) || ! in_array( (string) $out['column_width_mode'], array( 'auto', 'equal', 'manual' ), true ) ) {
			$out['column_width_mode'] = 'auto';
		}
		if ( empty( $out['tabs_mode'] ) || ! in_array( (string) $out['tabs_mode'], array( 'sheets', 'category' ), true ) ) {
			$out['tabs_mode'] = 'sheets';
		}
		if ( empty( $out['tabs_ui'] ) || ! in_array( (string) $out['tabs_ui'], array( 'buttons', 'select' ), true ) ) {
			$out['tabs_ui'] = 'buttons';
		}
		if ( empty( $out['category_column'] ) ) {
			$out['category_column'] = 'Категория';
		}
		return $out;
	}

	/**
	 * Style keys shared by global defaults and per-price settings.
	 *
	 * @return string[]
	 */
	public static function style_keys() {
		return array(
			'header_bg',
			'header_color',
			'row_bg',
			'row_color',
			'alt_bg',
			'alt_color',
			'border_color',
			'row_height',
			'font_size',
			'tab_bg',
			'tab_color',
			'tab_active_bg',
			'tab_active_color',
			'tab_radius',
			'per_page',
			'show_search',
			'show_sort',
			'sticky_header',
			'zebra',
			'column_width_mode',
			'column_widths',
			'tabs_mode',
			'tabs_ui',
			'category_column',
		);
	}

	/**
	 * Parse "20,60,20" into positive percentages.
	 *
	 * @param mixed $raw Raw value.
	 * @return int[]
	 */
	public static function parse_column_widths( $raw ) {
		$parts = preg_split( '/[,\s;]+/', (string) $raw );
		$out   = array();
		if ( ! is_array( $parts ) ) {
			return $out;
		}
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part || ! is_numeric( $part ) ) {
				continue;
			}
			$n = (int) round( (float) $part );
			if ( $n < 1 ) {
				continue;
			}
			if ( $n > 100 ) {
				$n = 100;
			}
			$out[] = $n;
			if ( count( $out ) >= 40 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Fit width list to column count (sum ≈ 100).
	 *
	 * @param int[] $widths Percents.
	 * @param int   $count  Column count.
	 * @return int[]
	 */
	public static function fit_column_widths( $widths, $count ) {
		$count = max( 0, (int) $count );
		if ( $count < 1 ) {
			return array();
		}

		$widths = array_values( array_map( 'intval', (array) $widths ) );
		if ( empty( $widths ) ) {
			$each = (int) floor( 100 / $count );
			$out  = array_fill( 0, $count, max( 1, $each ) );
			$out[ $count - 1 ] += 100 - array_sum( $out );
			return $out;
		}

		if ( count( $widths ) > $count ) {
			$widths = array_slice( $widths, 0, $count );
		}

		while ( count( $widths ) < $count ) {
			$widths[] = 1;
		}

		$sum = array_sum( $widths );
		if ( $sum < 1 ) {
			return self::fit_column_widths( array(), $count );
		}

		$scaled = array();
		$used   = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			if ( $i === $count - 1 ) {
				$scaled[] = max( 1, 100 - $used );
				break;
			}
			$n        = (int) max( 1, round( ( $widths[ $i ] / $sum ) * 100 ) );
			$scaled[] = $n;
			$used    += $n;
		}

		return $scaled;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function style_defaults() {
		return array_intersect_key( self::defaults(), array_flip( self::style_keys() ) );
	}

	/**
	 * Global style defaults used for new prices.
	 *
	 * @return array<string, mixed>
	 */
	public static function global_style() {
		return array_intersect_key( self::settings(), array_flip( self::style_keys() ) );
	}

	/**
	 * Effective style for one price table.
	 *
	 * @param int $post_id Price ID.
	 * @return array<string, mixed>
	 */
	public static function price_settings( $post_id ) {
		$saved = self::read_price_style( $post_id );
		if ( empty( $saved ) ) {
			return self::global_style();
		}
		$merged = array_merge( self::style_defaults(), array_intersect_key( $saved, self::style_defaults() ) );
		return self::normalize_settings( array_merge( self::defaults(), $merged ) );
	}

	/**
	 * Decode stored sheets JSON from post meta.
	 *
	 * @param int $post_id Price ID.
	 * @return array{sheets:array,error:string,raw_len:int}
	 */
	public static function load_sheets_meta( $post_id ) {
		$raw = get_post_meta( (int) $post_id, ATEP_META_SHEETS, true );
		$out = array(
			'sheets'  => array(),
			'error'   => '',
			'raw_len' => is_string( $raw ) ? strlen( $raw ) : ( is_array( $raw ) ? count( $raw ) : 0 ),
		);
		if ( is_array( $raw ) ) {
			$out['sheets'] = $raw;
			return $out;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			$out['error'] = 'empty';
			return $out;
		}

		$raw  = wp_unslash( $raw );
		$json = '';

		// New safe format: base64, immune to WP slash/quote corruption.
		if ( 0 === strpos( $raw, 'atep64:' ) ) {
			$decoded = base64_decode( substr( $raw, 7 ), true );
			if ( false === $decoded || '' === $decoded ) {
				$out['error'] = 'base64';
				return $out;
			}
			$json = $decoded;
		} else {
			$json = $raw;
		}

		$data = self::decode_sheets_json( $json );
		if ( ! is_array( $data ) ) {
			$out['error'] = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'json';
			return $out;
		}
		$out['sheets'] = $data;
		return $out;
	}

	/**
	 * Try several slash/encoding variants for legacy broken JSON meta.
	 *
	 * @param string $json JSON string.
	 * @return array|null
	 */
	private static function decode_sheets_json( $json ) {
		$candidates = array(
			$json,
			stripslashes( $json ),
			wp_unslash( $json ),
			stripslashes( stripslashes( $json ) ),
		);
		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) || '' === $candidate ) {
				continue;
			}
			$data = json_decode( $candidate, true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		return null;
	}

	/**
	 * @param int $post_id Price ID.
	 * @return array<string, mixed>
	 */
	private static function read_price_style( $post_id ) {
		$raw = get_post_meta( (int) $post_id, ATEP_META_STYLE, true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return array();
	}

	/**
	 * @param int                  $post_id Price ID.
	 * @param array<string, mixed> $style   Style values.
	 */
	private function write_price_style( $post_id, $style ) {
		update_post_meta(
			(int) $post_id,
			ATEP_META_STYLE,
			wp_json_encode( $style, JSON_UNESCAPED_UNICODE )
		);
	}

	public function register_cpt() {
		register_post_type(
			'atep_price',
			array(
				'labels'              => array(
					'name'          => __( 'Прайсы Excel', 'at-excel-price' ),
					'singular_name' => __( 'Прайс Excel', 'at-excel-price' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'exclude_from_search' => true,
			)
		);
	}

	public function admin_menu() {
		add_menu_page(
			__( 'AT Excel Price', 'at-excel-price' ),
			__( 'AT Excel Price', 'at-excel-price' ),
			'manage_options',
			'at-excel-price',
			array( $this, 'render_prices_page' ),
			'dashicons-media-spreadsheet',
			58
		);

		add_submenu_page(
			'at-excel-price',
			__( 'Прайсы', 'at-excel-price' ),
			__( 'Прайсы', 'at-excel-price' ),
			'manage_options',
			'at-excel-price',
			array( $this, 'render_prices_page' )
		);

		add_submenu_page(
			'at-excel-price',
			__( 'По умолчанию', 'at-excel-price' ),
			__( 'По умолчанию', 'at-excel-price' ),
			'manage_options',
			'at-excel-price-style',
			array( $this, 'render_style_page' )
		);
	}

	public function admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'at-excel-price' ) ) {
			return;
		}

		wp_enqueue_style(
			'atep-admin',
			ATEP_URL . 'assets/css/admin.css',
			array(),
			ATEP_VERSION
		);

		if ( isset( $_GET['preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->enqueue_front( true );
		}
	}

	public function register_front_assets() {
		wp_register_style(
			'atep-front',
			ATEP_URL . 'assets/css/front.css',
			array(),
			ATEP_VERSION
		);
		wp_register_script(
			'atep-front',
			ATEP_URL . 'assets/js/front.js',
			array(),
			ATEP_VERSION,
			true
		);
	}

	/**
	 * @param bool $force Enqueue even if shortcode not yet seen.
	 */
	private function enqueue_front( $force = false ) {
		$this->register_front_assets();
		wp_enqueue_style( 'atep-front' );
		wp_enqueue_script( 'atep-front' );
		unset( $force );
	}

	public function handle_admin_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! empty( $_GET['atep_check_updates'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'atep_check_updates' );
			ATEP_Updater::flush_cache();
			wp_update_plugins();
			ATEP_Updater::force_transient();
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price-style&checked=1' ) );
			exit;
		}

		if ( ! empty( $_GET['atep_install_update'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'atep_install_update' );
			$result = ATEP_Updater::install_from_github();
			if ( is_wp_error( $result ) ) {
				set_transient( 'atep_admin_error_' . get_current_user_id(), $result->get_error_message(), 60 );
				wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price-style&update_failed=1' ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price-style&updated_plugin=1' ) );
			exit;
		}

		if ( empty( $_POST['atep_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['atep_action'] ) );

		if ( 'save_style' === $action ) {
			check_admin_referer( 'atep_save_style' );
			update_option( ATEP_OPTION, $this->sanitize_settings( $_POST ) );
			ATEP_Updater::flush_cache();
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price-style&updated=1' ) );
			exit;
		}

		if ( 'save_price_style' === $action ) {
			check_admin_referer( 'atep_save_price_style' );
			$id   = isset( $_POST['price_id'] ) ? (int) $_POST['price_id'] : 0;
			$post = get_post( $id );
			if ( ! $post || 'atep_price' !== $post->post_type ) {
				wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price' ) );
				exit;
			}
			$this->write_price_style( $id, $this->sanitize_style_input( $_POST ) );
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price&style_saved=1&style_id=' . $id . '&preview=' . $id ) );
			exit;
		}

		if ( 'reset_price_style' === $action ) {
			check_admin_referer( 'atep_reset_price_style' );
			$id   = isset( $_POST['price_id'] ) ? (int) $_POST['price_id'] : 0;
			$post = get_post( $id );
			if ( $post && 'atep_price' === $post->post_type ) {
				$this->write_price_style( $id, self::global_style() );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price&style_reset=1&style_id=' . $id . '&preview=' . $id ) );
			exit;
		}

		if ( 'upload_price' === $action ) {
			check_admin_referer( 'atep_upload_price' );
			$result = $this->import_upload( 0 );
			if ( is_wp_error( $result ) ) {
				set_transient( 'atep_admin_error_' . get_current_user_id(), $result->get_error_message(), 60 );
				wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price' ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price&imported=1&preview=' . (int) $result ) );
			exit;
		}

		if ( 'replace_price' === $action ) {
			check_admin_referer( 'atep_replace_price' );
			$id     = isset( $_POST['price_id'] ) ? (int) $_POST['price_id'] : 0;
			$result = $this->import_upload( $id );
			if ( is_wp_error( $result ) ) {
				set_transient( 'atep_admin_error_' . get_current_user_id(), $result->get_error_message(), 60 );
				wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price&preview=' . $id ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price&replaced=1&preview=' . (int) $result ) );
			exit;
		}

		if ( 'delete_price' === $action ) {
			check_admin_referer( 'atep_delete_price' );
			$id = isset( $_POST['price_id'] ) ? (int) $_POST['price_id'] : 0;
			$post = get_post( $id );
			if ( $post && 'atep_price' === $post->post_type ) {
				wp_delete_post( $id, true );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price&deleted=1' ) );
			exit;
		}
	}

	/**
	 * @param array<string, mixed> $input Raw POST.
	 * @return array<string, mixed>
	 */
	private function sanitize_style_input( $input ) {
		$defaults = self::style_defaults();
		$out      = $defaults;

		$colors = array(
			'header_bg',
			'header_color',
			'row_bg',
			'row_color',
			'alt_bg',
			'alt_color',
			'border_color',
			'tab_bg',
			'tab_color',
			'tab_active_bg',
			'tab_active_color',
		);

		foreach ( $colors as $key ) {
			$raw         = isset( $input[ $key ] ) ? sanitize_hex_color( wp_unslash( $input[ $key ] ) ) : '';
			$out[ $key ] = $raw ? $raw : $defaults[ $key ];
		}

		$out['row_height'] = $this->clamp_int( isset( $input['row_height'] ) ? $input['row_height'] : 40, 24, 96 );
		$out['font_size']  = $this->clamp_int( isset( $input['font_size'] ) ? $input['font_size'] : 14, 11, 22 );
		$out['tab_radius'] = $this->clamp_int( isset( $input['tab_radius'] ) ? $input['tab_radius'] : 6, 0, 24 );
		$out['per_page']   = $this->clamp_int( isset( $input['per_page'] ) ? $input['per_page'] : 50, 10, 500 );

		foreach ( array( 'show_search', 'show_sort', 'sticky_header', 'zebra' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$mode = isset( $input['column_width_mode'] ) ? sanitize_key( wp_unslash( $input['column_width_mode'] ) ) : 'auto';
		if ( ! in_array( $mode, array( 'auto', 'equal', 'manual' ), true ) ) {
			$mode = 'auto';
		}
		$out['column_width_mode'] = $mode;

		$widths = self::parse_column_widths( isset( $input['column_widths'] ) ? wp_unslash( $input['column_widths'] ) : '' );
		$out['column_widths'] = empty( $widths ) ? '' : implode( ',', $widths );

		$tabs_mode = isset( $input['tabs_mode'] ) ? sanitize_key( wp_unslash( $input['tabs_mode'] ) ) : 'sheets';
		if ( ! in_array( $tabs_mode, array( 'sheets', 'category' ), true ) ) {
			$tabs_mode = 'sheets';
		}
		$out['tabs_mode'] = $tabs_mode;

		$tabs_ui = isset( $input['tabs_ui'] ) ? sanitize_key( wp_unslash( $input['tabs_ui'] ) ) : 'buttons';
		if ( ! in_array( $tabs_ui, array( 'buttons', 'select' ), true ) ) {
			$tabs_ui = 'buttons';
		}
		$out['tabs_ui'] = $tabs_ui;

		$cat_col = isset( $input['category_column'] ) ? sanitize_text_field( wp_unslash( $input['category_column'] ) ) : '';
		$out['category_column'] = '' !== $cat_col ? $cat_col : 'Категория';

		return $out;
	}

	/**
	 * Build tabs for front output: by Excel sheets or by category column.
	 *
	 * @param array<int, array{name?:string,rows?:array}> $sheets   Stored sheets.
	 * @param array<string, mixed>                        $settings Price settings.
	 * @return array<int, array{name:string,rows:array}>
	 */
	public static function prepare_display_sheets( $sheets, $settings ) {
		if ( ! is_array( $sheets ) ) {
			return array();
		}

		$mode = isset( $settings['tabs_mode'] ) ? (string) $settings['tabs_mode'] : 'sheets';
		if ( 'category' !== $mode ) {
			return $sheets;
		}

		$column  = isset( $settings['category_column'] ) ? (string) $settings['category_column'] : 'Категория';
		$grouped = self::group_sheets_by_category( $sheets, $column );
		// If the category column is missing, keep the original sheets so the table still shows.
		return ! empty( $grouped ) ? $grouped : $sheets;
	}

	/**
	 * Group all sheet rows by a category column into virtual sheets (tabs).
	 *
	 * @param array<int, array{name?:string,rows?:array}> $sheets        Sheets.
	 * @param string                                      $column_label  Header title to match.
	 * @return array<int, array{name:string,rows:array}>
	 */
	public static function group_sheets_by_category( $sheets, $column_label = 'Категория' ) {
		if ( ! is_array( $sheets ) || empty( $sheets ) ) {
			return array();
		}

		$needle = self::normalize_header_label( $column_label );
		if ( '' === $needle ) {
			$needle = self::normalize_header_label( 'Категория' );
		}

		$groups        = array();
		$display_header = null;

		foreach ( $sheets as $sheet ) {
			$rows = isset( $sheet['rows'] ) && is_array( $sheet['rows'] ) ? $sheet['rows'] : array();
			if ( count( $rows ) < 2 ) {
				continue;
			}

			$header = array_values( $rows[0] );
			$cat_ix = self::find_header_index( $header, $needle );
			if ( $cat_ix < 0 ) {
				continue;
			}

			if ( null === $display_header ) {
				$display_header = self::row_without_index( $header, $cat_ix );
			}

			for ( $i = 1, $n = count( $rows ); $i < $n; $i++ ) {
				$row = isset( $rows[ $i ] ) && is_array( $rows[ $i ] ) ? array_values( $rows[ $i ] ) : array();
				if ( empty( $row ) ) {
					continue;
				}

				$cat = isset( $row[ $cat_ix ] ) ? trim( (string) $row[ $cat_ix ] ) : '';
				if ( '' === $cat ) {
					$cat = __( 'Без категории', 'at-excel-price' );
				}

				if ( ! isset( $groups[ $cat ] ) ) {
					$groups[ $cat ] = array();
				}
				$groups[ $cat ][] = self::row_without_index( $row, $cat_ix );
			}
		}

		if ( empty( $groups ) || null === $display_header ) {
			return array();
		}

		$result = array();
		foreach ( $groups as $name => $body_rows ) {
			$result[] = array(
				'name' => (string) $name,
				'rows' => array_merge( array( $display_header ), $body_rows ),
			);
		}

		return $result;
	}

	/**
	 * @param mixed $label Header label.
	 * @return string
	 */
	private static function normalize_header_label( $label ) {
		$label = (string) $label;
		// BOM, NBSP, zero-width spaces.
		$label = str_replace(
			array( "\xEF\xBB\xBF", "\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D" ),
			array( '', ' ', '', '', '' ),
			$label
		);
		$label = trim( $label );
		$collapsed = preg_replace( '/\s+/', ' ', $label );
		if ( is_string( $collapsed ) ) {
			$label = $collapsed;
		}
		if ( function_exists( 'mb_strtolower' ) ) {
			return (string) mb_strtolower( $label, 'UTF-8' );
		}
		return strtolower( $label );
	}

	/**
	 * @param array<int, mixed> $header Header cells.
	 * @param string            $needle Normalized label.
	 * @return int Index or -1.
	 */
	private static function find_header_index( $header, $needle ) {
		$needle = (string) $needle;
		foreach ( $header as $i => $cell ) {
			if ( self::normalize_header_label( $cell ) === $needle ) {
				return (int) $i;
			}
		}
		foreach ( $header as $i => $cell ) {
			$n = self::normalize_header_label( $cell );
			if ( '' === $n ) {
				continue;
			}
			if ( '' !== $needle && false !== strpos( $n, $needle ) ) {
				return (int) $i;
			}
			if ( false !== strpos( $n, 'категор' ) || false !== strpos( $n, 'categor' ) ) {
				return (int) $i;
			}
		}
		return -1;
	}

	/**
	 * @param array<int, mixed> $row   Cells.
	 * @param int               $index Index to remove.
	 * @return array<int, string>
	 */
	private static function row_without_index( $row, $index ) {
		$out = array();
		foreach ( array_values( $row ) as $i => $cell ) {
			if ( (int) $i === (int) $index ) {
				continue;
			}
			$out[] = is_scalar( $cell ) ? (string) $cell : '';
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $input Raw POST.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( $input ) {
		$out = array_merge( self::defaults(), $this->sanitize_style_input( $input ) );

		$repo               = isset( $input['github_repo'] ) ? wp_unslash( $input['github_repo'] ) : '';
		$out['github_repo'] = ATEP_Updater::sanitize_repo( $repo );

		$prev  = self::settings();
		$token = isset( $input['github_token'] ) ? trim( (string) wp_unslash( $input['github_token'] ) ) : '';
		if ( ! empty( $input['github_token_clear'] ) ) {
			$out['github_token'] = '';
		} elseif ( '' === $token ) {
			$out['github_token'] = isset( $prev['github_token'] ) ? (string) $prev['github_token'] : '';
		} else {
			$out['github_token'] = sanitize_text_field( $token );
		}

		return $out;
	}

	/**
	 * @param mixed $value Value.
	 * @param int   $min   Min.
	 * @param int   $max   Max.
	 * @return int
	 */
	private function clamp_int( $value, $min, $max ) {
		$n = (int) $value;
		if ( $n < $min ) {
			return $min;
		}
		if ( $n > $max ) {
			return $max;
		}
		return $n;
	}

	/**
	 * Import a new price or replace Excel data for an existing one (same post ID / shortcode).
	 *
	 * @param int $replace_id Existing price ID, or 0 to create.
	 * @return int|WP_Error Post ID.
	 */
	private function import_upload( $replace_id = 0 ) {
		if ( empty( $_FILES['price_file']['tmp_name'] ) ) {
			return new WP_Error( 'atep_no_file', __( 'Выберите файл .xlsx.', 'at-excel-price' ) );
		}

		$file = $_FILES['price_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'atep_upload', __( 'Ошибка загрузки файла.', 'at-excel-price' ) );
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( 'xlsx' !== $ext ) {
			return new WP_Error( 'atep_ext', __( 'Поддерживается только формат .xlsx (Excel 2007 и новее). Старый .xls сохраните как .xlsx.', 'at-excel-price' ) );
		}

		$check = wp_check_filetype_and_ext( $file['tmp_name'], $name, array( 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) );
		if ( ! empty( $check['ext'] ) && 'xlsx' !== $check['ext'] ) {
			return new WP_Error( 'atep_mime', __( 'Тип файла не похож на книгу Excel.', 'at-excel-price' ) );
		}

		$sheets = ATEP_XLSX::parse( $file['tmp_name'] );
		if ( is_wp_error( $sheets ) ) {
			return $sheets;
		}

		$sheets = $this->trim_sheets( $sheets );
		if ( empty( $sheets ) ) {
			return new WP_Error( 'atep_empty_data', __( 'В файле нет заполненных строк.', 'at-excel-price' ) );
		}

		$replace_id = (int) $replace_id;

		if ( $replace_id > 0 ) {
			$post = get_post( $replace_id );
			if ( ! $post || 'atep_price' !== $post->post_type ) {
				return new WP_Error( 'atep_missing', __( 'Прайс не найден.', 'at-excel-price' ) );
			}

			$title = isset( $_POST['price_title'] ) ? sanitize_text_field( wp_unslash( $_POST['price_title'] ) ) : '';
			if ( '' !== $title && $title !== $post->post_title ) {
				$updated = wp_update_post(
					array(
						'ID'         => $replace_id,
						'post_title' => $title,
					),
					true
				);
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}

			$encoded = $this->encode_sheets( $sheets );
			if ( is_wp_error( $encoded ) ) {
				return $encoded;
			}
			update_post_meta( $replace_id, ATEP_META_SHEETS, wp_slash( $encoded ) );
			update_post_meta( $replace_id, ATEP_META_SOURCE, $name );
			if ( empty( self::read_price_style( $replace_id ) ) ) {
				$this->write_price_style( $replace_id, self::global_style() );
			}

			return $replace_id;
		}

		$title = isset( $_POST['price_title'] ) ? sanitize_text_field( wp_unslash( $_POST['price_title'] ) ) : '';
		if ( '' === $title ) {
			$title = preg_replace( '/\.xlsx$/i', '', $name );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'atep_price',
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$encoded = $this->encode_sheets( $sheets );
		if ( is_wp_error( $encoded ) ) {
			wp_delete_post( $post_id, true );
			return $encoded;
		}
		update_post_meta( $post_id, ATEP_META_SHEETS, wp_slash( $encoded ) );
		update_post_meta( $post_id, ATEP_META_SOURCE, $name );
		$this->write_price_style( $post_id, self::global_style() );

		return (int) $post_id;
	}

	/**
	 * @param array $sheets Sheets payload.
	 * @return string|WP_Error Storage payload (atep64:...).
	 */
	private function encode_sheets( $sheets ) {
		$flags = JSON_UNESCAPED_UNICODE;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}
		$encoded = wp_json_encode( $sheets, $flags );
		if ( false === $encoded || '' === $encoded || 'null' === $encoded ) {
			return new WP_Error( 'atep_json', __( 'Не удалось сохранить данные файла. Проверьте кодировку Excel (UTF-8).', 'at-excel-price' ) );
		}
		// Base64 avoids WordPress slash corruption around quotes in cell text.
		return 'atep64:' . base64_encode( $encoded );
	}

	/**
	 * @param array<int, array{name:string,rows:array}> $sheets Sheets.
	 * @return array<int, array{name:string,rows:array}>
	 */
	private function trim_sheets( $sheets ) {
		$max_rows = 5000;
		$max_cols = 40;
		$clean    = array();

		foreach ( $sheets as $sheet ) {
			$name = isset( $sheet['name'] ) ? sanitize_text_field( $sheet['name'] ) : '';
			$rows = isset( $sheet['rows'] ) && is_array( $sheet['rows'] ) ? $sheet['rows'] : array();
			if ( count( $rows ) > $max_rows ) {
				$rows = array_slice( $rows, 0, $max_rows );
			}
			$out_rows = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( count( $row ) > $max_cols ) {
					$row = array_slice( $row, 0, $max_cols );
				}
				$cells = array();
				foreach ( $row as $cell ) {
					$cells[] = is_scalar( $cell ) ? (string) $cell : '';
				}
				$out_rows[] = $cells;
			}
			if ( empty( $out_rows ) ) {
				continue;
			}
			$clean[] = array(
				'name' => '' !== $name ? $name : __( 'Лист', 'at-excel-price' ),
				'rows' => $out_rows,
			);
		}

		return $clean;
	}

	public function render_prices_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$error = get_transient( 'atep_admin_error_' . get_current_user_id() );
		if ( $error ) {
			delete_transient( 'atep_admin_error_' . get_current_user_id() );
		}

		$prices = get_posts(
			array(
				'post_type'      => 'atep_price',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$preview_id = isset( $_GET['preview'] ) ? (int) $_GET['preview'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$style_id   = isset( $_GET['style_id'] ) ? (int) $_GET['style_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$style_post = $style_id ? get_post( $style_id ) : null;
		if ( ! $style_post || 'atep_price' !== $style_post->post_type ) {
			$style_id   = 0;
			$style_post = null;
		}
		$price_style = $style_id ? self::price_settings( $style_id ) : array();

		include ATEP_DIR . 'admin/views/prices.php';
	}

	public function render_style_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = self::settings();
		include ATEP_DIR . 'admin/views/style.php';
	}

	/**
	 * @param array<string, string>|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'at_excel_price'
		);

		$id = (int) $atts['id'];
		if ( $id < 1 ) {
			return '';
		}

		$post = get_post( $id );
		if ( ! $post || 'atep_price' !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		$loaded = self::load_sheets_meta( $id );
		$sheets = $loaded['sheets'];
		if ( ! is_array( $sheets ) || empty( $sheets ) ) {
			if ( is_admin() ) {
				return '<div class="notice notice-warning"><p>' . esc_html__( 'Нет данных таблицы для этого прайса. Загрузите Excel-файл заново.', 'at-excel-price' ) . '</p></div>';
			}
			return '';
		}

		$this->enqueue_front( true );

		$settings = self::price_settings( $id );
		$sheets   = self::prepare_display_sheets( $sheets, $settings );
		if ( empty( $sheets ) ) {
			if ( is_admin() ) {
				return '<div class="notice notice-warning"><p>' . esc_html__( 'Данные есть, но вкладки не собрались. Проверьте режим вкладок и имя столбца категории.', 'at-excel-price' ) . '</p></div>';
			}
			return '';
		}
		$uid = 'atep-' . $id . '-' . wp_rand( 1000, 9999 );

		ob_start();
		include ATEP_DIR . 'public/views/table.php';
		$html = (string) ob_get_clean();
		return $html;
	}
}
