<?php
/**
 * Shared style fields (global defaults or per-price).
 *
 * @var array<string, mixed> $settings
 * @var string               $id_prefix Unique HTML id prefix.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fields = array(
	'header_bg'        => __( 'Фон шапки таблицы', 'at-excel-price' ),
	'header_color'     => __( 'Текст шапки', 'at-excel-price' ),
	'row_bg'           => __( 'Фон строки', 'at-excel-price' ),
	'row_color'        => __( 'Текст строки', 'at-excel-price' ),
	'alt_bg'           => __( 'Фон чередующейся строки', 'at-excel-price' ),
	'alt_color'        => __( 'Текст чередующейся строки', 'at-excel-price' ),
	'border_color'     => __( 'Цвет границ', 'at-excel-price' ),
	'tab_bg'           => __( 'Фон вкладки', 'at-excel-price' ),
	'tab_color'        => __( 'Текст вкладки', 'at-excel-price' ),
	'tab_active_bg'    => __( 'Фон активной вкладки', 'at-excel-price' ),
	'tab_active_color' => __( 'Текст активной вкладки', 'at-excel-price' ),
);

if ( empty( $id_prefix ) ) {
	$id_prefix = 'atep';
}
?>
<div class="atep-panel">
	<h2><?php esc_html_e( 'Цвета', 'at-excel-price' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php foreach ( $fields as $key => $label ) : ?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $id_prefix . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td><input type="color" id="<?php echo esc_attr( $id_prefix . '-' . $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" /></td>
			</tr>
		<?php endforeach; ?>
	</table>
</div>

<div class="atep-panel">
	<h2><?php esc_html_e( 'Размеры и поведение', 'at-excel-price' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id_prefix ); ?>-row-height"><?php esc_html_e( 'Высота строк, px', 'at-excel-price' ); ?></label></th>
			<td><input type="number" min="24" max="96" id="<?php echo esc_attr( $id_prefix ); ?>-row-height" name="row_height" value="<?php echo (int) $settings['row_height']; ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id_prefix ); ?>-font-size"><?php esc_html_e( 'Размер шрифта, px', 'at-excel-price' ); ?></label></th>
			<td><input type="number" min="11" max="22" id="<?php echo esc_attr( $id_prefix ); ?>-font-size" name="font_size" value="<?php echo (int) $settings['font_size']; ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id_prefix ); ?>-tab-radius"><?php esc_html_e( 'Скругление вкладок, px', 'at-excel-price' ); ?></label></th>
			<td><input type="number" min="0" max="24" id="<?php echo esc_attr( $id_prefix ); ?>-tab-radius" name="tab_radius" value="<?php echo (int) $settings['tab_radius']; ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id_prefix ); ?>-per-page"><?php esc_html_e( 'Строк на странице', 'at-excel-price' ); ?></label></th>
			<td>
				<input type="number" min="10" max="500" id="<?php echo esc_attr( $id_prefix ); ?>-per-page" name="per_page" value="<?php echo (int) $settings['per_page']; ?>" />
				<p class="description"><?php esc_html_e( 'Первая строка листа считается заголовком и не уходит в пагинацию.', 'at-excel-price' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Опции', 'at-excel-price' ); ?></th>
			<td>
				<label><input type="checkbox" name="zebra" value="1" <?php checked( $settings['zebra'], 1 ); ?> /> <?php esc_html_e( 'Чередовать фон строк', 'at-excel-price' ); ?></label><br />
				<label><input type="checkbox" name="show_search" value="1" <?php checked( $settings['show_search'], 1 ); ?> /> <?php esc_html_e( 'Поиск по строкам', 'at-excel-price' ); ?></label><br />
				<label><input type="checkbox" name="show_sort" value="1" <?php checked( $settings['show_sort'], 1 ); ?> /> <?php esc_html_e( 'Сортировка по клику на столбец', 'at-excel-price' ); ?></label><br />
				<label><input type="checkbox" name="sticky_header" value="1" <?php checked( $settings['sticky_header'], 1 ); ?> /> <?php esc_html_e( 'Закрепить шапку при прокрутке', 'at-excel-price' ); ?></label>
			</td>
		</tr>
	</table>
</div>
