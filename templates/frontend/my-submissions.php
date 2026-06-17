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
$number = 1;
?>
<div class="vce-my-submissions">
	<h2><?php esc_html_e( 'My Submissions', VCE_TEXT_DOMAIN ); ?></h2>

	<div class="vce-submissions-table-wrap">
		<table class="vce-submissions-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'No', VCE_TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Card', VCE_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Status', VCE_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Scheduled Date', VCE_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Receiver Email', VCE_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Created', VCE_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Actions', VCE_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $submissions as $sub ) : ?>
					<?php
					$status         = get_post_meta( $sub->ID, \Virtual_Card_Elementor\Panel_Meta::SUBMISSION_STATUS, true ) ?: 'saved';
					$receiver_email = get_post_meta( $sub->ID, \Virtual_Card_Elementor\Panel_Meta::SUBMISSION_RECEIVER_EMAIL, true );
					$parent_id      = (int) $sub->post_parent;
					$parent_title   = $parent_id ? get_the_title( $parent_id ) : '';
					$edit_url       = $parent_id && ( 'saved' === $status || 'scheduled' === $status ) ? get_permalink( $parent_id )."?id={$sub->ID}" : '';
					$view_url       = get_permalink( $sub->ID );
					$color          = $status_colors[ $status ] ?? '#999';
					$label          = $status_labels[ $status ] ?? ucfirst( $status );
					// Blank unless status is scheduled; includes dynamic timezone label.
					$scheduled_date = \Virtual_Card_Elementor\Panel_Meta::format_scheduled_at_display( (int) $sub->ID );
					?>
					<tr>
                        <td data-label="<?php esc_attr_e( 'No', VCE_TEXT_DOMAIN ); ?>">
                            <?php echo $number; ?>
                        </td>
						<td data-label="<?php esc_attr_e( 'Card', VCE_TEXT_DOMAIN ); ?>">
							<?php echo esc_html( $parent_title ?: '#' . $parent_id ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Status', VCE_TEXT_DOMAIN ); ?>">
							<span class="vce-status-badge" style="background:<?php echo esc_attr( $color ); ?>;">
								<?php echo esc_html( $label ); ?>
							</span>
						</td>
						<td data-label="<?php esc_attr_e( 'Scheduled Date', VCE_TEXT_DOMAIN ); ?>">
							<?php echo $scheduled_date ? esc_html( $scheduled_date ) : '—'; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Receiver Email', VCE_TEXT_DOMAIN ); ?>">
							<?php echo $receiver_email ? esc_html( $receiver_email ) : '—'; ?>
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
									<?php esc_html_e( 'Preview', VCE_TEXT_DOMAIN ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php
                    $number = $number + 1;
                endforeach;
                ?>
			</tbody>
		</table>
	</div>
</div>
