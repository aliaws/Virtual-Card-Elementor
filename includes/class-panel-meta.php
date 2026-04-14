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

}
