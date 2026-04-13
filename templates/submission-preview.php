<?php
/**
 * Full-page submission preview (recipient view). Loaded via rewrite card-submission/{uuid}/.
 *
 * @package Virtual_Card_Elementor
 */

defined( 'ABSPATH' ) || exit;

use Virtual_Card_Elementor\Debug_Log;
use Virtual_Card_Elementor\Submission;
use Virtual_Card_Elementor\Submission_Preview;

$uuid = get_query_var( Submission_Preview::QUERY_VAR );
$uuid = is_string( $uuid ) ? sanitize_text_field( $uuid ) : '';
$post = $uuid ? Submission::find_by_uuid( $uuid ) : null;

if ( ! $post ) {
	if ( Debug_Log::enabled() ) {
		Debug_Log::log( 'preview_template 404 invalid_uuid_or_missing_post uuid_len=' . strlen( $uuid ) );
	}
	status_header( 404 );
	nocache_headers();
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Not found', VCE_TEXT_DOMAIN ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<p><?php esc_html_e( 'This submission link is invalid or expired.', VCE_TEXT_DOMAIN ); ?></p>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
	exit;
}

nocache_headers();
$card_id = (int) get_post_meta( $post->ID, Submission::META_CARD_ID, true );
if ( $card_id <= 0 || get_post_type( $card_id ) !== \Virtual_Card_Elementor\Post_Type::POST_TYPE ) {
	if ( Debug_Log::enabled() ) {
		Debug_Log::log(
			'preview_template 404 bad_card submission_id=' . $post->ID . ' card_id=' . $card_id . ' card_type=' . ( $card_id > 0 ? get_post_type( $card_id ) : '0' )
		);
	}
	status_header( 404 );
	exit;
}

if ( Debug_Log::enabled() ) {
	Debug_Log::log( 'preview_template 200 submission_id=' . $post->ID . ' card_id=' . $card_id );
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: site name */
			__( 'Card preview — %s', VCE_TEXT_DOMAIN ),
			get_bloginfo( 'name' )
		)
	);
	?>
	</title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'vce-submission-preview-body' ); ?>>
<div
	class="vce-submission-preview"
	id="vce-submission-preview-root"
	data-vce-submission-preview
	role="dialog"
	aria-modal="true"
	aria-label="<?php echo esc_attr__( 'Card preview', VCE_TEXT_DOMAIN ); ?>"
>
	<div class="vce-spv__header">
		<span class="vce-spv__title" data-vce-spv-title></span>
		<button type="button" class="vce-spv__close" data-vce-spv-close aria-label="<?php echo esc_attr__( 'Close', VCE_TEXT_DOMAIN ); ?>">
			<span aria-hidden="true">&times;</span>
		</button>
	</div>
	<div class="vce-spv__loading" data-vce-spv-loading><?php esc_html_e( 'Loading…', VCE_TEXT_DOMAIN ); ?></div>
	<div class="vce-spv__body" data-vce-spv-body hidden>
		<button type="button" class="vce-spv__nav vce-spv__nav--prev" data-vce-spv-prev aria-label="<?php echo esc_attr__( 'Previous panel', VCE_TEXT_DOMAIN ); ?>">‹</button>
		<div class="vce-spv__stage">
			<img src="" alt="" class="vce-spv__main" data-vce-spv-main decoding="async" width="800" height="600" />
		</div>
		<button type="button" class="vce-spv__nav vce-spv__nav--next" data-vce-spv-next aria-label="<?php echo esc_attr__( 'Next panel', VCE_TEXT_DOMAIN ); ?>">›</button>
	</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
<?php
exit;
