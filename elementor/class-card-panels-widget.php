<?php
/**
 * Elementor: Card Panels widget.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Virtual_Card_Elementor\Panel_Meta;
use Virtual_Card_Elementor\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs the current post’s Card Panels (image set).
 */
class Card_Panels_Widget extends Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'card_panels';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Card Panels', VCE_TEXT_DOMAIN );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-columns';
	}

	/**
	 * @return string[]
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * @return string[]
	 */
	public function get_style_depends() {
		return [ 'vce-frontend-panel' ];
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'layout',
			[
				'label' => __( 'Layout', VCE_TEXT_DOMAIN ),
			]
		);

		$this->add_control(
			'columns',
			[
				'label'   => __( 'Columns', VCE_TEXT_DOMAIN ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 3,
				'min'     => 1,
				'max'     => 6,
			]
		);

		$this->add_control(
			'limit',
			[
				'label'   => __( 'Limit', VCE_TEXT_DOMAIN ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 6,
				'min'     => 1,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style',
			[
				'label' => __( 'Style', VCE_TEXT_DOMAIN ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'gap',
			[
				'label'      => __( 'Gap', VCE_TEXT_DOMAIN ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', '%' ],
				'range'      => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .virtual-card-panels' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'radius',
			[
				'label'      => __( 'Border radius', VCE_TEXT_DOMAIN ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range'      => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .virtual-card-panels img' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Frontend output.
	 */
	protected function render() {
		global $post;

		if ( ! $post ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$ids      = get_post_meta( $post->ID, Panel_Meta::META_KEY, true );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return;
		}

		$limit   = ! empty( $settings['limit'] ) ? (int) $settings['limit'] : 6;
		$ids     = array_slice( $ids, 0, $limit );
		$columns = ! empty( $settings['columns'] ) ? (int) $settings['columns'] : 3;

		Template::render(
			'frontend/card-panels.php',
			[
				'ids'     => $ids,
				'columns' => max( 1, min( 6, $columns ) ),
			]
		);
	}
}
