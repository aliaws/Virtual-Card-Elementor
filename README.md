# Virtual Card Elementor

Minimal WordPress plugin that registers the **`virtual_card`** post type, stores **Card Panels** (attachment IDs) in post meta, and registers an **Elementor** widget named **Card Panels** that outputs those images for whatever post is current in the loop.

There is **no** custom taxonomy in this version—only the post type, panel meta, and the widget.

## Requirements

- **WordPress** with a working media library (the admin picker uses the core **`wp.media`** modal).
- **Elementor** (the plugin hooks `elementor/widgets/register`). The Elementor widget class file is loaded only when that hook runs, so the base plugin does not fatal if Elementor is inactive.

## Installation

1. Copy this folder into `wp-content/plugins/`.
2. Activate **Virtual Card Elementor** in **Plugins**.
3. Visit **Settings → Permalinks** and click **Save Changes** once so rewrite rules for the new post type are registered.

## Upgrading from older versions

If you used an earlier copy of this plugin that stored images under **`_virtual_card_gallery`** or the Elementor widget id **`virtual_card_gallery`**, re-save each Virtual Card’s **Card Panels** in the editor and replace the old widget with **Card Panels** (`card_panels`) in Elementor templates.

## What the code does

### Bootstrap: `virtual-card-elementor.php`

Defines constants, loads classes, runs `Plugin::instance()->run()`.

**Custom post type `virtual_card` (`Post_Type` on `init`)**

| Argument | Value |
|----------|--------|
| `labels` | Virtual Cards / Virtual Card |
| `public` | `true` |
| `menu_icon` | `dashicons-images-alt2` |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |

**Admin: Card Panels meta box (`Panel_Meta_Box`)**

- Hook: `add_meta_boxes`
- Box id: `virtual_card_panels`, title **Card Panels**, screen `virtual_card`, context `normal`, priority `high`
- Reads/writes meta key **`_virtual_card_panels`**: list of attachment IDs when saved.
- Nonce: action `virtual_card_panel_nonce`, field **`virtual_card_panel_nonce_field`**.
- Hidden input **`virtual_card_panel_ids`**: comma-separated attachment IDs; **`assets/js/admin-panel.js`** updates it when adding/removing rows.
- Styles: **`assets/css/admin-panel.css`**. Markup: **`templates/admin/panel-meta-box.php`**.

**Saving panels (`save_post_virtual_card` → `Panel_Meta_Box::save_panels`)**

Runs only if the panel nonce is present and verifies. If **`virtual_card_panel_ids`** is non-empty, IDs are sanitized and stored; otherwise meta is deleted. REST-only saves that omit the metabox POST fields do not change `_virtual_card_panels`.

**Elementor**

- Hook: `elementor/widgets/register` registers **`Card_Panels_Widget`** from `elementor/class-card-panels-widget.php`.
- Frontend style handle **`vce-frontend-panel`** → `assets/css/frontend-panel.css`.
- Markup: **`templates/frontend/card-panels.php`**.

| Widget | Behavior |
|--------|----------|
| `get_name()` | `card_panels` |
| `get_title()` | **Card Panels** |
| `get_icon()` | `eicon-columns` |
| **Layout** | Columns (1–6), Limit |
| **Style** | Gap / border radius on `.virtual-card-panels` |
| **`render()`** | Uses **`global $post`**, reads **`_virtual_card_panels`**, outputs the panel grid for the current loop post |

Use the widget where the main queried post is the desired `virtual_card` (e.g. single template for that CPT).

## File layout

| Path | Role |
|------|------|
| `virtual-card-elementor.php` | Bootstrap |
| `includes/class-plugin.php` | Hooks orchestration |
| `includes/class-post-type.php` | CPT registration |
| `includes/class-panel-meta.php` | Meta key constant |
| `includes/class-template.php` | Template loader |
| `admin/class-panel-meta-box.php` | Admin meta box + save + asset enqueue |
| `elementor/class-card-panels-widget.php` | Elementor widget |
| `templates/admin/panel-meta-box.php` | Admin markup |
| `templates/frontend/card-panels.php` | Frontend markup |
| `assets/css/admin-panel.css` | Admin styles |
| `assets/css/frontend-panel.css` | Widget styles |
| `assets/js/admin-panel.js` | Admin media picker UI |

## Example: query virtual cards in PHP

```php
$query = new WP_Query([
	'post_type'      => 'virtual_card',
	'post_status'    => 'publish',
	'posts_per_page' => 10,
]);
```

## License

If you distribute this plugin, use a license consistent with WordPress (commonly **GPL-2.0-or-later**). The repository does not ship a `LICENSE` file by default.
