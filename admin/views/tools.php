<?php
/**
 * Tools screen: import, export, system status and the debug log.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

$mwright_log      = array_reverse( (array) get_option( 'mwright_log', array() ) );
$mwright_counts   = MWRIGHT_Template_Post_Type::counts();
$mwright_debug_on = (bool) MWRIGHT_Plugin::setting( 'debug' );
?>
<div class="wrap mwright">

	<?php
	MWRIGHT_Admin::header( __( 'Tools', 'mailwright-for-contact-form-7' ) );
	MWRIGHT_Admin::flash();
	?>

	<div class="mwright-columns">

		<div class="mwright-card">
			<div class="mwright-card__head"><h2><?php esc_html_e( 'Demo Templates', 'mailwright-for-contact-form-7' ); ?></h2></div>
			<p class="mwright-muted"><?php esc_html_e( 'Add the ready-made starter templates: contact notification, customer thank-you, quote, booking, support, newsletter, file upload, simple and blank. Any you already have are skipped, so it is safe to run again.', 'mailwright-for-contact-form-7' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mwright_install_demos" />
				<?php wp_nonce_field( 'mwright_install_demos' ); ?>
				<button type="submit" class="mwright-btn mwright-btn--primary">
					<span class="dashicons dashicons-welcome-add-page" aria-hidden="true"></span>
					<?php esc_html_e( 'Install demo templates', 'mailwright-for-contact-form-7' ); ?>
				</button>
			</form>
		</div>

		<div class="mwright-card">
			<div class="mwright-card__head"><h2><?php esc_html_e( 'Export', 'mailwright-for-contact-form-7' ); ?></h2></div>
			<p class="mwright-muted">
				<?php
				printf(
					/* translators: %d: number of templates */
					esc_html( _n( 'Download all %d template as a JSON file.', 'Download all %d templates as a JSON file.', $mwright_counts['total'], 'mailwright-for-contact-form-7' ) ),
					(int) $mwright_counts['total']
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mwright_export" />
				<?php wp_nonce_field( 'mwright_export' ); ?>
				<button type="submit" class="mwright-btn" <?php disabled( 0 === $mwright_counts['total'] ); ?>>
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Export templates', 'mailwright-for-contact-form-7' ); ?>
				</button>
			</form>
		</div>

		<div class="mwright-card">
			<div class="mwright-card__head"><h2><?php esc_html_e( 'Import', 'mailwright-for-contact-form-7' ); ?></h2></div>
			<p class="mwright-muted"><?php esc_html_e( 'Upload a file exported from this plugin. Imported templates arrive as drafts so you can review them before they go live.', 'mailwright-for-contact-form-7' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mwright_import" />
				<?php wp_nonce_field( 'mwright_import' ); ?>
				<p class="mwright-field">
					<label for="mwright-import-file"><?php esc_html_e( 'Template file (.json)', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="file" id="mwright-import-file" name="import_file" accept="application/json,.json" required />
				</p>
				<p class="mwright-field mwright-field--check">
					<label for="mwright-import-branding">
						<input type="checkbox" id="mwright-import-branding" name="import_branding" value="1" />
						<?php esc_html_e( 'Also restore global branding from the file', 'mailwright-for-contact-form-7' ); ?>
					</label>
					<span class="mwright-help"><?php esc_html_e( 'Overwrites your current logo, colours, footer and social links. Off by default.', 'mailwright-for-contact-form-7' ); ?></span>
				</p>
				<button type="submit" class="mwright-btn">
					<span class="dashicons dashicons-upload" aria-hidden="true"></span>
					<?php esc_html_e( 'Import templates', 'mailwright-for-contact-form-7' ); ?>
				</button>
			</form>
		</div>

		<div class="mwright-card">
			<div class="mwright-card__head"><h2><?php esc_html_e( 'System Status', 'mailwright-for-contact-form-7' ); ?></h2></div>
			<table class="mwright-table mwright-table--plain">
				<tbody>
					<?php
					$mwright_rows = array(
						__( 'Plugin version', 'mailwright-for-contact-form-7' )      => MWRIGHT_VERSION,
						__( 'WordPress', 'mailwright-for-contact-form-7' )           => get_bloginfo( 'version' ),
						__( 'Contact Form 7', 'mailwright-for-contact-form-7' )      => defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : __( 'Not active', 'mailwright-for-contact-form-7' ),
						__( 'PHP', 'mailwright-for-contact-form-7' )                 => PHP_VERSION,
						__( 'Templates', 'mailwright-for-contact-form-7' )           => number_format_i18n( $mwright_counts['total'] ),
						__( 'Managed forms', 'mailwright-for-contact-form-7' )       => number_format_i18n( count( MWRIGHT_CF7_Bridge::assignments() ) ),
						__( 'Debug logging', 'mailwright-for-contact-form-7' )       => $mwright_debug_on ? __( 'On', 'mailwright-for-contact-form-7' ) : __( 'Off', 'mailwright-for-contact-form-7' ),
					);

					foreach ( $mwright_rows as $mwright_label => $mwright_value ) :
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $mwright_label ); ?></th>
							<td><?php echo esc_html( $mwright_value ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	</div>

	<div class="mwright-card mwright-card--flush">
		<div class="mwright-card__head">
			<h2><?php esc_html_e( 'Debug Log', 'mailwright-for-contact-form-7' ); ?></h2>
			<?php if ( $mwright_log ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mwright_clear_log" />
					<?php wp_nonce_field( 'mwright_clear_log' ); ?>
					<button type="submit" class="mwright-btn mwright-btn--small"><?php esc_html_e( 'Clear log', 'mailwright-for-contact-form-7' ); ?></button>
				</form>
			<?php endif; ?>
		</div>

		<?php if ( ! $mwright_debug_on && ! $mwright_log ) : ?>
			<div class="mwright-empty mwright-empty--inline">
				<h2><?php esc_html_e( 'Logging is off.', 'mailwright-for-contact-form-7' ); ?></h2>
				<p><?php esc_html_e( 'Turn on debug logging in Settings to record which templates were applied.', 'mailwright-for-contact-form-7' ); ?></p>
				<a class="mwright-btn" href="<?php echo esc_url( MWRIGHT_Plugin::url( 'settings' ) ); ?>">
					<?php esc_html_e( 'Open Settings', 'mailwright-for-contact-form-7' ); ?>
				</a>
			</div>
		<?php elseif ( ! $mwright_log ) : ?>
			<div class="mwright-empty mwright-empty--inline">
				<h2><?php esc_html_e( 'Nothing logged yet.', 'mailwright-for-contact-form-7' ); ?></h2>
				<p><?php esc_html_e( 'Entries appear here after a managed form is submitted.', 'mailwright-for-contact-form-7' ); ?></p>
			</div>
		<?php else : ?>
			<table class="mwright-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'When', 'mailwright-for-contact-form-7' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Event', 'mailwright-for-contact-form-7' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $mwright_log, 0, 50 ) as $mwright_entry ) : ?>
						<tr>
							<td data-label="<?php esc_attr_e( 'When', 'mailwright-for-contact-form-7' ); ?>" class="mwright-nowrap">
								<?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $mwright_entry['time'] ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Event', 'mailwright-for-contact-form-7' ); ?>">
								<?php echo esc_html( $mwright_entry['message'] ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

</div>
