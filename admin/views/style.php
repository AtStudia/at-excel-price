<?php
/**
 * Global appearance defaults + GitHub updates.
 *
 * @var array<string, mixed> $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id_prefix = 'atep';
?>
<div class="wrap atep-wrap">
	<h1><?php esc_html_e( 'Оформление по умолчанию', 'at-excel-price' ); ?></h1>
	<p class="atep-lead"><?php esc_html_e( 'Эти значения копируются в новый прайс при загрузке. Уже созданные таблицы настраиваются отдельно: Прайсы → Настройки.', 'at-excel-price' ); ?></p>

	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Настройки сохранены.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['checked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Проверка обновлений выполнена. Если доступна новая версия — см. блок ниже или страницу «Плагины».', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['updated_plugin'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Плагин обновлён с GitHub.', 'at-excel-price' ); ?></p></div>
	<?php endif; ?>

	<?php
	$style_error = get_transient( 'atep_admin_error_' . get_current_user_id() );
	if ( $style_error ) {
		delete_transient( 'atep_admin_error_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html( (string) $style_error ) . '</p></div>';
	}
	?>

	<form method="post">
		<?php wp_nonce_field( 'atep_save_style' ); ?>
		<input type="hidden" name="atep_action" value="save_style" />

		<?php include ATEP_DIR . 'admin/views/style-fields.php'; ?>

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
			$installed = ATEP_Updater::installed_version();
			$release   = ATEP_Updater::remote();
			?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: version number */
						__( 'Установлено: %s', 'at-excel-price' ),
						$installed
					)
				);
				?>
			</p>
			<?php if ( ! empty( $release['error'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Последняя проверка:', 'at-excel-price' ); ?></strong> <?php echo esc_html( $release['error'] ); ?></p>
			<?php elseif ( ! empty( $release['version'] ) ) : ?>
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
					<?php if ( version_compare( $release['version'], $installed, '>' ) ) : ?>
						— <strong><?php esc_html_e( 'доступно обновление', 'at-excel-price' ); ?></strong>
					<?php else : ?>
						— <?php esc_html_e( 'у вас актуальная версия', 'at-excel-price' ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<p class="atep-style-actions">
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=at-excel-price-style&atep_check_updates=1' ), 'atep_check_updates' ) ); ?>">
					<?php esc_html_e( 'Проверить обновления', 'at-excel-price' ); ?>
				</a>
				<?php if ( ! empty( $release['version'] ) && version_compare( $release['version'], $installed, '>' ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=at-excel-price-style&atep_install_update=1' ), 'atep_install_update' ) ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: version */
								__( 'Установить %s с GitHub', 'at-excel-price' ),
								$release['version']
							)
						);
						?>
					</a>
				<?php endif; ?>
			</p>
			<p class="description"><?php esc_html_e( 'Если в списке плагинов кнопку не видно (Easy Updates Manager и др.), обновляйте отсюда.', 'at-excel-price' ); ?></p>
		</div>

		<?php submit_button( __( 'Сохранить', 'at-excel-price' ) ); ?>
	</form>
</div>
