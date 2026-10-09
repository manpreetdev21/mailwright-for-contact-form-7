<?php
/**
 * Global branding screen.
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;

$mwright_branding = MWRIGHT_Branding::get();
?>
<div class="wrap mwright mwright-branding">

	<?php
	MWRIGHT_Admin::header( __( 'Global Branding', 'mailwright-for-contact-form-7' ) );
	MWRIGHT_Admin::flash();
	?>

	<div class="mwright-alert mwright-alert--info">
		<?php esc_html_e( 'These values fill in the branding tags used by your templates, so one change updates every email at once.', 'mailwright-for-contact-form-7' ); ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mwright-branding__form">
		<input type="hidden" name="action" value="mwright_save_branding" />
		<?php wp_nonce_field( 'mwright_save_branding' ); ?>

		<div class="mwright-columns">

			<div class="mwright-card">
				<div class="mwright-card__head"><h2><?php esc_html_e( 'Company', 'mailwright-for-contact-form-7' ); ?></h2></div>

				<p class="mwright-field">
					<label for="mwright-company-name"><?php esc_html_e( 'Company Name', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="text" id="mwright-company-name" name="branding[company_name]"
						value="<?php echo esc_attr( $mwright_branding['company_name'] ); ?>" />
					<span class="mwright-help"><code>[mwright_company_name]</code></span>
				</p>

				<div class="mwright-field">
					<label for="mwright-logo"><?php esc_html_e( 'Logo', 'mailwright-for-contact-form-7' ); ?></label>
					<div class="mwright-media">
						<input type="url" id="mwright-logo" name="branding[logo]" data-media-field
							value="<?php echo esc_attr( $mwright_branding['logo'] ); ?>" />
						<button type="button" class="mwright-btn mwright-btn--small" data-media-choose>
							<?php esc_html_e( 'Choose image', 'mailwright-for-contact-form-7' ); ?>
						</button>
					</div>
					<span class="mwright-help">
						<?php esc_html_e( 'Use a hosted image; email clients cannot read local files.', 'mailwright-for-contact-form-7' ); ?>
						<code>[mwright_logo]</code>
					</span>
					<?php if ( $mwright_branding['logo'] && MWRIGHT_Branding::is_private_host( $mwright_branding['logo'] ) ) : ?>
						<span class="mwright-alert mwright-alert--warning">
							<?php
							printf(
								/* translators: %s: host name of the logo URL */
								esc_html__( 'This logo is served from %s, which only exists on this machine. It shows in the preview here, but stays blank in the email your visitors receive. Upload the logo on the live site, or point this field at a publicly reachable URL.', 'mailwright-for-contact-form-7' ),
								esc_html( (string) wp_parse_url( $mwright_branding['logo'], PHP_URL_HOST ) )
							);
							?>
						</span>
					<?php endif; ?>
					<?php if ( $mwright_branding['logo'] ) : ?>
						<img class="mwright-media__preview" src="<?php echo esc_url( $mwright_branding['logo'] ); ?>" alt="" data-media-preview />
					<?php else : ?>
						<img class="mwright-media__preview" src="" alt="" data-media-preview hidden />
					<?php endif; ?>
				</div>

				<p class="mwright-field">
					<label for="mwright-website"><?php esc_html_e( 'Website URL', 'mailwright-for-contact-form-7' ); ?></label>
					<input type="url" id="mwright-website" name="branding[website]"
						value="<?php echo esc_attr( $mwright_branding['website'] ); ?>" />
					<span class="mwright-help"><code>[mwright_website]</code></span>
				</p>

				<p class="mwright-field">
					<label for="mwright-address"><?php esc_html_e( 'Company Address', 'mailwright-for-contact-form-7' ); ?></label>
					<textarea id="mwright-address" name="branding[address]" rows="3"><?php echo esc_textarea( $mwright_branding['address'] ); ?></textarea>
					<span class="mwright-help"><code>[mwright_address]</code></span>
				</p>
			</div>

			<div class="mwright-card">
				<div class="mwright-card__head"><h2><?php esc_html_e( 'Appearance', 'mailwright-for-contact-form-7' ); ?></h2></div>

				<?php
				$mwright_colors = array(
					'primary_color'   => __( 'Primary Colour', 'mailwright-for-contact-form-7' ),
					'secondary_color' => __( 'Secondary Colour', 'mailwright-for-contact-form-7' ),
				);

				foreach ( $mwright_colors as $mwright_key => $mwright_label ) :
					$id = 'mwright-' . str_replace( '_', '-', $mwright_key );
					?>
					<p class="mwright-field">
						<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $mwright_label ); ?></label>
						<span class="mwright-color">
							<input type="color" id="<?php echo esc_attr( $id ); ?>" name="branding[<?php echo esc_attr( $mwright_key ); ?>]"
								value="<?php echo esc_attr( $mwright_branding[ $mwright_key ] ); ?>" data-color-input />
							<input type="text" class="mwright-color__hex" aria-label="<?php echo esc_attr( $mwright_label ); ?>"
								value="<?php echo esc_attr( $mwright_branding[ $mwright_key ] ); ?>" maxlength="7" data-color-hex />
						</span>
						<span class="mwright-help"><code>[mwright_<?php echo esc_attr( $mwright_key ); ?>]</code></span>
					</p>
				<?php endforeach; ?>

				<p class="mwright-field">
					<label for="mwright-footer-text"><?php esc_html_e( 'Footer Text', 'mailwright-for-contact-form-7' ); ?></label>
					<textarea id="mwright-footer-text" name="branding[footer_text]" rows="3"><?php echo esc_textarea( $mwright_branding['footer_text'] ); ?></textarea>
					<span class="mwright-help"><code>[mwright_footer_text]</code></span>
				</p>
			</div>

			<div class="mwright-card">
				<div class="mwright-card__head"><h2><?php esc_html_e( 'Social Links', 'mailwright-for-contact-form-7' ); ?></h2></div>

				<?php
				$mwright_socials = array(
					'social_facebook'  => __( 'Facebook', 'mailwright-for-contact-form-7' ),
					'social_twitter'   => __( 'X', 'mailwright-for-contact-form-7' ),
					'social_linkedin'  => __( 'LinkedIn', 'mailwright-for-contact-form-7' ),
					'social_instagram' => __( 'Instagram', 'mailwright-for-contact-form-7' ),
				);

				foreach ( $mwright_socials as $mwright_key => $mwright_label ) :
					?>
					<p class="mwright-field">
						<label for="mwright-<?php echo esc_attr( $mwright_key ); ?>"><?php echo esc_html( $mwright_label ); ?></label>
						<input type="url" id="mwright-<?php echo esc_attr( $mwright_key ); ?>" name="branding[<?php echo esc_attr( $mwright_key ); ?>]"
							value="<?php echo esc_attr( $mwright_branding[ $mwright_key ] ); ?>" placeholder="https://" />
					</p>
				<?php endforeach; ?>

				<p class="mwright-help">
					<?php esc_html_e( 'Only the networks you fill in are shown.', 'mailwright-for-contact-form-7' ); ?>
					<code>[mwright_social_links]</code>
				</p>
			</div>

		</div>

		<p class="mwright-form-actions">
			<button type="submit" class="mwright-btn mwright-btn--primary"><?php esc_html_e( 'Save Branding', 'mailwright-for-contact-form-7' ); ?></button>
		</p>
	</form>
</div>
