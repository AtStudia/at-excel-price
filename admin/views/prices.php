<?php
/**
 * Prices list, upload, preview.
 *
 * @var string    $error
 * @var WP_Post[] $prices
 * @var int       $preview_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap atep-wrap">
	<h1><?php esc_html_e( 'AT Excel Price', 'at-excel-price' ); ?></h1>
	<p class="atep-lead"><?php esc_html_e( 'Загрузите книгу .xlsx. Каждый лист станет вкладкой на странице. Вставка: шорткод из таблицы ниже. Файл можно обновить у существующего прайса — шорткод не изменится.', 'at-excel-price' ); ?></p>

	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Файл загружен. Ниже предпросмотр — так таблица выглядит на сайте.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['replaced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Файл обновлён. Шорткод на страницах менять не нужно.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Прайс удалён.', 'at-excel-price' ); ?></p></div>
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
						$raw    = get_post_meta( $price->ID, ATEP_META_SHEETS, true );
						$data   = is_string( $raw ) ? json_decode( $raw, true ) : array();
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
									<span class="description"><?php esc_html_e( 'Заменит данные прайса. Шорткод останется тем же.', 'at-excel-price' ); ?></span>
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
			<?php echo do_shortcode( '[at_excel_price id="' . (int) $preview_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>
</div>
