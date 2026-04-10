<?php
/**
 * Plugin Name: Virtual Card Elementor
 */

if (!defined('ABSPATH')) exit;


/**
 * 1. Register Virtual Card CPT
 */
add_action('init', function () {

    register_post_type(
        'virtual_card',
        [
            'label' => 'Virtual Cards',
            'public' => true,
            'menu_icon' => 'dashicons-format-gallery',

            'supports' => [
                'title',
                'editor',
                'thumbnail'
            ],

            'show_in_rest' => true
        ]
    );

});



/**
 * 2. Add gallery meta to Virtual Card
 */
add_action('add_meta_boxes', function () {

    add_meta_box(
        'virtual_card_gallery',
        'Card Gallery',
        'virtual_card_gallery_html',
        'virtual_card',
        'normal',
        'high'
    );

});



function virtual_card_gallery_html($post)
{
    $ids = get_post_meta(
        $post->ID,
        '_virtual_card_gallery',
        true
    );

    $ids = is_array($ids) ? $ids : [];

    wp_nonce_field(
        'virtual_card_nonce',
        'virtual_card_nonce_field'
    );

?>

<div>

    <ul id="virtual_card_list">

        <?php foreach ($ids as $id): ?>

            <li data-id="<?php echo $id ?>">

                <?php echo wp_get_attachment_image($id,'thumbnail'); ?>

                <a href="#" class="remove">×</a>

            </li>

        <?php endforeach; ?>

    </ul>


    <input
        type="hidden"
        name="virtual_card_ids"
        id="virtual_card_ids"
        value="<?php echo esc_attr(
            implode(',', $ids)
        ); ?>"
    >


    <button class="button" id="virtual_card_add">

        Add Images

    </button>

</div>



<style>

#virtual_card_list{
display:flex;
gap:10px;
flex-wrap:wrap;
}

#virtual_card_list img{
width:80px;
height:80px;
object-fit:cover;
}

#virtual_card_list li{
position:relative;
}

#virtual_card_list .remove{

position:absolute;
right:-5px;
top:-5px;
background:red;
color:white;
padding:2px 6px;

}

</style>



<script>

jQuery(function($){

let frame;

$('#virtual_card_add').click(function(e){

e.preventDefault();

frame = wp.media({

title:'Select Images',

multiple:true

});


frame.on('select',function(){

let selection =
frame.state()
.get('selection')
.toJSON();


let ids =
$('#virtual_card_ids')
.val()
? $('#virtual_card_ids')
.val()
.split(',')
: [];


selection.forEach(img => {

ids.push(img.id);


$('#virtual_card_list').append(

`<li data-id="${img.id}">

<img src="${img.sizes.thumbnail.url}">

<a href="#" class="remove">×</a>

</li>`

);

});


$('#virtual_card_ids')
.val(ids.join(','));

});


frame.open();

});



$(document).on(
'click',
'.remove',
function(e){

e.preventDefault();


let li =
$(this).closest('li');


let id =
li.data('id');


let ids =
$('#virtual_card_ids')
.val()
.split(',');


ids =
ids.filter(v => v != id);


$('#virtual_card_ids')
.val(ids.join(','));


li.remove();

});

});

</script>


<?php
}


/**
 * save gallery
 */
add_action(
'save_post_virtual_card',
function ($post_id) {

if (
!isset(
$_POST[
'virtual_card_nonce_field'
]
)
) return;


if (
!wp_verify_nonce(
$_POST[
'virtual_card_nonce_field'
],
'virtual_card_nonce'
)
) return;



if (!empty(
$_POST['virtual_card_ids']
)) {

$ids =
array_map(
'intval',
explode(
',',
$_POST[
'virtual_card_ids'
]
)
);


update_post_meta(

$post_id,

'_virtual_card_gallery',

$ids

);

}

else {

delete_post_meta(

$post_id,

'_virtual_card_gallery'

);

}

}
);




/**
 * 3. Elementor widget
 */
add_action(
'elementor/widgets/register',
function ($widgets_manager) {

require_once __DIR__ .
'/virtual-card-widget.php';


$widgets_manager
->register(

new
\Virtual_Card_Widget()

);

});

