<?php
/**
 * Front table markup.
 *
 * @var int                  $id
 * @var array<int, array>    $sheets
 * @var array<string, mixed> $settings
 * @var string               $uid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = ATEP_Plugin::normalize_settings( $settings );

$tab_w   = (int) $settings['tab_width'];
$tab_h   = (int) $settings['tab_height'];
$tab_w_m = (int) $settings['tab_width_mobile'];
$tab_h_m = (int) $settings['tab_height_mobile'];
if ( $tab_w_m < 1 ) {
	$tab_w_m = $tab_w;
}
if ( $tab_h_m < 1 ) {
	$tab_h_m = $tab_h;
}

$css_vars = sprintf(
	'--atep-header-bg:%1$s;--atep-header-color:%2$s;--atep-row-bg:%3$s;--atep-row-color:%4$s;--atep-alt-bg:%5$s;--atep-alt-color:%6$s;--atep-border:%7$s;--atep-row-h:%8$dpx;--atep-font:%9$dpx;--atep-tab-bg:%10$s;--atep-tab-color:%11$s;--atep-tab-active-bg:%12$s;--atep-tab-active-color:%13$s;--atep-tab-radius:%14$dpx;--atep-tab-w:%15$s;--atep-tab-h:%16$s;--atep-tab-pad-y:%17$s;--atep-tab-lh:%18$s;--atep-tab-w-m:%19$s;--atep-tab-h-m:%20$s;--atep-tab-pad-y-m:%21$s;--atep-tab-lh-m:%22$s;--atep-title-color:%23$s;--atep-title-font:%24$dpx;',
	esc_attr( $settings['header_bg'] ),
	esc_attr( $settings['header_color'] ),
	esc_attr( $settings['row_bg'] ),
	esc_attr( $settings['row_color'] ),
	esc_attr( $settings['alt_bg'] ),
	esc_attr( $settings['alt_color'] ),
	esc_attr( $settings['border_color'] ),
	(int) $settings['row_height'],
	(int) $settings['font_size'],
	esc_attr( $settings['tab_bg'] ),
	esc_attr( $settings['tab_color'] ),
	esc_attr( $settings['tab_active_bg'] ),
	esc_attr( $settings['tab_active_color'] ),
	(int) $settings['tab_radius'],
	$tab_w > 0 ? $tab_w . 'px' : 'auto',
	$tab_h > 0 ? $tab_h . 'px' : 'auto',
	$tab_h > 0 ? '0px' : '8px',
	$tab_h > 0 ? max( 1, $tab_h - 2 ) . 'px' : '1.2',
	$tab_w_m > 0 ? $tab_w_m . 'px' : 'auto',
	$tab_h_m > 0 ? $tab_h_m . 'px' : 'auto',
	$tab_h_m > 0 ? '0px' : '8px',
	$tab_h_m > 0 ? max( 1, $tab_h_m - 2 ) . 'px' : '1.2',
	esc_attr( $settings['title_color'] ),
	(int) $settings['title_font_size']
);

$active_title = ! empty( $sheets[0]['name'] ) ? (string) $sheets[0]['name'] : '';

$col_mode = isset( $settings['column_width_mode'] ) ? (string) $settings['column_width_mode'] : 'auto';
if ( ! in_array( $col_mode, array( 'auto', 'equal', 'manual' ), true ) ) {
	$col_mode = 'auto';
}
$tabs_ui = isset( $settings['tabs_ui'] ) ? (string) $settings['tabs_ui'] : 'buttons';
if ( ! in_array( $tabs_ui, array( 'buttons', 'select' ), true ) ) {
	$tabs_ui = 'buttons';
}
$manual_widths = ATEP_Plugin::parse_column_widths( isset( $settings['column_widths'] ) ? $settings['column_widths'] : '' );

$classes = array( 'atep', 'atep-cols-' . $col_mode );
if ( ! empty( $settings['zebra'] ) ) {
	$classes[] = 'atep-zebra';
}
if ( ! empty( $settings['sticky_header'] ) ) {
	$classes[] = 'atep-sticky';
}
if ( ! empty( $settings['show_sort'] ) ) {
	$classes[] = 'atep-sortable';
}
if ( 'select' === $tabs_ui ) {
	$classes[] = 'atep-tabs-select';
}
?>
<div
	id="<?php echo esc_attr( $uid ); ?>"
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	style="<?php echo esc_attr( $css_vars ); ?>"
	data-per-page="<?php echo (int) $settings['per_page']; ?>"
	data-col-mode="<?php echo esc_attr( $col_mode ); ?>"
	data-tabs-ui="<?php echo esc_attr( $tabs_ui ); ?>"
>
	<?php if ( count( $sheets ) > 1 ) : ?>
		<?php if ( 'select' === $tabs_ui ) : ?>
			<div class="atep-tabs-select-wrap">
				<label class="atep-tabs-select-label" for="<?php echo esc_attr( $uid ); ?>-tabs-select">
					<span class="screen-reader-text"><?php esc_html_e( 'Выбор вкладки', 'at-excel-price' ); ?></span>
					<select
						id="<?php echo esc_attr( $uid ); ?>-tabs-select"
						class="atep-tabs-select-control"
						data-atep-tabs-select
					>
						<?php foreach ( $sheets as $index => $sheet ) : ?>
							<option value="<?php echo (int) $index; ?>" <?php selected( $index, 0 ); ?>>
								<?php echo esc_html( $sheet['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		<?php else : ?>
			<div class="atep-tabs" role="tablist">
				<?php foreach ( $sheets as $index => $sheet ) : ?>
					<button
						type="button"
						class="atep-tab<?php echo 0 === $index ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
						data-atep-tab="<?php echo (int) $index; ?>"
						title="<?php echo esc_attr( $sheet['name'] ); ?>"
					><?php echo esc_html( $sheet['name'] ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( '' !== $active_title ) : ?>
		<h2 class="atep-sheet-title" data-atep-sheet-title><?php echo esc_html( $active_title ); ?></h2>
	<?php endif; ?>

	<?php foreach ( $sheets as $index => $sheet ) : ?>
		<?php
		$rows    = isset( $sheet['rows'] ) && is_array( $sheet['rows'] ) ? $sheet['rows'] : array();
		$header  = ! empty( $rows ) ? array_shift( $rows ) : array();
		$body_id = $uid . '-body-' . (int) $index;
		$col_n   = ! empty( $header ) ? count( $header ) : 0;
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$col_n = max( $col_n, count( $row ) );
			}
		}
		$sheet_widths = ( 'manual' === $col_mode && $col_n > 0 )
			? ATEP_Plugin::fit_column_widths( $manual_widths, $col_n )
			: array();
		?>
		<section class="atep-sheet<?php echo 0 === $index ? ' is-active' : ''; ?>" data-atep-sheet="<?php echo (int) $index; ?>" <?php echo 0 === $index ? '' : 'hidden'; ?>>
			<?php if ( ! empty( $settings['show_search'] ) ) : ?>
				<div class="atep-toolbar">
					<label class="atep-search">
						<span class="screen-reader-text"><?php esc_html_e( 'Поиск по строкам', 'at-excel-price' ); ?></span>
						<input type="search" placeholder="<?php esc_attr_e( 'Поиск по строкам…', 'at-excel-price' ); ?>" data-atep-search />
					</label>
					<span class="atep-count" data-atep-count></span>
				</div>
			<?php endif; ?>

			<div class="atep-scroll">
				<table class="atep-table">
					<?php if ( ! empty( $sheet_widths ) ) : ?>
						<colgroup>
							<?php foreach ( $sheet_widths as $w ) : ?>
								<col style="width:<?php echo (int) $w; ?>%" />
							<?php endforeach; ?>
						</colgroup>
					<?php endif; ?>
					<?php if ( ! empty( $header ) ) : ?>
						<thead>
							<tr>
								<?php foreach ( $header as $col => $cell ) : ?>
									<th scope="col" <?php echo ! empty( $settings['show_sort'] ) ? 'data-atep-sort="' . (int) $col . '"' : ''; ?>>
										<?php echo esc_html( (string) $cell ); ?>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
					<?php endif; ?>
					<tbody id="<?php echo esc_attr( $body_id ); ?>">
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<?php
								$width = max( count( $header ), count( $row ) );
								for ( $col = 0; $col < $width; $col++ ) :
									$value = isset( $row[ $col ] ) ? (string) $row[ $col ] : '';
									?>
									<td><?php echo esc_html( $value ); ?></td>
								<?php endfor; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<nav class="atep-pager" data-atep-pager hidden>
				<button type="button" data-atep-page="prev"><?php esc_html_e( 'Назад', 'at-excel-price' ); ?></button>
				<span data-atep-page-label></span>
				<button type="button" data-atep-page="next"><?php esc_html_e( 'Далее', 'at-excel-price' ); ?></button>
			</nav>
		</section>
	<?php endforeach; ?>
</div>
