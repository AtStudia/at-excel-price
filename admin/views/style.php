<?php
/**
 * Appearance settings.
 *
 * @var array<string, mixed> $settings
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
?>
<div class="wrap atep-wrap">
	<h1><?php esc_html_e( 'Оформление таблиц', 'at-excel-price' ); ?></h1>
	<p class="atep-lead"><?php esc_html_e( 'Настройки общие для всех шорткодов на сайте.', 'at-excel-price' ); ?></p>

	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Настройки сохранены.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['checked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Проверка обновлений выполнена. Если на GitHub есть более новая версия, она появится в разделе «Плагины».', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'atep_save_style' ); ?>
		<input type="hidden" name="atep_action" value="save_style" />

		<div class="atep-panel">
			<h2><?php esc_html_e( 'Цвета', 'at-excel-price' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $label ) : ?>
					<tr>
						<th scope="row"><label for="atep-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="color" id="atep-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="atep-panel">
			<h2><?php esc_html_e( 'Размеры и поведение', 'at-excel-price' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="atep-row-height"><?php esc_html_e( 'Высота строк, px', 'at-excel-price' ); ?></label></th>
					<td><input type="number" min="24" max="96" id="atep-row-height" name="row_height" value="<?php echo (int) $settings['row_height']; ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="atep-font-size"><?php esc_html_e( 'Размер шрифта, px', 'at-excel-price' ); ?></label></th>
					<td><input type="number" min="11" max="22" id="atep-font-size" name="font_size" value="<?php echo (int) $settings['font_size']; ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="atep-tab-radius"><?php esc_html_e( 'Скругление вкладок, px', 'at-excel-price' ); ?></label></th>
					<td><input type="number" min="0" max="24" id="atep-tab-radius" name="tab_radius" value="<?php echo (int) $settings['tab_radius']; ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="atep-per-page"><?php esc_html_e( 'Строк на странице', 'at-excel-price' ); ?></label></th>
					<td>
						<input type="number" min="10" max="500" id="atep-per-page" name="per_page" value="<?php echo (int) $settings['per_page']; ?>" />
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

		<div class="atep-panel">
			<h2><?php esc_html_e( 'Обновления с GitHub', 'at-excel-price' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Публичный репозиторий. Новая версия появляется в «Плагины → Обновления», когда на GitHub есть релиз новее установленной. Токен нужен только для приватного репозитория.', 'at-excel-price' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="atep-github-repo"><?php esc_html_e( 'Репозиторий', 'at-excel-price' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="atep-github-repo" name="github_repo" value="<?php echo esc_attr( $settings['github_repo'] ); ?>" placeholder="AtStudia/at-excel-price" />
						<p class="description"><?php esc_html_e( 'Формат owner/repo или ссылка https://github.com/owner/repo. Пустое поле — обновления выключены.', 'at-excel-price' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="atep-github-token"><?php esc_html_e( 'Токен', 'at-excel-price' ); ?></label></th>
					<td>
						<input type="password" class="regular-text" id="atep-github-token" name="github_token" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $settings['github_token'] ) ? esc_attr__( 'Токен сохранён', 'at-excel-price' ) : ''; ?>" />
						<?php if ( ! empty( $settings['github_token'] ) ) : ?>
							<br />
							<label><input type="checkbox" name="github_token_clear" value="1" /> <?php esc_html_e( 'Удалить сохранённый токен', 'at-excel-price' ); ?></label>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php
			$release = get_site_transient( 'atep_github_release' );
			if ( is_array( $release ) && ! empty( $release['error'] ) ) :
				?>
				<p><strong><?php esc_html_e( 'Последняя проверка:', 'at-excel-price' ); ?></strong> <?php echo esc_html( $release['error'] ); ?></p>
			<?php elseif ( is_array( $release ) && ! empty( $release['version'] ) ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: version number */
							__( 'На GitHub последняя версия: %s', 'at-excel-price' ),
							$release['version']
						)
					);
					?>
				</p>
			<?php endif; ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=at-excel-price-style&atep_check_updates=1' ), 'atep_check_updates' ) ); ?>">
					<?php esc_html_e( 'Проверить обновления', 'at-excel-price' ); ?>
				</a>
			</p>
		</div>

		<?php submit_button( __( 'Сохранить', 'at-excel-price' ) ); ?>
	</form>
</div>
