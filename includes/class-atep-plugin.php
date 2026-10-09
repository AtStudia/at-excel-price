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
			'show_search'      => 1,
			'show_sort'        => 1,
			'sticky_header'    => 1,
			'zebra'            => 1,
			'github_repo'      => '',
			'github_token'     => '',
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
		return array_merge( self::defaults(), $saved );
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
			__( 'Оформление', 'at-excel-price' ),
			__( 'Оформление', 'at-excel-price' ),
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
			wp_safe_redirect( admin_url( 'admin.php?page=at-excel-price-style&checked=1' ) );
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
	private function sanitize_settings( $input ) {
		$defaults = self::defaults();
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
			$raw = isset( $input[ $key ] ) ? sanitize_hex_color( wp_unslash( $input[ $key ] ) ) : '';
			$out[ $key ] = $raw ? $raw : $defaults[ $key ];
		}

		$out['row_height'] = $this->clamp_int( isset( $input['row_height'] ) ? $input['row_height'] : 40, 24, 96 );
		$out['font_size']  = $this->clamp_int( isset( $input['font_size'] ) ? $input['font_size'] : 14, 11, 22 );
		$out['tab_radius'] = $this->clamp_int( isset( $input['tab_radius'] ) ? $input['tab_radius'] : 6, 0, 24 );
		$out['per_page']   = $this->clamp_int( isset( $input['per_page'] ) ? $input['per_page'] : 50, 10, 500 );

		foreach ( array( 'show_search', 'show_sort', 'sticky_header', 'zebra' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$repo = isset( $input['github_repo'] ) ? wp_unslash( $input['github_repo'] ) : '';
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

			update_post_meta( $replace_id, ATEP_META_SHEETS, wp_json_encode( $sheets, JSON_UNESCAPED_UNICODE ) );
			update_post_meta( $replace_id, ATEP_META_SOURCE, $name );

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

		update_post_meta( $post_id, ATEP_META_SHEETS, wp_json_encode( $sheets, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, ATEP_META_SOURCE, $name );

		return (int) $post_id;
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

		$raw = get_post_meta( $id, ATEP_META_SHEETS, true );
		$sheets = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $sheets ) || empty( $sheets ) ) {
			return '';
		}

		$this->enqueue_front( true );

		$settings = self::settings();
		$uid      = 'atep-' . $id . '-' . wp_rand( 1000, 9999 );

		ob_start();
		include ATEP_DIR . 'public/views/table.php';
		return (string) ob_get_clean();
	}
}
