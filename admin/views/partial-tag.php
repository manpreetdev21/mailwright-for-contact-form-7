<?php
/**
 * A single insertable tag chip.
 *
 * Expects $mwright_tag (tag name without brackets) and $mwright_label (friendly name).
 *
 * @package Mailwright_For_Contact_Form_7
 */

defined( 'ABSPATH' ) || exit;
?>
<span class="mwright-tag" data-tag="<?php echo esc_attr( $mwright_tag ); ?>" data-search="<?php echo esc_attr( strtolower( $mwright_label . ' ' . $mwright_tag ) ); ?>">
	<button type="button" class="mwright-tag__insert" data-insert="<?php echo esc_attr( $mwright_tag ); ?>"
		title="<?php echo esc_attr( sprintf( '[%s]', $mwright_tag ) ); ?>">
		<span class="mwright-tag__label"><?php echo esc_html( $mwright_label ); ?></span>
		<code class="mwright-tag__code">[<?php echo esc_html( $mwright_tag ); ?>]</code>
	</button>
	<button type="button" class="mwright-tag__copy" data-copy="<?php echo esc_attr( $mwright_tag ); ?>"
		aria-label="<?php echo esc_attr( sprintf( /* translators: %s: tag name */ __( 'Copy [%s]', 'mailwright-for-contact-form-7' ), $mwright_tag ) ); ?>">
		<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
	</button>
</span>
