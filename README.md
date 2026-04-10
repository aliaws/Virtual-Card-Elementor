# Virtual Card Elementor

Minimal WordPress plugin that registers the **`virtual_card`** post type, stores a **multi-image gallery** in post meta, and registers an **Elementor** widget that outputs that gallery for whatever post is current in the loop.

There is **no** custom taxonomy in this version—only the post type, gallery meta, and the widget.

## Requirements

- **WordPress** with a working media library (the gallery UI uses `wp.media`).
- **Elementor** (the plugin hooks `elementor/widgets/register` and instantiates `\Elementor\Widget_Base`). There is **no** `class_exists( '\Elementor\Plugin' )` guard; if Elementor is deactivated, PHP may error when that hook runs.

## Installation

1. Copy this folder into `wp-content/plugins/`.
2. Activate **Virtual Card Elementor** in **Plugins**.
3. Visit **Settings → Permalinks** and click **Save Changes** once so rewrite rules for the new post type are registered.

## What the code does

### `virtual-card-elementor.php`

**Custom post type `virtual_card` (on `init`)**

| Argument | Value |
|----------|--------|
| `label` | Virtual Cards |
| `public` | `true` |
| `menu_icon` | `dashicons-format-gallery` |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |

WordPress defaults apply for anything not set (e.g. rewrite slug is normally the post type key `virtual_card` unless changed elsewhere).

**Admin: Card Gallery meta box**

- Hook: `add_meta_boxes`
- Box id: `virtual_card_gallery`, title **Card Gallery**, screen `virtual_card`, context `normal`, priority `high`
- Reads/writes meta key **`_virtual_card_gallery`**: must be an **array of attachment IDs** when saved.
- Outputs a nonce: action `virtual_card_nonce`, field name **`virtual_card_nonce_field`**.
- Hidden input **`virtual_card_ids`**: comma-separated attachment IDs (also updated by jQuery when using **Add Images** / remove).
- Front-end of the box uses inline **jQuery** and **`wp.media`** with `multiple: true` to pick images; thumbnails use the **`thumbnail`** image size in the list UI.

**Saving the gallery (`save_post_virtual_card`)**

Runs only if:

- `$_POST['virtual_card_nonce_field']` is set, and  
- `wp_verify_nonce( … , 'virtual_card_nonce' )` passes.

Then:

- If **`virtual_card_ids`** is non-empty (after `empty()`): split on commas, `intval` each part, `update_post_meta( $post_id, '_virtual_card_gallery', $ids )`.
- **Else** (including empty string after removing all images): **`delete_post_meta( $post_id, '_virtual_card_gallery' )`**.

So saves that **do not** include the gallery nonce (typical **REST-only** / block editor flows that skip classic metabox POST data) will **not** change `_virtual_card_gallery` at all—the callback returns before any meta update.

**Elementor registration**

- Hook: `elementor/widgets/register`
- `require_once` `virtual-card-widget.php` and registers `new \Virtual_Card_Widget()`.

### `virtual-card-widget.php`

Class **`Virtual_Card_Widget`** extends `\Elementor\Widget_Base`.

| Method / area | Behavior |
|-----------------|----------|
| `get_name()` | `virtual_card_gallery` |
| `get_title()` | “Virtual Card Gallery” (`__( …, 'text-domain' )` — placeholder text domain) |
| `get_icon()` | `eicon-gallery-grid` |
| `get_categories()` | `[ 'general' ]` |
| **Layout controls** | **Columns**: `NUMBER`, default `3`, min `1`, max `6`. **Limit**: `NUMBER`, default `6`, min `1`. |
| **Style controls** | Responsive **Gap** → `{{WRAPPER}} .virtual-card-gallery` → `gap`. Responsive **Border radius** → `{{WRAPPER}} .virtual-card-gallery img` → `border-radius`. |
| **`render()`** | Uses **`global $post`**. If `$post` is missing, outputs nothing. Loads `_virtual_card_gallery`; if empty or not an array, outputs nothing. Otherwise `array_slice` to **Limit**, builds a grid: `display: grid; grid-template-columns: repeat( columns, 1fr )` on **`.virtual-card-gallery`**, each image in **`.virtual-card-item`**, `wp_get_attachment_image( …, 'large', … )` with class **`virtual-card-image`**, `loading="lazy"`, `alt` from `_wp_attachment_image_alt`. |

**Important:** The widget always shows the gallery for **`$post` in the global scope**, not a post selected in the widget. Use it where the main queried post is the desired `virtual_card` (e.g. single template for that CPT).

## File layout

| File | Role |
|------|------|
| `virtual-card-elementor.php` | CPT, gallery meta box + save handler, Elementor widget registration |
| `virtual-card-widget.php` | `Virtual_Card_Widget` definition |

## Example: query virtual cards in PHP

```php
$query = new WP_Query([
    'post_type'      => 'virtual_card',
    'post_status'    => 'publish',
    'posts_per_page' => 10,
]);
```

## Plugin header note

The main file only declares **`Plugin Name: Virtual Card Elementor`**. There is no `Version`, `Description`, or `Text Domain` header in code; widget strings still pass `'text-domain'` to `__()`.

## License

If you distribute this plugin, use a license consistent with WordPress (commonly **GPL-2.0-or-later**). The repository does not ship a `LICENSE` file by default.
