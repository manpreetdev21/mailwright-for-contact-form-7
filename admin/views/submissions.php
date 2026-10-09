<?php
/**
 * Submissions screen: the log of everything visitors have sent.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

require_once MWRIGHT_DIR . 'admin/class-submissions-list-table.php';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which entry to show.
$mwright_entry_id = isset( $_GET['entry'] ) && ! isset( $_GET['entry_action'] ) ? absint( $_GET['entry'] ) : 0;

$mwright_entry = $mwright_entry_id ? MWRIGHT_Submissions::get( $mwright_entry_id ) : null;
?>
<div class="wrap mwright">

	<?php if ( $mwright_entry ) : ?>

		<?php
		MWRIGHT_Admin::header(
			__( 'Submission', 'mailwright-for-contact-form-7' ),
			sprintf(
				'<a class="mwright-btn" href="%s">%s</a>',
				esc_url( MWRIGHT_Plugin::url( 'submissions', array( 'form' => $mwright_entry['form_id'] ) ) ),
				esc_html__( 'Back to submissions', 'mailwright-for-contact-form-7' )
			)
		);

		MWRIGHT_Admin::flash();

		$mwright_stamp = strtotime( $mwright_entry['submitted_at'] );
		?>

		<div class="mwright-card mwright-card--flush">
			<table class="mwright-entry">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Form', 'mailwright-for-contact-form-7' ); ?></th>
						<td><?php echo esc_html( $mwright_entry['form_title'] ? $mwright_entry['form_title'] : sprintf( '#%d', $mwright_entry['form_id'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Submitted', 'mailwright-for-contact-form-7' ); ?></th>
						<td><?php echo esc_html( $mwright_stamp ? wp_date( 'Y-m-d H:i:s', $mwright_stamp ) : $mwright_entry['submitted_at'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email', 'mailwright-for-contact-form-7' ); ?></th>
						<td>
							<span class="mwright-badge mwright-badge--<?php echo 'sent' === $mwright_entry['status'] ? 'success' : 'danger'; ?>">
								<?php
								echo 'sent' === $mwright_entry['status']
									? esc_html__( 'Sent', 'mailwright-for-contact-form-7' )
									: esc_html__( 'Not sent', 'mailwright-for-contact-form-7' );
								?>
							</span>
						</td>
					</tr>
					<?php if ( $mwright_entry['remote_ip'] ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'IP address', 'mailwright-for-contact-form-7' ); ?></th>
							<td><?php echo esc_html( $mwright_entry['remote_ip'] ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="mwright-card mwright-card--flush">
			<div class="mwright-card__head"><h2><?php esc_html_e( 'Submitted Data', 'mailwright-for-contact-form-7' ); ?></h2></div>

			<?php require MWRIGHT_DIR . 'admin/views/partial-entry-data.php'; ?>
		</div>

	<?php else : ?>

		<?php
		$mwright_table = new MWRIGHT_Submissions_List_Table();
		$mwright_table->prepare_items();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters, carried into the export link.
		$mwright_export_args = array_filter(
			array(
				'action' => 'mwright_export_entries',
				'form'   => $mwright_table->current_form(),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, carried into the export link.
				'status' => isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, carried into the export link.
				's'      => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			),
			static fn( $value ) => '' !== $value && 0 !== $value
		);

		MWRIGHT_Admin::header(
			__( 'Form Submissions', 'mailwright-for-contact-form-7' ),
			$mwright_table->has_items()
				? sprintf(
					'<a class="mwright-btn" href="%s"><span class="dashicons dashicons-media-spreadsheet"></span>%s</a>',
					esc_url( wp_nonce_url( add_query_arg( $mwright_export_args, admin_url( 'admin-post.php' ) ), 'mwright_export_entries' ) ),
					esc_html__( 'Export CSV', 'mailwright-for-contact-form-7' )
				)
				: ''
		);

		MWRIGHT_Admin::flash();
		?>

		<div class="mwright-card mwright-card--flush mwright-list">
			<form method="get">
				<input type="hidden" name="page" value="mwright-submissions" />
				<input type="hidden" name="form" value="<?php echo esc_attr( (string) $mwright_table->current_form() ); ?>" />
				<?php
				$mwright_table->views();
				$mwright_table->search_box( __( 'Search submissions', 'mailwright-for-contact-form-7' ), 'mwright-entry-search' );
				?>
			</form>

			<form method="post">
				<input type="hidden" name="page" value="mwright-submissions" />
				<input type="hidden" name="form" value="<?php echo esc_attr( (string) $mwright_table->current_form() ); ?>" />
				<div class="mwright-list__scroll">
					<?php $mwright_table->display(); ?>
				</div>
			</form>
		</div>

		<p class="mwright-muted">
			<?php esc_html_e( 'Every submission that passes Contact Form 7\'s validation and spam checks is stored here, whether or not the email went out.', 'mailwright-for-contact-form-7' ); ?>
		</p>

	<?php endif; ?>

</div>
