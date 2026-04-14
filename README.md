# Virtual Card Elementor

WordPress plugin that registers a **`virtual_card`** post type, stores **Card Panels** (attachment IDs) in post meta, and provides an **Elementor** widget (**Card Panels**) with a front-end editor and submission flow.

It also registers a child post type **`card_submission`** (under the Virtual Cards menu) so front-end edits can be saved to a separate submission and previewed without modifying the original virtual card.

## Requirements

- **WordPress** with a working media library (the admin picker uses the core **`wp.media`** modal).
- **Admin Tagify** loads **[Tagify](https://github.com/yairEO/tagify)** from **jsDelivr** (HTTPS). Restrictive CSPs or offline admin may need to allow that host or bundle assets locally.
- **Elementor** (the plugin hooks `elementor/widgets/register`). The Elementor widget class file is loaded only when that hook runs, so the base plugin does not fatal if Elementor is inactive.

## Installation

1. Copy this folder into `wp-content/plugins/`.
2. Activate **Virtual Card Elementor** in **Plugins**.
3. Visit **Settings → Permalinks** and click **Save Changes** once so rewrite rules for the new post type are registered.

## Upgrading from older versions

If you used an earlier copy of this plugin that stored images under **`_virtual_card_gallery`** or the Elementor widget id **`virtual_card_gallery`**, re-save each Virtual Card’s **Card Panels** in the editor and replace the old widget with **Card Panels** (`card_panels`) in Elementor templates.

## What the code does

### Bootstrap: `virtual-card-elementor.php`

- Defines **`VCE_VERSION`** from the **`Version`** field in this file’s plugin header via **`get_file_data()`**, so the header stays the single source of truth for the release number.
- Defines path/url constants, text domain, loads PHP class files, runs `Plugin::instance()->run()`.

**Custom post type `virtual_card` (`Post_Type` on `init`)**

| Argument | Value |
|----------|--------|
| `labels` | Virtual Cards / Virtual Card |
| `public` | `true` |
| `menu_icon` | `dashicons-images-alt2` |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |

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

**Admin: Card Panels meta box (`Panel_Meta_Box`)**

- Hook: `add_meta_boxes`
- Box id: `virtual_card_panels`, title **Card Panels**, screen `virtual_card`, context `normal`, priority `high`
- Reads/writes meta key **`_virtual_card_panels`**: list of attachment IDs when saved.
- Nonce: action `virtual_card_panel_nonce`, field **`virtual_card_panel_nonce_field`**.
- Hidden input **`virtual_card_panel_ids`**: comma-separated attachment IDs; **`assets/js/admin-panel.js`** updates it when adding/removing rows.
- Styles: **`assets/css/admin-panel.css`**. Markup: **`templates/admin/panel-meta-box.php`**.

**Saving panels (`save_post_virtual_card` → `Panel_Meta_Box::save_panels`)**

Runs only if the panel nonce is present and verifies. If **`virtual_card_panel_ids`** is non-empty, IDs are sanitized and stored; otherwise meta is deleted. REST-only saves that omit the metabox POST fields do not change `_virtual_card_panels`.

**Virtual Cards admin list (`Virtual_Card_Admin_Columns`)**

On **Virtual Cards → All Virtual Cards**, two columns are inserted after **Title**:

| Column | Meaning |
|--------|---------|
| **No. of cards** | Always **`1`** for each row (one Virtual Card post = one card). |
| **No. of panels** | Count of attachment IDs stored in **`_virtual_card_panels`** for that post. |

**Card Submissions admin (`Card_Submission_Admin`)**

- Adds **Virtual card** column in submissions list.
- Adds **Final view** column with a stable link to the submission’s front-end view (`/?post_type=card_submission&p=ID`).
- Adds parent filter dropdown above the submissions list.
- Adds a parent selector meta box on submission edit screen.
- Saves `post_parent` safely (only allows parent posts of type `virtual_card`).

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

When the current post is a `card_submission`, widget rendering falls back to the parent virtual card panel IDs if needed, then applies submission layer data from **`_vce_submission_layers`**.

**Front-end editor and submissions**

- Editor UI is rendered by `templates/frontend/card-panels-editor.php` and powered by `assets/js/frontend-panel-editor.js`. The toolbar supports **text color** and **text background** (Fabric `textBackgroundColor`, including per-range styles while editing).
- Users can click **Save submission** in the front-end editor.
- Save endpoint: **`POST /wp-json/vce/v1/submission`** (`Card_Submission_Rest`).
- Endpoint creates a new `card_submission`, sets **`post_parent`** to the source virtual card, stores the layer payload in **`_vce_submission_layers`**, and returns **`url`** / **`preview_url`** (query-string style `/?post_type=card_submission&p=ID` so the link works even if permalinks need a flush).
- **`_vce_submission_layers`** is a map keyed by panel index (`"0"`, `"1"`, …). Each value holds Fabric `objects` plus **`baseW`** / **`baseH`** (canvas size when saved) so text positions scale when the preview size changes.
- Editor opens the returned preview link after save.
- Parent virtual card panel attachments and **`_virtual_card_panels`** are not modified by submissions.

**Final review (editor) and submission view (browser)**

- **Final review** (editor) and **submission** pages use a full-page modal with the same layout CSS (`assets/css/frontend-panel-editor.css`).
- Preview uses a **layered** approach: the bottom **`<img>`** uses the **real panel image URL** from WordPress; a transparent **overlay `<img>`** carries only the rendered text/shapes (PNG data URL from Fabric). That keeps the base asset URL visible in devtools while still showing edits on top.
- Shared sizing and overlay rendering live in **`assets/js/frontend-panel-renderer.js`** (`buildPreviewSlides`). Submission-only UI is **`assets/js/frontend-panel-submission.js`** + **`templates/frontend/card-panels-submission.php`**.
- On single **`card_submission`** posts, **`Plugin::append_submission_final_view`** (`the_content`) can inject the submission viewer when the Elementor widget is not present on the template.

Use the widget where the main queried post is the desired `virtual_card` (e.g. single template for that CPT).

**Media library: attachment tags (`Attachment_Tags`)**

- Adds a **Tags** field on attachment details **immediately after File URL** (media modal sidebar, **Media → Library**, and when editing an attachment in **post.php**).
- Values are stored in post meta **`_vce_attachment_tags`** as a comma-separated list (max 50 tags, 100 characters each), sanitized on save. JSON payloads from Tagify are normalized the same way.
- **[Tagify](https://github.com/yairEO/tagify)** provides the tag UI; typing triggers debounced **`admin-ajax.php?action=vce_suggest_attachment_tags`** (nonce + `upload_files` capability) to suggest existing tags. Suggestions are built from other attachments’ meta and cached in a short-lived transient.
- Scripts: **`assets/js/admin-attachment-tags.js`** (patches `wp.media.view.Attachment.Details` / `TwoColumn` so Tagify initializes after each render). Styles: **`assets/css/admin-attachment-tags.css`**.

## File layout

| Path | Role |
|------|------|
| `virtual-card-elementor.php` | Bootstrap |
| `includes/class-plugin.php` | Hooks orchestration |
| `includes/class-post-type.php` | CPT registration |
| `includes/class-panel-meta.php` | Meta key constant |
| `includes/class-card-submission-rest.php` | REST endpoint for saving front-end submissions |
| `includes/class-template.php` | Template loader |
| `admin/class-panel-meta-box.php` | Admin meta box + save + asset enqueue |
| `admin/class-virtual-card-admin-columns.php` | Virtual Cards list table columns |
| `admin/class-card-submission-admin.php` | Card submissions list UI + parent selector/save |
| `admin/class-attachment-tags.php` | Attachment Tags field + AJAX + Tagify enqueue |
| `elementor/class-card-panels-widget.php` | Elementor widget |
| `templates/admin/panel-meta-box.php` | Admin markup |
| `templates/frontend/card-panels.php` | Frontend markup |
| `templates/frontend/card-panels-editor.php` | Front-end editor shell |
| `templates/frontend/card-panels-submission.php` | Submission final-view modal (carousel) |
| `assets/css/admin-panel.css` | Admin styles |
| `assets/css/frontend-panel.css` | Widget styles |
| `assets/css/frontend-panel-editor.css` | Front-end editor + preview/submission modal styles |
| `assets/js/admin-panel.js` | Admin media picker UI |
| `assets/js/frontend-panel-editor.js` | Front-end editor logic + final review + submission save/open |
| `assets/js/frontend-panel-renderer.js` | Shared Fabric preview/slide builder (`buildPreviewSlides`, `buildPreviewUrls`) |
| `assets/js/frontend-panel-submission.js` | Submission page carousel viewer |
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
