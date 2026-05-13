<?php
/**
 * Read-only submission tracking meta box.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Admin;

use Virtual_Card_Elementor\Panel_Meta;
use Virtual_Card_Elementor\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays submission tracking info and disables editing.
 */
class Card_Submission_Meta_Box {

	public function register_hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'remove_all_meta_boxes' ], 9999 );
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ], 10000 );
		add_action( 'admin_head', [ $this, 'disable_submission_editing' ] );
		add_filter( 'wp_insert_post_data', [ $this, 'prevent_submission_changes' ], 10, 2 );
	}

	public function remove_all_meta_boxes(): void {
		global $wp_meta_boxes;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Post_Type::CARD_SUBMISSION_POST_TYPE !== $screen->post_type ) {
			return;
		}
		if ( isset( $wp_meta_boxes[ Post_Type::CARD_SUBMISSION_POST_TYPE ] ) ) {
			$wp_meta_boxes[ Post_Type::CARD_SUBMISSION_POST_TYPE ] = [];
		}
		remove_post_type_support( Post_Type::CARD_SUBMISSION_POST_TYPE, 'thumbnail' );
		remove_post_type_support( Post_Type::CARD_SUBMISSION_POST_TYPE, 'editor' );
	}

	public function register_meta_box(): void {
		add_meta_box(
			'vce_submission_tracking',
			__( 'Submission Tracking', VCE_TEXT_DOMAIN ),
			[ $this, 'render_meta_box' ],
			Post_Type::CARD_SUBMISSION_POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ): void {
		$sender_id      = (int) get_post_meta( $post->ID, Panel_Meta::SUBMISSION_SENDER_ID, true );
		$receiver_email = get_post_meta( $post->ID, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, true );
		$status         = get_post_meta( $post->ID, Panel_Meta::SUBMISSION_STATUS, true ) ?: 'saved';
		$viewed_count   = (int) get_post_meta( $post->ID, Panel_Meta::SUBMISSION_VIEWED_COUNT, true );
		$parent_id      = (int) $post->post_parent;
		$sender_user    = $sender_id ? get_userdata( $sender_id ) : null;

		$sent_count = 0;
		if ( $parent_id && $receiver_email ) {
			$all = get_posts(
				[
					'post_type'      => Post_Type::CARD_SUBMISSION_POST_TYPE,
					'post_parent'    => $parent_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'post_status'    => 'any',
				]
			);
			foreach ( $all as $sid ) {
				$d = get_post_meta( $sid, Panel_Meta::SUBMISSION_SENT_COUNT, true );
				if ( is_array( $d ) && isset( $d[ $receiver_email ] ) ) {
					$sent_count += (int) $d[ $receiver_email ];
				}
			}
		}

		$status_labels = [
			'saved'  => __( 'Saved', VCE_TEXT_DOMAIN ),
			'sent'   => __( 'Sent', VCE_TEXT_DOMAIN ),
			'viewed' => __( 'Viewed', VCE_TEXT_DOMAIN ),
		];
		$status_colors = [
			'saved'  => '#f0ad4e',
			'sent'   => '#5bc0de',
			'viewed' => '#5cb85c',
		];
		$status_label = $status_labels[ $status ] ?? ucfirst( $status );
		$status_color = $status_colors[ $status ] ?? '#999';
		?>
		<table class="widefat" style="border:0;">
			<tbody>
				<tr>
					<td style="width:140px;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Sender', VCE_TEXT_DOMAIN ); ?></td>
					<td style="padding:8px 10px;">
						<?php if ( $sender_user ) : ?>
							<a href="<?php echo esc_url( get_edit_user_link( $sender_id ) ); ?>">
								<?php echo esc_html( $sender_user->display_name ?: $sender_user->user_login ); ?>
							</a>
						<?php else : ?>
							<?php echo $sender_id ? esc_html( "#{$sender_id}" ) : '—'; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="width:140px;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Receiver Email', VCE_TEXT_DOMAIN ); ?></td>
					<td style="padding:8px 10px;"><?php echo $receiver_email ? esc_html( $receiver_email ) : '—'; ?></td>
				</tr>
				<tr>
					<td style="width:140px;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Status', VCE_TEXT_DOMAIN ); ?></td>
					<td style="padding:8px 10px;">
						<span style="display:inline-block;padding:2px 10px;border-radius:10px;color:#fff;background:<?php echo esc_attr( $status_color ); ?>;font-size:12px;font-weight:600;">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<td style="width:140px;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Times Sent', VCE_TEXT_DOMAIN ); ?></td>
					<td style="padding:8px 10px;"><?php echo esc_html( (string) $sent_count ); ?></td>
				</tr>
				<tr>
					<td style="width:140px;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Times Viewed', VCE_TEXT_DOMAIN ); ?></td>
					<td style="padding:8px 10px;"><?php echo esc_html( (string) $viewed_count ); ?></td>
				</tr>
				<tr>
					<td style="width:140px;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Virtual Card', VCE_TEXT_DOMAIN ); ?></td>
					<td style="padding:8px 10px;">
						<?php if ( $parent_id && Post_Type::POST_TYPE === get_post_type( $parent_id ) ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $parent_id ) ); ?>">
								<?php echo esc_html( get_the_title( $parent_id ) ?: "#{$parent_id}" ); ?>
							</a>
						<?php else : ?>
							<?php echo $parent_id ? esc_html( "#{$parent_id}" ) : '—'; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="width:140px;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Submission ID', VCE_TEXT_DOMAIN ); ?></td>
					<td style="padding:8px 10px;"><?php echo esc_html( (string) $post->ID ); ?></td>
				</tr>
				<tr>
					<td style="width:140px;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Created', VCE_TEXT_DOMAIN ); ?></td>
					<td style="padding:8px 10px;"><?php echo esc_html( get_the_date( 'Y-m-d H:i:s', $post->ID ) ); ?></td>
				</tr>
			</tbody>
		</table>

		<?php
		$logs = get_post_meta( $post->ID, Panel_Meta::SUBMISSION_LOG, true );
		if ( is_array( $logs ) && ! empty( $logs ) ) :
			$action_labels = [
				'created' => __( 'Created', VCE_TEXT_DOMAIN ),
				'sent'    => __( 'Sent', VCE_TEXT_DOMAIN ),
				'viewed'  => __( 'Viewed', VCE_TEXT_DOMAIN ),
			];
			$action_colors = [
				'created' => '#f0ad4e',
				'sent'    => '#5bc0de',
				'viewed'  => '#5cb85c',
			];
			?>
			<h3 style="margin:16px 0 8px;"><?php esc_html_e( 'Activity Log', VCE_TEXT_DOMAIN ); ?></h3>
			<table class="widefat" style="border:1px solid #ccd0d4;">
				<thead>
					<tr>
						<th style="padding:6px 8px;"><?php esc_html_e( 'Date/Time', VCE_TEXT_DOMAIN ); ?></th>
						<th style="padding:6px 8px;"><?php esc_html_e( 'Action', VCE_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td style="padding:6px 8px;"><?php echo esc_html( $log['time'] ?? '' ); ?></td>
						<td style="padding:6px 8px;">
							<span style="display:inline-block;padding:1px 8px;border-radius:8px;color:#fff;background:<?php echo esc_attr( $action_colors[ $log['action'] ] ?? '#999' ); ?>;font-size:11px;">
								<?php echo esc_html( $action_labels[ $log['action'] ] ?? $log['action'] ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	public function disable_submission_editing(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Post_Type::CARD_SUBMISSION_POST_TYPE !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			.post-type-card_submission #edit-slug-box,
			.post-type-card_submission #postdivrich,
			.post-type-card_submission #post-body-content,
			.post-type-card_submission .editor-block-list__layout,
			.post-type-card_submission .block-editor-block-list__layout,
			.post-type-card_submission .edit-post-visual-editor,
			.post-type-card_submission #minor-publishing-actions,
			.post-type-card_submission #misc-publishing-actions,
			.post-type-card_submission #publishing-action {
				display: none !important;
			}
			.post-type-card_submission .wrap .page-title-action,
			.post-type-card_submission .subsubsub {
				display: none !important;
			}
			.post-type-card_submission .wp-menu-name {
				display: inline;
			}
		</style>
		<script>
		jQuery(function($){
			$('#postdivrich, #post-body-content').remove();
			$('#title').prop('readonly', true).css('background','#f0f0f1').css('cursor','not-allowed');
		});
		</script>
		<?php
	}

	public function prevent_submission_changes( $data, $postarr ) {
		if ( Post_Type::CARD_SUBMISSION_POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}
		if ( empty( $postarr['ID'] ) ) {
			return $data;
		}

		if ( 'trash' === $data['post_status'] || 'trash' === get_post_status( $postarr['ID'] ) ) {
			return $data;
		}

		$original = get_post( $postarr['ID'] );
		if ( $original ) {
			$data['post_title']   = $original->post_title;
			$data['post_content'] = $original->post_content;
			$data['post_status']  = $original->post_status;
			$data['post_name']    = $original->post_name;
			$data['post_parent']  = $original->post_parent;
		}
		return $data;
	}
}
