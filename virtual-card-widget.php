<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Virtual_Card_Widget extends \Elementor\Widget_Base {

    // Widget Name
    public function get_name() {
        return 'virtual_card_gallery';
    }

    // Widget Title
    public function get_title() {
        return __( 'Virtual Card Gallery', 'text-domain' );
    }

    // Widget Icon
    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    // Widget Category
    public function get_categories() {
        return [ 'general' ];
    }

    // Register Controls
    protected function register_controls() {

        // Layout Section
        $this->start_controls_section(
            'layout',
            [
                'label' => __( 'Layout', 'text-domain' ),
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'   => __( 'Columns', 'text-domain' ),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 3,
                'min'     => 1,
                'max'     => 6,
            ]
        );

        $this->add_control(
            'limit',
            [
                'label'   => __( 'Limit', 'text-domain' ),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 6,
                'min'     => 1,
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style',
            [
                'label' => __( 'Style', 'text-domain' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'gap',
            [
                'label' => __( 'Gap', 'text-domain' ),
                'type'  => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px', 'em', '%' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .virtual-card-gallery' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'radius',
            [
                'label' => __( 'Border Radius', 'text-domain' ),
                'type'  => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .virtual-card-gallery img' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    // Render Widget Output
    protected function render() {
        global $post;

        if ( ! $post ) {
            return;
        }

        $settings = $this->get_settings_for_display();

        // Get gallery IDs from post meta
        $ids = get_post_meta( $post->ID, '_virtual_card_gallery', true );

        if ( empty( $ids ) || ! is_array( $ids ) ) {
            return;
        }

        // Limit the number of images
        $ids = array_slice( $ids, 0, intval( $settings['limit'] ) );

        $columns = ! empty( $settings['columns'] ) ? intval( $settings['columns'] ) : 3;
        ?>

        <div class="virtual-card-gallery" style="display: grid; grid-template-columns: repeat(<?php echo esc_attr( $columns ); ?>, 1fr);">
            <?php foreach ( $ids as $id ) : ?>
                <div class="virtual-card-item">
                    <?php
                    echo wp_get_attachment_image(
                        $id,
                        'large',
                        false,
                        [
                            'class' => 'virtual-card-image',
                            'loading' => 'lazy',
                            'alt' => esc_attr( get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
                        ]
                    );
                    ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
    }
}
