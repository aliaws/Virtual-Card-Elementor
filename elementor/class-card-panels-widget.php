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
			'editor',
			[
				'label' => __( 'Editor', VCE_TEXT_DOMAIN ),
			]
		);

		$this->add_control(
			'enable_front_editor',
			[
				'label'        => __( 'Enable front-end editor', VCE_TEXT_DOMAIN ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', VCE_TEXT_DOMAIN ),
				'label_off'    => __( 'No', VCE_TEXT_DOMAIN ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Toolbar, filmstrip, and draggable text. Drafts are stored in the visitor’s browser only; the virtual card template in the database is not modified.', VCE_TEXT_DOMAIN ),
			]
		);

		$this->add_control(
			'editor_font_family',
			[
				'label'     => __( 'Default text font', VCE_TEXT_DOMAIN ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'system',
				'options'   => self::get_font_options_labels(),
				'condition' => [
					'enable_front_editor' => 'yes',
				],
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
	 * Font labels for Elementor control and toolbar &lt;select&gt;.
	 *
	 * @return array<string, string>
	 */
	public static function get_font_options_labels(): array {
		return [
			'system'       => __( 'System UI', VCE_TEXT_DOMAIN ),
			'georgia'      => __( 'Georgia', VCE_TEXT_DOMAIN ),
			'tnr'          => __( 'Times New Roman', VCE_TEXT_DOMAIN ),
			'verdana'      => __( 'Verdana', VCE_TEXT_DOMAIN ),
			'comic'        => __( 'Comic Sans MS', VCE_TEXT_DOMAIN ),
			'impact'       => __( 'Impact', VCE_TEXT_DOMAIN ),
			'courier'      => __( 'Courier New', VCE_TEXT_DOMAIN ),
			'open-sans'    => __( 'Open Sans (Google)', VCE_TEXT_DOMAIN ),
			'lato'         => __( 'Lato (Google)', VCE_TEXT_DOMAIN ),
			'merriweather' => __( 'Merriweather (Google)', VCE_TEXT_DOMAIN ),
			'roboto'       => __( 'Roboto (Google)', VCE_TEXT_DOMAIN ),
			'playfair'     => __( 'Playfair Display (Google)', VCE_TEXT_DOMAIN ),
		];
	}

	/**
	 * CSS font-family stack for editor font keys.
	 *
	 * @param string $key Setting value.
	 */
	public static function get_font_stack( string $key ): string {
		$map = [
			'system'       => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
			'georgia'      => 'Georgia, "Times New Roman", serif',
			'tnr'          => '"Times New Roman", Times, serif',
			'verdana'      => 'Verdana, Geneva, sans-serif',
			'comic'        => '"Comic Sans MS", "Comic Sans", cursive',
			'impact'       => 'Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif',
			'courier'      => '"Courier New", Courier, monospace',
			'open-sans'    => '"Open Sans", sans-serif',
			'lato'         => 'Lato, sans-serif',
			'merriweather' => 'Merriweather, serif',
			'roboto'       => 'Roboto, sans-serif',
			'playfair'     => '"Playfair Display", Georgia, serif',
		];

		return $map[ $key ] ?? $map['system'];
	}

	/**
	 * Font stacks for wp_localize_script (JS).
	 *
	 * @return array<string, string>
	 */
	public static function get_font_stacks_for_js(): array {
		$keys = [ 'system', 'georgia', 'tnr', 'verdana', 'comic', 'impact', 'courier', 'open-sans', 'lato', 'merriweather', 'roboto', 'playfair' ];
		$out  = [];
		foreach ( $keys as $key ) {
			$out[ $key ] = self::get_font_stack( $key );
		}
		return $out;
	}

	/**
	 * Enqueue Google Fonts CSS when the selected editor font needs it.
	 *
	 * @param string $key Setting value.
	 */
	public static function enqueue_editor_google_font( string $key ): void {
		$urls = [
			'open-sans'    => 'https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;0,700;1,400&display=swap',
			'lato'         => 'https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,400;0,700;1,400&display=swap',
			'merriweather' => 'https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,400;0,700;1,400&display=swap',
			'roboto'       => 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,700;1,400&display=swap',
			'playfair'     => 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap',
		];

		if ( isset( $urls[ $key ] ) ) {
			wp_enqueue_style(
				'vce-editor-gfont-' . sanitize_key( $key ),
				$urls[ $key ],
				[],
				null
			);
		}
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

		$editor_on = ! empty( $settings['enable_front_editor'] ) && 'yes' === $settings['enable_front_editor'];
		$can_edit  = function_exists( 'vce_can_use_front_editor' ) && vce_can_use_front_editor();

		if ( $editor_on && $can_edit ) {
			$font_key = isset( $settings['editor_font_family'] ) ? (string) $settings['editor_font_family'] : 'system';

			wp_enqueue_style( 'vce-frontend-panel-editor' );
			wp_enqueue_script( 'vce-frontend-panel-editor' );

			self::enqueue_editor_google_font( $font_key );

			$editor_localize = [
				'defaultFont'  => $font_key,
				'fontStacks'   => self::get_font_stacks_for_js(),
				'i18n'         => [
					'defaultText'         => __( 'Your text', VCE_TEXT_DOMAIN ),
					'finalReview'         => __( 'Final review', VCE_TEXT_DOMAIN ),
					'closePreview'        => __( 'Close', VCE_TEXT_DOMAIN ),
					'previewLoading'      => __( 'Building preview…', VCE_TEXT_DOMAIN ),
					'prevPanel'           => __( 'Previous panel', VCE_TEXT_DOMAIN ),
					'nextPanel'           => __( 'Next panel', VCE_TEXT_DOMAIN ),
					'panelPreview'        => __( 'Panel preview', VCE_TEXT_DOMAIN ),
					'leaveUnsavedDraft'   => __(
						'You have text on this card that is only saved in this browser. Leave anyway?',
						VCE_TEXT_DOMAIN
					),
				],
			];
			if ( \Virtual_Card_Elementor\Debug_Log::vce_debug_client_enabled() ) {
				$editor_localize['vceDiag'] = [
					'cardPostId' => (int) $post->ID,
					'panelCount' => count( $ids ),
				];
			}
			wp_localize_script( 'vce-frontend-panel-editor', 'vcePanelEditor', $editor_localize );

			$panels_data = [];
			foreach ( $ids as $aid ) {
				$aid   = (int) $aid;
				$large = $aid ? wp_get_attachment_image_src( $aid, 'large' ) : null;
				$thumb = $aid ? wp_get_attachment_image_src( $aid, 'thumbnail' ) : null;
				$url   = ( $large && ! empty( $large[0] ) ) ? $large[0] : '';
				if ( '' === $url && $aid ) {
					$url = wp_get_attachment_url( $aid ) ?: '';
				}
				$panels_data[] = [
					'id'    => $aid,
					'url'   => $url,
					'w'     => isset( $large[1] ) ? (int) $large[1] : 0,
					'h'     => isset( $large[2] ) ? (int) $large[2] : 0,
					'thumb' => ( $thumb && ! empty( $thumb[0] ) ) ? $thumb[0] : $url,
				];
			}

			Template::render(
				'frontend/card-panels-editor.php',
				[
					'post_id'      => (int) $post->ID,
					'ids'          => $ids,
					'panels_data'  => $panels_data,
					'editor_font'  => $font_key,
					'font_options' => self::get_font_options_labels(),
				]
			);
			return;
		}

		Template::render(
			'frontend/card-panels.php',
			[
				'ids'     => $ids,
				'columns' => max( 1, min( 6, $columns ) ),
			]
		);
	}
}
