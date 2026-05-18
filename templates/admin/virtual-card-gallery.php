<div class="wrap vce-gallery-wrap">
	<h1><?php esc_html_e( 'Card Gallery', VCE_TEXT_DOMAIN ); ?></h1>
	<p class="vce-gallery-count"><?php echo esc_html( sprintf( __( 'Total cards: %d', VCE_TEXT_DOMAIN ), $total ) ); ?></p>

	<?php if ( ! $query->have_posts() ) : ?>
		<p><?php esc_html_e( 'No virtual cards found.', VCE_TEXT_DOMAIN ); ?></p>
	<?php else : ?>
		<div class="vce-gallery-grid">
			<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<?php
				$post_id   = get_the_ID();
				$edit_url  = get_edit_post_link( $post_id, 'raw' );
				$title     = get_the_title() ?: '#' . $post_id;
				$order     = (int) get_post_meta( $post_id, $order_meta, true );
				$thumbnail = get_the_post_thumbnail( $post_id, 'large', [ 'class' => 'vce-gallery-grid-img' ] );
				$terms     = wp_get_post_terms( $post_id, 'virtual_card_category', [ 'fields' => 'names' ] );
				?>
				<div class="vce-gallery-grid-item" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
					<a href="<?php echo esc_url( $edit_url ); ?>" class="vce-gallery-grid-link">
						<div class="vce-gallery-grid-imgwrap">
							<span class="vce-gallery-grid-badges">
								<span class="vce-gallery-grid-order"><?php echo esc_html( (string) $order ); ?></span>
								<?php if ( ! empty( $terms ) ) : ?>
									<span class="vce-gallery-grid-cat"><?php echo esc_html( implode( ', ', $terms ) ); ?></span>
								<?php endif; ?>
							</span>
							<?php if ( $thumbnail ) : ?>
								<?php echo $thumbnail; ?>
							<?php else : ?>
								<span class="vce-gallery-grid-noimg"><?php esc_html_e( 'No image', VCE_TEXT_DOMAIN ); ?></span>
							<?php endif; ?>
							<button type="button" class="vce-gallery-edit-btn" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-title="<?php echo esc_attr( $title ); ?>" data-order="<?php echo esc_attr( (string) $order ); ?>"><?php esc_html_e( 'Edit', VCE_TEXT_DOMAIN ); ?></button>
						</div>
						<span class="vce-gallery-grid-title"><?php echo esc_html( $title ); ?></span>
					</a>
				</div>
			<?php endwhile; ?>
		</div>

		<div class="vce-gallery-modal" id="vce-gallery-modal" hidden>
			<div class="vce-gallery-modal-backdrop"></div>
			<div class="vce-gallery-modal-dialog">
				<button type="button" class="vce-gallery-modal-close">&times;</button>
				<h3><?php esc_html_e( 'Edit Card', VCE_TEXT_DOMAIN ); ?></h3>
				<form id="vce-gallery-edit-form">
					<input type="hidden" name="post_id" id="vce-edit-post-id" value="" />
					<p>
						<label for="vce-edit-title"><?php esc_html_e( 'Title', VCE_TEXT_DOMAIN ); ?></label>
						<input type="text" name="title" id="vce-edit-title" class="widefat" />
					</p>
					<p>
						<label for="vce-edit-order"><?php esc_html_e( 'Order', VCE_TEXT_DOMAIN ); ?></label>
						<input type="number" name="order" id="vce-edit-order" class="small-text" min="0" step="1" />
					</p>
					<p class="vce-gallery-modal-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', VCE_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button vce-gallery-modal-cancel"><?php esc_html_e( 'Cancel', VCE_TEXT_DOMAIN ); ?></button>
					</p>
					<p class="vce-gallery-modal-status" style="display:none;"></p>
				</form>
			</div>
		</div>

		<?php wp_reset_postdata(); ?>
	<?php endif; ?>
</div>
