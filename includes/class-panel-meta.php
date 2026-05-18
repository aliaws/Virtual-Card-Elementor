<?php
/**
 * Shared panel (image set) post meta constants.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Panel attachment IDs stored in post meta.
 */
class Panel_Meta {

	public const META_KEY = '_virtual_card_panels';

	public const SUBMISSION_LAYERS_META_KEY = '_vce_submission_layers';

	public const WIX_META_KEY = '_ads_wix_card_id';

	/**
	 * Custom sort / display order for Virtual Cards (integer, 0 = default).
	 */
	public const ORDER_META_KEY = 'order';

	/**
	 * Elementor Posts / Loop "Order By" value for {@see Panel_Meta::ORDER_META_KEY}.
	 */
	public const ORDERBY_DISPLAY_ORDER = 'vce_display_order';

	/**
	 * Is Favorite (checkbox).
	 */
	public const IS_FAVORITE_META_KEY = '_vce_is_favorite';

	/**
	 * First Level Label (text).
	 */
	public const FIRST_LEVEL_LABEL_META_KEY = '_vce_first_level_label';

/**
 * Second Level Label (text).
 */
public const SECOND_LEVEL_LABEL_META_KEY = '_vce_second_level_label';

/**
 * Submission sender user ID.
 */
public const SUBMISSION_SENDER_ID = '_vce_sender_id';

/**
 * Submission receiver email.
 */
public const SUBMISSION_RECEIVER_EMAIL = '_vce_receiver_email';

/**
 * Submission status: saved, sent, viewed.
 */
public const SUBMISSION_STATUS = '_vce_submission_status';

/**
 * Number of times email sent.
 */
public const SUBMISSION_SENT_COUNT = '_vce_sent_count';

/**
 * Number of times viewed.
 */
public const SUBMISSION_VIEWED_COUNT = '_vce_viewed_count';

/**
 * Submission activity log.
 */
public const SUBMISSION_LOG = '_vce_submission_log';

}
