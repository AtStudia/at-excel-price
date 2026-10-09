<?php
/**
 * Prices list, upload, preview, per-price style.
 *
 * @var string               $error
 * @var WP_Post[]            $prices
 * @var int                  $preview_id
 * @var int                  $style_id
 * @var WP_Post|null         $style_post
 * @var array<string, mixed> $price_style
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap atep-wrap">
	<h1><?php esc_html_e( 'AT Excel Price', 'at-excel-price' ); ?></h1>
	<p class="atep-lead"><?php esc_html_e( 'Загрузите книгу .xlsx. Каждый лист станет вкладкой на странице. Вставка: шорткод из таблицы ниже. Файл и оформление прайса можно менять без смены шорткода.', 'at-excel-price' ); ?></p>

	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Файл загружен. Ниже предпросмотр — так таблица выглядит на сайте.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['replaced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Файл обновлён. Шорткод на страницах менять не нужно.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['style_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Оформление этого прайса сохранено.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['style_reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Оформление прайса сброшено к общим настройкам по умолчанию.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Прайс удалён.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $style_post ) : ?>
		<div class="atep-panel atep-price-style">
			<h2>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: price title */
						__( 'Оформление: %s', 'at-excel-price' ),
						get_the_title( $style_post )
					)
				);
				?>
			</h2>
			<p class="description"><?php esc_html_e( 'Настройки только для этого прайса. Шорткод не меняется.', 'at-excel-price' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'atep_save_price_style' ); ?>
				<input type="hidden" name="atep_action" value="save_price_style" />
				<input type="hidden" name="price_id" value="<?php echo (int) $style_id; ?>" />
				<?php
				$settings  = $price_style;
				$id_prefix = 'atep-price-' . (int) $style_id;
				include ATEP_DIR . 'admin/views/style-fields.php';
				?>
				<p class="submit atep-style-actions">
					<?php submit_button( __( 'Сохранить оформление', 'at-excel-price' ), 'primary', 'submit', false ); ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=at-excel-price&preview=' . (int) $style_id ) ); ?>"><?php esc_html_e( 'Закрыть', 'at-excel-price' ); ?></a>
				</p>
			</form>
			<form method="post" class="atep-reset-style" onsubmit="return confirm('<?php echo esc_js( __( 'Сбросить оформление этого прайса к общим настройкам?', 'at-excel-price' ) ); ?>');">
				<?php wp_nonce_field( 'atep_reset_price_style' ); ?>
				<input type="hidden" name="atep_action" value="reset_price_style" />
				<input type="hidden" name="price_id" value="<?php echo (int) $style_id; ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Сбросить к общим', 'at-excel-price' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<div class="atep-panel">
		<h2><?php esc_html_e( 'Новый прайс', 'at-excel-price' ); ?></h2>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'atep_upload_price' ); ?>
			<input type="hidden" name="atep_action" value="upload_price" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="atep-title"><?php esc_html_e( 'Название', 'at-excel-price' ); ?></label></th>
					<td><input type="text" class="regular-text" id="atep-title" name="price_title" placeholder="<?php esc_attr_e( 'Например: Прайс 2026', 'at-excel-price' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="atep-file"><?php esc_html_e( 'Файл Excel', 'at-excel-price' ); ?></label></th>
					<td>
						<input type="file" id="atep-file" name="price_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required />
						<p class="description"><?php esc_html_e( 'Только .xlsx. До 5000 строк и 40 столбцов на лист. Старый .xls сохраните как .xlsx.', 'at-excel-price' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Загрузить', 'at-excel-price' ) ); ?>
		</form>
	</div>

	<div class="atep-panel">
		<h2><?php esc_html_e( 'Загруженные прайсы', 'at-excel-price' ); ?></h2>
		<?php if ( empty( $prices ) ) : ?>
			<p><?php esc_html_e( 'Пока нет файлов.', 'at-excel-price' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Название', 'at-excel-price' ); ?></th>
						<th><?php esc_html_e( 'Файл', 'at-excel-price' ); ?></th>
						<th><?php esc_html_e( 'Листы', 'at-excel-price' ); ?></th>
						<th><?php esc_html_e( 'Шорткод', 'at-excel-price' ); ?></th>
						<th><?php esc_html_e( 'Действия', 'at-excel-price' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $prices as $price ) : ?>
						<?php
						$source = (string) get_post_meta( $price->ID, ATEP_META_SOURCE, true );
						$loaded = ATEP_Plugin::load_sheets_meta( $price->ID );
						$data   = $loaded['sheets'];
						$names  = array();
						if ( is_array( $data ) ) {
							foreach ( $data as $sheet ) {
								if ( ! empty( $sheet['name'] ) ) {
									$names[] = $sheet['name'];
								}
							}
						}
						$code = '[at_excel_price id="' . (int) $price->ID . '"]';
						?>
						<tr>
							<td><strong><?php echo esc_html( get_the_title( $price ) ); ?></strong></td>
							<td><?php echo esc_html( $source ); ?></td>
							<td><?php echo esc_html( implode( ', ', $names ) ); ?></td>
							<td><code class="atep-code"><?php echo esc_html( $code ); ?></code></td>
							<td class="atep-actions">
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=at-excel-price&style_id=' . (int) $price->ID . '&preview=' . (int) $price->ID ) ); ?>"><?php esc_html_e( 'Настройки', 'at-excel-price' ); ?></a>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=at-excel-price&preview=' . (int) $price->ID ) ); ?>"><?php esc_html_e( 'Предпросмотр', 'at-excel-price' ); ?></a>
								<form method="post" class="atep-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Удалить этот прайс?', 'at-excel-price' ) ); ?>');">
									<?php wp_nonce_field( 'atep_delete_price' ); ?>
									<input type="hidden" name="atep_action" value="delete_price" />
									<input type="hidden" name="price_id" value="<?php echo (int) $price->ID; ?>" />
									<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Удалить', 'at-excel-price' ); ?></button>
								</form>
							</td>
						</tr>
						<tr class="atep-replace-row">
							<td colspan="5">
								<form method="post" enctype="multipart/form-data" class="atep-replace">
									<?php wp_nonce_field( 'atep_replace_price' ); ?>
									<input type="hidden" name="atep_action" value="replace_price" />
									<input type="hidden" name="price_id" value="<?php echo (int) $price->ID; ?>" />
									<label class="screen-reader-text" for="atep-replace-<?php echo (int) $price->ID; ?>"><?php esc_html_e( 'Новый файл Excel', 'at-excel-price' ); ?></label>
									<input type="file" id="atep-replace-<?php echo (int) $price->ID; ?>" name="price_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required />
									<button type="submit" class="button"><?php esc_html_e( 'Обновить файл', 'at-excel-price' ); ?></button>
									<span class="description"><?php esc_html_e( 'Заменит данные прайса. Шорткод и оформление останутся.', 'at-excel-price' ); ?></span>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php if ( $preview_id ) : ?>
		<div class="atep-panel atep-preview">
			<h2><?php esc_html_e( 'Предпросмотр', 'at-excel-price' ); ?></h2>
			<?php
			$diag     = ATEP_Plugin::load_sheets_meta( $preview_id );
			$ps       = ATEP_Plugin::price_settings( $preview_id );
			$row_sum  = 0;
			$tab_names = array();
			if ( ! empty( $diag['sheets'] ) && is_array( $diag['sheets'] ) ) {
				$display = ATEP_Plugin::prepare_display_sheets( $diag['sheets'], $ps );
				foreach ( $display as $sh ) {
					$tab_names[] = isset( $sh['name'] ) ? (string) $sh['name'] : '';
					if ( ! empty( $sh['rows'] ) && is_array( $sh['rows'] ) ) {
						$row_sum += max( 0, count( $sh['rows'] ) - 1 );
					}
				}
			}
			?>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: meta bytes, 2: tabs, 3: data rows, 4: tabs mode */
						__( 'Диагностика: meta %1$d байт, вкладок %2$d, строк данных %3$d, режим «%4$s».', 'at-excel-price' ),
						(int) $diag['raw_len'],
						count( $tab_names ),
						(int) $row_sum,
						isset( $ps['tabs_mode'] ) ? (string) $ps['tabs_mode'] : 'sheets'
					)
				);
				if ( ! empty( $diag['error'] ) ) {
					echo ' ';
					echo esc_html(
						sprintf(
							/* translators: %s: error */
							__( 'Ошибка данных: %s', 'at-excel-price' ),
							$diag['error']
						)
					);
				}
				?>
			</p>
			<?php if ( ! empty( $tab_names ) ) : ?>
				<p class="description"><?php echo esc_html( implode( ' | ', array_slice( $tab_names, 0, 12 ) ) ); ?></p>
			<?php endif; ?>
			<?php echo do_shortcode( '[at_excel_price id="' . (int) $preview_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>
</div>
