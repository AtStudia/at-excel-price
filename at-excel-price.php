<?php
/**
 * Plugin Name: AT Excel Price
 * Plugin URI:  https://github.com/AtStudia/at-excel-price
 * Description: Загрузка прайса Excel и вывод на страницу шорткодом [at_excel_price]: вкладки по листам, поиск, сортировка, пагинация и оформление из админки.
 * Version:     1.5.0
 * Author:      AT
 * Text Domain: at-excel-price
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Update URI:  https://github.com/AtStudia/at-excel-price
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATEP_VERSION', '1.5.0' );

if ( ! defined( 'ATEP_GITHUB_REPO' ) ) {
	define( 'ATEP_GITHUB_REPO', 'AtStudia/at-excel-price' );
}
define( 'ATEP_FILE', __FILE__ );
define( 'ATEP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATEP_URL', plugin_dir_url( __FILE__ ) );
define( 'ATEP_OPTION', 'atep_settings' );
define( 'ATEP_META_SHEETS', '_atep_sheets' );
define( 'ATEP_META_SOURCE', '_atep_source_name' );
define( 'ATEP_META_STYLE', '_atep_style' );

require_once ATEP_DIR . 'includes/class-atep-zip.php';
require_once ATEP_DIR . 'includes/class-atep-xlsx.php';
require_once ATEP_DIR . 'includes/class-atep-updater.php';
require_once ATEP_DIR . 'includes/class-atep-plugin.php';

register_activation_hook( __FILE__, array( 'ATEP_Plugin', 'activate' ) );

ATEP_Plugin::instance();
