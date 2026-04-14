# Virtual Card Elementor

WordPress plugin that registers a **`virtual_card`** post type (with **categories** taxonomy), stores **Card Panels** (attachment IDs) and optional **display order** / integration meta in post meta, and provides an **Elementor** widget (**Card Panels**) with a Fabric.js **front-end editor**, **final review** preview, and **`card_submission`** save flow.

It also registers **`card_submission`** (nested under the Virtual Cards admin menu) so front-end edits can be stored on a separate post and viewed without modifying the source virtual card or its panel attachments.

**Version** is defined in the plugin header in `virtual-card-elementor.php` (also exposed as **`VCE_VERSION`**). Asset URLs use **`vce_asset_version()`** (`VCE_VERSION` + file `mtime`) for cache busting.

## Requirements

- **WordPress** with a working media library (the admin picker uses the core **`wp.media`** modal).
- **Admin Tagify** loads **[Tagify](https://github.com/yairEO/tagify)** from **jsDelivr** (HTTPS). Restrictive CSPs or offline admin may need to allow that host or bundle assets locally.
- **Front-end editor / preview** loads **[Fabric.js 5.3](https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js)** from **jsDelivr**. Allow **`cdn.jsdelivr.net`** if CSP blocks it.
- **Elementor** (the plugin hooks `elementor/widgets/register`). The Elementor widget class file is loaded only when that hook runs, so the base plugin does not fatal if Elementor is inactive.

## Installation

1. Copy this folder into `wp-content/plugins/`.
2. Activate **Virtual Card Elementor** in **Plugins** (activation queues a one-time permalink flush via the **`vce_flush_rewrite_rules`** option).
3. Visit **Settings → Permalinks** and click **Save Changes** once so rewrite rules for the new post type are registered.

## Upgrading from older versions

If you used an earlier copy of this plugin that stored images under **`_virtual_card_gallery`** or the Elementor widget id **`virtual_card_gallery`**, re-save each Virtual Card’s **Card Panels** in the editor and replace the old widget with **Card Panels** (`card_panels`) in Elementor templates.

## What the code does

### Bootstrap: `virtual-card-elementor.php`

- Defines **`VCE_VERSION`** from the **`Version`** field in this file’s plugin header via **`get_file_data()`**, so the header stays the single source of truth for the release number.
- Defines **`vce_asset_version( $relative_path )`**: returns **`VCE_VERSION`** plus the file’s **`mtime`** when the asset exists, so enqueued CSS/JS get reliable cache-busting after edits.
- Optional **`VCE_DEBUG`** constant (defaults to **`false`** in code when unset): enables dedicated file logging and related admin/REST tooling (see **Diagnostics** below).
- Defines helpers **`vce_get_front_editor_mode()`** and **`vce_can_use_front_editor()`** (wrappers around **`Editor_Access`**).
- Defines path/url constants, text domain, loads PHP class files, runs **`Plugin::instance()->run()`**.

### Front-end editor access (`includes/class-editor-access.php`)

- Default mode is **`logged_in`**: only logged-in visitors see/use the front-end editor when the widget enables it.
- Filter **`vce_front_editor_mode`**: return **`guest`** (class constant **`Editor_Access::MODE_GUEST`**) to allow guests to use the editor UI.
- Filter **`vce_front_editor_can_use`**: final boolean override after mode is evaluated.

### Diagnostics and debugging

- **`Debug_Log`** (`includes/class-debug-log.php`): when **`VCE_DEBUG`** is **`true`** in `wp-config.php`, logs append to **`wp-content/uploads/vce-debug.log`** (not web-served by default). Can also mirror to PHP **`error_log`** when **`WP_DEBUG`** + **`WP_DEBUG_LOG`** are on.
- **`Vce_Debug_Page`** (`admin/class-vce-debug-page.php`): **Tools → VCE debug** ( **`manage_options`** ) — view log path, clear log. Intended as a temporary support screen.
- **`Vce_Debug_Rest`**: **`POST /wp-json/vce/v1/debug-client`** appends sanitized lines from the browser (admins only, **`VCE_DEBUG`** on). **`assets/js/vce-debug-client.js`** is registered as a dependency of the panel editor script when enabled.

**Custom post type `virtual_card` (`Post_Type` on `init`)**

| Argument | Value |
|----------|--------|
| `labels` | Virtual Cards / Virtual Card |
| `public` | `true` |
| `menu_icon` | `dashicons-images-alt2` |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |

**Taxonomy `virtual_card_category` (`Post_Type::register_post_taxonomy` on `init`)**

| Argument | Value |
|----------|--------|
| `object_types` | `virtual_card` |
| `hierarchical` | `true` (behaves like categories) |
| `show_admin_column` | `true` (term column on the Virtual Cards list) |
| `show_in_rest` | `true` |
| `public` / `show_ui` | `true` |

**Custom post type `card_submission` (`Post_Type` on `init`)**

| Argument | Value |
|----------|--------|
| `public` | `false` |
| `publicly_queryable` | `true` |
| `show_ui` | `true` |
| `show_in_menu` | `edit.php?post_type=virtual_card` (submenu under Virtual Cards) |
| `hierarchical` | `false` (flat permalinks like `/card-submission/slug/`; parent is still stored in `post_parent`) |
| `rewrite` | `slug` = `card-submission` |
| `query_var` | `card_submission` |

**Classic editor for submissions**

- Filter **`use_block_editor_for_post_type`**: **`card_submission`** uses the **classic** editor so parent meta boxes POST as expected; other post types are unchanged.

**Shared meta keys (`Panel_Meta`)**

| Constant | Meta key | Role |
|----------|----------|------|
| `META_KEY` | **`_virtual_card_panels`** | Ordered attachment IDs for Card Panels |
| `SUBMISSION_LAYERS_META_KEY` | **`_vce_submission_layers`** | Front-end submission layer payload (per panel index) |
| `WIX_META_KEY` | **`_ads_wix_card_id`** | External Wix / sync identifier (string) |
| `ORDER_META_KEY` | **`order`** | Optional integer sort key (`0` clears stored meta); constant name is still `ORDER_META_KEY`, value is the bare meta key **`order`**. |

**Admin: Card Panels meta box (`Panel_Meta_Box`)**

- Hook: `add_meta_boxes`
- Box id: `virtual_card_panels`, title **Card Panels**, screen `virtual_card`, context `normal`, priority `high`
- Reads/writes meta key **`_virtual_card_panels`**: list of attachment IDs when saved.
- Nonce: action `virtual_card_panel_nonce`, field **`virtual_card_panel_nonce_field`**.
- Hidden input **`virtual_card_panel_ids`**: comma-separated attachment IDs; **`assets/js/admin-panel.js`** updates it when adding/removing rows (reorder, preview modal, etc.).
- Enqueues use **`vce_asset_version()`** for **`assets/css/admin-panel.css`** and **`assets/js/admin-panel.js`** (script dependencies include **`jquery-ui-sortable`** and **`media-editor`**).
- Styles: **`assets/css/admin-panel.css`**. Markup: **`templates/admin/panel-meta-box.php`**.

**Admin: Display order meta box (`Panel_Meta_Box`)**

- Second meta box id **`virtual_card_display_order`**, title **Display order**, same screen, context **`normal`**, priority **`default`** (below Card Panels).
- Field **`vce_display_order`** (number, min `0`). Nonce action **`vce_display_order_save`**, field **`vce_display_order_nonce_field`**.
- Saved on **`save_post_virtual_card`** (priority **11**) into post meta key **`order`** (`Panel_Meta::ORDER_META_KEY`). **`0`** deletes meta (no custom order).

**Saving panels (`save_post_virtual_card` → `Panel_Meta_Box::save_panels`)**

Runs only if the panel nonce is present and verifies. If **`virtual_card_panel_ids`** is non-empty, IDs are sanitized and stored; otherwise meta is deleted. REST-only saves that omit the metabox POST fields do not change `_virtual_card_panels`.

**Virtual Cards admin list (`Virtual_Card_Admin_Columns`)**

On **Virtual Cards → All Virtual Cards**:

- **Category filter**: **`restrict_manage_posts`** outputs a **`virtual_card_category`** dropdown (same GET parameter name). **`parse_query`** applies a **`tax_query`** when a term is selected so the list matches that category (includes child terms).
- **Extra columns** (inserted after **Title**):

| Column | Meaning |
|--------|---------|
| **No. of panels** | Count of attachment IDs in **`_virtual_card_panels`**. |
| **WIX ID** | Value of **`_ads_wix_card_id`** (`Panel_Meta::WIX_META_KEY`), or **—** when empty. There is no dedicated admin field in this plugin for that key; it is intended for integrations or external sync that set the meta directly. |

The taxonomy also registers **`show_admin_column`** so WordPress adds its own **Categories** column where terms are assigned.

**Card Submissions admin (`Card_Submission_Admin`)**

- Adds **Virtual card** column in submissions list (sortable by **`post_parent`**).
- Adds **Final view** column with a stable link to the submission’s front-end view (`/?post_type=card_submission&p=ID`).
- Adds parent filter dropdown above the submissions list.
- Adds a parent selector meta box on submission edit screen (classic editor for **`card_submission`** so the meta box saves reliably).
- Saves **`post_parent`** safely (only allows parent posts of type **`virtual_card`**).

**Elementor**

- Hook: `elementor/widgets/register` registers **`Card_Panels_Widget`** from `elementor/class-card-panels-widget.php`.
- Frontend style handle **`vce-frontend-panel`** → `assets/css/frontend-panel.css`; editor/preview also enqueue **`vce-frontend-panel-editor`** and Fabric when needed.
- Templates: **`templates/frontend/card-panels.php`** (grid), **`card-panels-editor.php`** (editor), **`card-panels-submission.php`** (submission carousel).

| Widget | Behavior |
|--------|----------|
| `get_name()` | `card_panels` |
| `get_title()` | **Card Panels** |
| `get_icon()` | `eicon-columns` |
| **Layout** | Columns (1–6), Limit |
| **Style** | Gap / border radius on `.virtual-card-panels` |
| **`render()`** | Uses **`global $post`**, reads **`_virtual_card_panels`**, outputs the panel grid, optional **front-end editor** shell, or **submission** viewer depending on post type and widget settings |

When the current post is a **`card_submission`**, the widget resolves panel images from the parent **`virtual_card`** if the submission has no local panel list, then applies layer data from **`_vce_submission_layers`**.

**Front-end editor and submissions**

- Editor UI is rendered by **`templates/frontend/card-panels-editor.php`** and powered by **`assets/js/frontend-panel-editor.js`** (depends on **`fabric`**, **`vce-frontend-panel-renderer`**, and optionally **`vce-debug-client`**). Toolbar: font, size, **text color**, preset swatches, **text background** + clear (**Fabric** `textBackgroundColor`, including per-range selection while editing), bold / italic / underline, filmstrip, **Final review**, **Save submission**.
- Unsaved edits are **not** written to the **`virtual_card`** post or **`_virtual_card_panels`**. They are **not** persisted across a full page reload unless the user **saves a submission** (a new **`card_submission`** with **`_vce_submission_layers`** meta).
- Users can click **Save submission** in the front-end editor (REST nonce is sent when the visitor is logged in).
- Save endpoint: **`POST /wp-json/vce/v1/submission`** (`Card_Submission_Rest`, **`permission_callback`** allows unauthenticated calls — restrict or harden at edge if you expose this site-wide).
- Creates a new **`card_submission`**, sets **`post_parent`** to the source virtual card, stores the layer payload in **`_vce_submission_layers`**, and returns **`id`**, **`url`** / **`preview_url`** (query-string `/?post_type=card_submission&p=ID`), and **`edit_url`**.
- **`_vce_submission_layers`** is a map keyed by panel index (`"0"`, `"1"`, …). Each value holds Fabric **`objects`** plus **`baseW`** / **`baseH`** (editor canvas size when saved) so coordinates scale in preview/submission.
- After save, the editor opens the returned preview URL (new tab when allowed).
- Parent virtual card panel attachments and **`_virtual_card_panels`** are not modified by submissions.

**Final review (editor) and submission view (browser)**

- **Final review** (editor) and **submission** pages use a full-page modal with the same layout CSS (`assets/css/frontend-panel-editor.css`).
- Preview uses a **layered** approach: the bottom **`<img>`** uses the **real panel image URL** from WordPress; a transparent **overlay `<img>`** carries only the rendered text/shapes (PNG data URL from Fabric). That keeps the base asset URL visible in devtools while still showing edits on top.
- Shared sizing and overlay rendering live in **`assets/js/frontend-panel-renderer.js`** (`buildPreviewSlides`). Submission-only UI is **`assets/js/frontend-panel-submission.js`** + **`templates/frontend/card-panels-submission.php`**.
- On single **`card_submission`** posts, **`Plugin::append_submission_final_view`** filters **`the_content`** and appends the submission viewer so the final cards show even when the Elementor widget is not on the template (widget output still works when the template includes it).

Use the widget on templates where the main queried post is the desired **`virtual_card`** (single virtual card) or where you intentionally render a **`card_submission`** (submission single).

**Media library: attachment tags (`Attachment_Tags`)**

- Adds a **Tags** field on attachment details **immediately after File URL** (media modal sidebar, **Media → Library**, and when editing an attachment in **post.php**).
- Values are stored in post meta **`_vce_attachment_tags`** as a comma-separated list (max 50 tags, 100 characters each), sanitized on save. JSON payloads from Tagify are normalized the same way.
- **[Tagify](https://github.com/yairEO/tagify)** provides the tag UI; typing triggers debounced **`admin-ajax.php?action=vce_suggest_attachment_tags`** (nonce + `upload_files` capability) to suggest existing tags. Suggestions are built from other attachments’ meta and cached in a short-lived transient.
- Scripts: **`assets/js/admin-attachment-tags.js`** (patches `wp.media.view.Attachment.Details` / `TwoColumn` so Tagify initializes after each render). Styles: **`assets/css/admin-attachment-tags.css`**.

## REST API (plugin)

| Route | Method | Role |
|-------|--------|------|
| **`/wp-json/vce/v1/submission`** | `POST` | Create **`card_submission`**, store **`_vce_submission_layers`**, return links |
| **`/wp-json/vce/v1/debug-client`** | `POST` | Append client log lines when **`VCE_DEBUG`** + admin (see **`Vce_Debug_Rest`**) |

## File layout

| Path | Role |
|------|------|
| `virtual-card-elementor.php` | Bootstrap, constants, helpers, activation hook |
| `includes/class-plugin.php` | Hooks orchestration, Elementor asset registration, submission `the_content` append |
| `includes/class-post-type.php` | CPT + taxonomy registration, classic editor for submissions |
| `includes/class-panel-meta.php` | Meta key constants (panels, submission layers, Wix id, order) |
| `includes/class-editor-access.php` | Who may use the front-end editor (`logged_in` vs `guest` filters) |
| `includes/class-debug-log.php` | Diagnostic logging + debug client asset registration |
| `includes/class-vce-debug-rest.php` | REST **`vce/v1/debug-client`** |
| `includes/class-card-submission-rest.php` | REST **`vce/v1/submission`** |
| `includes/class-template.php` | Template loader |
| `admin/class-panel-meta-box.php` | Card Panels + display order meta boxes, save handlers |
| `admin/class-virtual-card-admin-columns.php` | Virtual Cards list: panels count, WIX ID, category filter |
| `admin/class-card-submission-admin.php` | Submissions list, final view link, parent filter/meta box |
| `admin/class-attachment-tags.php` | Attachment Tags field + AJAX + Tagify enqueue |
| `admin/class-vce-debug-page.php` | **Tools → VCE debug** admin page |
| `elementor/class-card-panels-widget.php` | Elementor widget |
| `templates/admin/panel-meta-box.php` | Admin markup |
| `templates/frontend/card-panels.php` | Frontend panel grid markup |
| `templates/frontend/card-panels-editor.php` | Front-end editor shell |
| `templates/frontend/card-panels-submission.php` | Submission final-view modal (carousel) |
| `assets/css/admin-panel.css` | Admin panel meta styles |
| `assets/css/frontend-panel.css` | Widget / grid styles |
| `assets/css/frontend-panel-editor.css` | Front-end editor + preview/submission modal styles |
| `assets/js/admin-panel.js` | Admin media picker + reorder UI |
| `assets/js/frontend-panel-editor.js` | Front-end editor, final review, submission save |
| `assets/js/frontend-panel-renderer.js` | Shared Fabric helpers (`buildPreviewSlides`, `buildPreviewUrls`) |
| `assets/js/frontend-panel-submission.js` | Submission page carousel viewer |
| `assets/js/vce-debug-client.js` | Optional browser → REST log lines |
| `assets/js/admin-attachment-tags.js` | Tagify + media modal attachment details |
| `assets/css/admin-attachment-tags.css` | Tagify layout in media sidebar |

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
