<?php
/**
 * One submission's answers and files, as a label/value table.
 *
 * Shared by the submission screen and the expandable row in the list, so both
 * show exactly the same thing.
 *
 * @package Mailwright_For_Contact_Form_7
 *
 * @var array $mwright_entry Submission, as returned by MWRIGHT_Submissions::get().
 */

defined( 'ABSPATH' ) || exit;

$mwright_entry_answers = MWRIGHT_Submissions::answers( $mwright_entry );
$mwright_entry_files   = MWRIGHT_Submissions::files( $mwright_entry );

if ( ! $mwright_entry_answers && ! $mwright_entry_files ) :
	?>
	<p class="mwright-muted"><?php esc_html_e( 'This submission had no fields.', 'mailwright-for-contact-form-7' ); ?></p>
	<?php
	return;
endif;
?>
<table class="mwright-entry">
	<tbody>
		<?php foreach ( $mwright_entry_answers as $mwright_entry_name => $mwright_entry_value ) : ?>
			<tr>
				<th scope="row">
					<?php echo esc_html( MWRIGHT_CF7_Bridge::friendly_label( $mwright_entry_name ) ); ?>
					<code>[<?php echo esc_html( $mwright_entry_name ); ?>]</code>
				</th>
				<td>
					<?php $mwright_entry_flat = MWRIGHT_Submissions::flatten( $mwright_entry_value ); ?>
					<?php if ( '' === trim( $mwright_entry_flat ) ) : ?>
						<span class="mwright-muted">&mdash;</span>
					<?php else : ?>
						<div class="mwright-entry__value"><?php echo nl2br( esc_html( $mwright_entry_flat ) ); ?></div>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>

		<?php foreach ( $mwright_entry_files as $mwright_entry_name => $mwright_entry_list ) : ?>
			<tr>
				<th scope="row">
					<?php echo esc_html( MWRIGHT_CF7_Bridge::friendly_label( $mwright_entry_name ) ); ?>
					<code>[<?php echo esc_html( $mwright_entry_name ); ?>]</code>
				</th>
				<td>
					<ul class="mwright-list-plain">
						<?php foreach ( $mwright_entry_list as $mwright_entry_file ) : ?>
							<li>
								<?php if ( '' !== $mwright_entry_file['path'] && MWRIGHT_Submissions::file_path( $mwright_entry_file['path'] ) ) : ?>
									<a class="mwright-btn mwright-btn--small"
										href="<?php echo esc_url( MWRIGHT_Submissions::download_url( $mwright_entry['id'], $mwright_entry_name, $mwright_entry_file['index'] ) ); ?>">
										<span class="dashicons dashicons-download" aria-hidden="true"></span>
										<?php echo esc_html( $mwright_entry_file['name'] ); ?>
									</a>
									<?php if ( $mwright_entry_file['size'] ) : ?>
										<span class="mwright-muted"><?php echo esc_html( size_format( $mwright_entry_file['size'] ) ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<?php echo esc_html( $mwright_entry_file['name'] ); ?>
									<span class="mwright-muted">
										<?php
										echo 'type' === $mwright_entry_file['error']
											? esc_html__( '— not stored, WordPress does not allow this file type', 'mailwright-for-contact-form-7' )
											: esc_html__( '— file no longer on disk', 'mailwright-for-contact-form-7' );
										?>
									</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
