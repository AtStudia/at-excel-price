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

$css_vars = sprintf(
	'--atep-header-bg:%1$s;--atep-header-color:%2$s;--atep-row-bg:%3$s;--atep-row-color:%4$s;--atep-alt-bg:%5$s;--atep-alt-color:%6$s;--atep-border:%7$s;--atep-row-h:%8$dpx;--atep-font:%9$dpx;--atep-tab-bg:%10$s;--atep-tab-color:%11$s;--atep-tab-active-bg:%12$s;--atep-tab-active-color:%13$s;--atep-tab-radius:%14$dpx;',
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
	(int) $settings['tab_radius']
);

$col_mode = isset( $settings['column_width_mode'] ) ? (string) $settings['column_width_mode'] : 'auto';
if ( ! in_array( $col_mode, array( 'auto', 'equal', 'manual' ), true ) ) {
	$col_mode = 'auto';
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
?>
<div
	id="<?php echo esc_attr( $uid ); ?>"
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	style="<?php echo esc_attr( $css_vars ); ?>"
	data-per-page="<?php echo (int) $settings['per_page']; ?>"
	data-col-mode="<?php echo esc_attr( $col_mode ); ?>"
>
	<?php if ( count( $sheets ) > 1 ) : ?>
		<div class="atep-tabs" role="tablist">
			<?php foreach ( $sheets as $index => $sheet ) : ?>
				<button
					type="button"
					class="atep-tab<?php echo 0 === $index ? ' is-active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
					data-atep-tab="<?php echo (int) $index; ?>"
				><?php echo esc_html( $sheet['name'] ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php elseif ( ! empty( $sheets[0]['name'] ) ) : ?>
		<div class="atep-single-title"><?php echo esc_html( $sheets[0]['name'] ); ?></div>
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
