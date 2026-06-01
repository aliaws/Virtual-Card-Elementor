<?php
/**
 * Frontend: My Submissions table for the My Account page.
 *
 * @package Virtual_Card_Elementor
 *
 * @var \WP_Post[] $submissions
 * @var string[]   $status_labels
 * @var string[]   $status_colors
 * @var int        $user_id
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vce-my-submissions">
	<h2><?php esc_html_e( 'My Submissions', VCE_TEXT_DOMAIN ); ?></h2>

	<div class="vce-submissions-table-wrap">
		<table class="vce-submissions-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Card', VCE_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Status', VCE_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Created', VCE_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Actions', VCE_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $submissions as $sub ) : ?>
					<?php
					$status         = get_post_meta( $sub->ID, \Virtual_Card_Elementor\Panel_Meta::SUBMISSION_STATUS, true ) ?: 'saved';
					$parent_id      = (int) $sub->post_parent;
					$parent_title   = $parent_id ? get_the_title( $parent_id ) : '';
					$edit_url       = $parent_id && ( 'saved' === $status || 'scheduled' === $status ) ? get_permalink( $parent_id ) : '';
					$view_url       = get_permalink( $sub->ID );
					$color          = $status_colors[ $status ] ?? '#999';
					$label          = $status_labels[ $status ] ?? ucfirst( $status );
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Card', VCE_TEXT_DOMAIN ); ?>">
							<?php echo esc_html( $parent_title ?: '#' . $parent_id ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Status', VCE_TEXT_DOMAIN ); ?>">
							<span class="vce-status-badge" style="background:<?php echo esc_attr( $color ); ?>;">
								<?php echo esc_html( $label ); ?>
							</span>
						</td>
						<td data-label="<?php esc_attr_e( 'Created', VCE_TEXT_DOMAIN ); ?>">
							<?php echo esc_html( get_the_date( 'Y-m-d H:i', $sub->ID ) ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Actions', VCE_TEXT_DOMAIN ); ?>">
							<?php if ( 'saved' === $status || 'scheduled' === $status ) : ?>
								<?php if ( $edit_url ) : ?>
									<a href="<?php echo esc_url( $edit_url ); ?>" class="vce-submission-action vce-submission-edit">
										<?php esc_html_e( 'Edit', VCE_TEXT_DOMAIN ); ?>
									</a>
								<?php endif; ?>
							<?php elseif ( $view_url ) : ?>
								<a href="<?php echo esc_url( $view_url ); ?>" class="vce-submission-action vce-submission-view">
									<?php esc_html_e( 'View', VCE_TEXT_DOMAIN ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
