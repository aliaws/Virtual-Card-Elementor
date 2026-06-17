# Virtual Card Elementor

WordPress plugin that registers a **`virtual_card`** post type (with **categories** taxonomy), stores **Card Panels** (attachment IDs) and optional **display order** / integration meta in post meta, and provides an **Elementor** widget (**Card Panels**) with a Fabric.js **front-end editor**, **final review** preview, and **`card_submission`** save, email, and view-tracking flow.

It also registers **`card_submission`** (nested under the Virtual Cards admin menu) so front-end edits can be stored on a separate post and viewed without modifying the source virtual card or its panel attachments.

Additional features include an admin **Card Gallery**, submission email REST endpoints, subscription-gated access shortcodes, WooCommerce / Ultimate Member profile integration, and Elementor **Display order** sorting for e-card listings.

**Version** is defined in the plugin header in `virtual-card-elementor.php` (also exposed as **`VCE_VERSION`**). Asset URLs use **`vce_asset_version()`** (`VCE_VERSION` + file `mtime`) for cache busting.

## Requirements

- **WordPress** with a working media library (the admin picker uses the core **`wp.media`** modal).
- **Elementor** (the plugin hooks `elementor/widgets/register`). The Elementor widget class file is loaded only when that hook runs, so the base plugin does not fatal if Elementor is inactive.
- **Admin Tagify** loads **[Tagify](https://github.com/yairEO/tagify)** from **jsDelivr** (HTTPS). Restrictive CSPs or offline admin may need to allow that host or bundle assets locally.
- **Virtual Cards list category filter** loads **[Select2](https://select2.org/)** from **jsDelivr**.
- **Front-end editor / preview** loads **[Fabric.js 5.3](https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js)** from **jsDelivr**. Allow **`cdn.jsdelivr.net`** if CSP blocks it.
- **WooCommerce** + **Ultimate Member** (optional): profile and account menu integration (`Profile_Hooks`, `Um_Hooks`).
- **Subscription plugin** exposing global **`$ads_subscription_post`** (optional): required for non-sample virtual card access via `[vce_dynamic_title]` / `vce_can_access_virtual_card()`.

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
- **`Vce_Debug_Page`** (`admin/class-vce-debug-page.php`): **Tools → VCE debug** (`manage_options`) — view log path, clear log. Intended as a temporary support screen.
- **`Vce_Debug_Rest`**: **`POST /wp-json/vce/v1/debug-client`** appends sanitized lines from the browser (admins only, **`VCE_DEBUG`** on). **`assets/js/vce-debug-client.js`** is registered as a dependency of the panel editor script when enabled.

### Custom post type `virtual_card` (`Post_Type` on `init`)

| Argument | Value |
|----------|--------|
| `labels` | Virtual Cards / Virtual Card |
| `public` | `true` |
| `menu_icon` | `dashicons-images-alt2` |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |

### Taxonomy `virtual_card_category` (`Post_Type::register_post_taxonomy` on `init`)

| Argument | Value |
|----------|--------|
| `object_types` | `virtual_card` |
| `hierarchical` | `true` (behaves like categories) |
| `show_admin_column` | `true` (term column on the Virtual Cards list) |
| `show_in_rest` | `true` |
| `public` / `show_ui` | `true` |

### Custom post type `card_submission` (`Post_Type` on `init`)

| Argument | Value |
|----------|--------|
| `public` | `false` |
| `publicly_queryable` | `true` |
| `show_ui` | `true` |
| `show_in_menu` | `edit.php?post_type=virtual_card` (submenu under Virtual Cards) |
| `hierarchical` | `false` (flat permalinks like `/card-submission/slug/`; parent is still stored in `post_parent`) |
| `rewrite` | `slug` = `card-submission` |
| `query_var` | `card_submission` |

### Classic editor for submissions

- Filter **`use_block_editor_for_post_type`**: **`card_submission`** uses the **classic** editor so parent meta boxes POST as expected; other post types are unchanged.

### Shared meta keys (`Panel_Meta`)

| Constant | Meta key | Role |
|----------|----------|------|
| `META_KEY` | **`_virtual_card_panels`** | Ordered attachment IDs for Card Panels |
| `SUBMISSION_LAYERS_META_KEY` | **`_vce_submission_layers`** | Front-end submission layer payload (per panel index) |
| `WIX_META_KEY` | **`_ads_wix_card_id`** | External Wix / sync identifier (string) |
| `ORDER_META_KEY` | **`order`** | Optional integer sort key (`0` clears stored meta) |
| `ORDERBY_DISPLAY_ORDER` | *(query value only)* | Elementor **Order By** slug **`vce_display_order`** (not stored as post meta) |
| `IS_FAVORITE_META_KEY` | **`_vce_is_favorite`** | Favorite flag (`1` = favorite) |
| `FIRST_LEVEL_LABEL_META_KEY` | **`_vce_first_level_label`** | First-level label text |
| `SECOND_LEVEL_LABEL_META_KEY` | **`_vce_second_level_label`** | Second-level label (used by `[vce_dynamic_title]`) |
| `SUBMISSION_SENDER_ID` | **`_vce_sender_id`** | User ID of submission creator |
| `SUBMISSION_RECEIVER_EMAIL` | **`_vce_receiver_email`** | Recipient email |
| `SUBMISSION_SCHEDULED_AT` | **`_vce_scheduled_at`** | Scheduled send datetime (MySQL, site timezone); omitted when sending immediately |
| `SUBMISSION_STATUS` | **`_vce_submission_status`** | `saved`, `scheduled`, `sent`, or `viewed` |
| `SUBMISSION_SENT_COUNT` | **`_vce_sent_count`** | Per-recipient send counts (array meta) |
| `SUBMISSION_VIEWED_COUNT` | **`_vce_viewed_count`** | View count |
| `SUBMISSION_LOG` | **`_vce_submission_log`** | Activity log entries |

### Admin: Card Panels meta box (`Panel_Meta_Box`)

- Hook: `add_meta_boxes`
- Box id: `virtual_card_panels`, title **Card Panels**, screen `virtual_card`, context `normal`, priority `high`
- Reads/writes meta key **`_virtual_card_panels`**: list of attachment IDs when saved.
- Nonce: action `virtual_card_panel_nonce`, field **`virtual_card_panel_nonce_field`**.
- Hidden input **`virtual_card_panel_ids`**: comma-separated attachment IDs; **`assets/js/admin-panel.js`** updates it when adding/removing rows (reorder, preview modal, etc.).
- Enqueues use **`vce_asset_version()`** for **`assets/css/admin-panel.css`** and **`assets/js/admin-panel.js`** (script dependencies include **`jquery-ui-sortable`** and **`media-editor`**).
- Markup: **`templates/admin/panel-meta-box.php`**.

### Admin: Display order meta box (`Panel_Meta_Box`)

- Second meta box id **`virtual_card_display_order`**, title **Display order**, same screen, context **`normal`**, priority **`default`** (below Card Panels).
- Field **`vce_display_order`** (number, min `0`). Nonce action **`vce_display_order_save`**, field **`vce_display_order_nonce_field`**.
- Saved on **`save_post_virtual_card`** (priority **11**) into post meta key **`order`** (`Panel_Meta::ORDER_META_KEY`). **`0`** deletes meta (no custom order).

### Saving panels (`save_post_virtual_card` → `Panel_Meta_Box::save_panels`)

Runs only if the panel nonce is present and verifies. If **`virtual_card_panel_ids`** is non-empty, IDs are sanitized and stored; otherwise meta is deleted. REST-only saves that omit the metabox POST fields do not change `_virtual_card_panels`.

### Virtual Cards admin list (`Virtual_Card_Admin_Columns`)

On **Virtual Cards → All Virtual Cards**:

- **Category filter**: multi-select **`category_id[]`** dropdown (Select2 from jsDelivr). **`parse_query`** applies a **`tax_query`** with **`IN`** when one or more term IDs are selected (`include_children` enabled).
- **Favorite filter**: dropdown **All / Favorites / Not Favorites**; filters by **`_vce_is_favorite`** meta.
- **Extra columns** (inserted after **Title**):

| Column | Meaning |
|--------|---------|
| **No. of panels** | Count of attachment IDs in **`_virtual_card_panels`**. |
| **WIX ID** | Value of **`_ads_wix_card_id`**, or **—** when empty. No dedicated admin field; intended for integrations or external sync. |
| **Favorite** | Star icon when **`_vce_is_favorite`** is `1`. |

The taxonomy also registers **`show_admin_column`** so WordPress adds its own **Categories** column where terms are assigned.

### Admin: Card Gallery (`Virtual_Card_Gallery_Page`)

- Submenu: **Virtual Cards → Card Gallery** (`vce-card-gallery`, capability **`edit_posts`**).
- Grid of all virtual cards with large thumbnails; sorted by **`order`** meta (ascending), then title.
- Inline edit **title** and **display order** via AJAX action **`vce_gallery_update_card`** (`assets/js/admin-gallery.js`, **`assets/css/admin-gallery.css`**).
- Template: **`templates/admin/virtual-card-gallery.php`**.

### Card Labels meta box (`Card_Labels_Meta_Box`)

- Adds **Labels & Status** meta box on Virtual Card edit screen.
- Fields: **Is Favorite** (checkbox), **First Level Label** (text), **Second Level Label** (text).
- Saves to **`_vce_is_favorite`**, **`_vce_first_level_label`**, **`_vce_second_level_label`**.

### Card Submissions admin (`Card_Submission_Admin`)

- List table columns: **Virtual card** (sortable by **`post_parent`**), **Sender** (linked user from **`_vce_sender_id`**, same as meta box), **Receiver Email**, **Status** (colored badge), **Preview Link** (front-end permalink).
- Filters (above **Filter** button): **All statuses** / Saved / Scheduled / Sent / Viewed, and **All E-cards** / parent virtual card (`pre_get_posts` on **`vce_parent_card`** and **`vce_submission_status`**).
- Searchable parent selector meta box on the submission edit screen; saves **`post_parent`** only when parent is type **`virtual_card`**.
- **Send** row action (status **`saved`**) opens modal from **`templates/admin/card-submission-send-modal.php`** → **`POST /wp-json/vce/v1/admin-send-email`** (`assets/js/admin-card-send.js`, **`assets/css/admin-card-submission.css`**).
- Status badges reuse **`templates/admin/partials/submission-status-badge.php`**.

### Card Submissions: tracking meta box (`Card_Submission_Meta_Box`)

- Submission edit screen is **read-only** (classic editor and thumbnail removed; default meta boxes stripped).
- **Submission Tracking** box: sender, receiver, status, view/send stats, activity log from **`_vce_submission_log`**.

### Elementor widget (`Card_Panels_Widget`)

- Hook: `elementor/widgets/register` registers widget from **`elementor/class-card-panels-widget.php`**.
- Frontend style **`vce-frontend-panel`** → `assets/css/frontend-panel.css`; editor/preview also enqueue **`vce-frontend-panel-editor`** and Fabric when needed.
- Templates: **`templates/frontend/card-panels.php`** (grid), **`card-panels-editor.php`** (editor), **`card-panels-submission.php`** (submission carousel).

| Widget | Behavior |
|--------|----------|
| `get_name()` | `card_panels` |
| `get_title()` | **Card Panels** |
| `get_icon()` | `eicon-columns` |
| **Layout** | Columns (1–6), Limit |
| **Style** | Gap / border radius on `.virtual-card-panels` |
| **`render()`** | Uses **`global $post`**, reads **`_virtual_card_panels`**, outputs panel grid, optional **front-end editor**, or **submission** viewer |

When the current post is a **`card_submission`**, the widget resolves panel images from the parent **`virtual_card`** if needed, then applies layer data from **`_vce_submission_layers`**.

Use the widget on templates where the main queried post is the desired **`virtual_card`** or a **`card_submission`** single.

- Editor UI is rendered by **`templates/frontend/card-panels-editor.php`** and powered by **`assets/js/frontend-panel-editor.js`** (depends on **`fabric`**, **`vce-frontend-panel-renderer`**, and optionally **`vce-debug-client`**). Toolbar: font, size, **text color**, preset swatches, **text background** + clear (**Fabric** `textBackgroundColor`, including per-range selection while editing), bold / italic / underline, filmstrip, **Final review**, **Save submission**, **Schedule or Send**.
- **Final review** button is positioned last in the toolbar action group.
- Unsaved in-browser text (before **Save submission**) is **not** persisted across a full page reload; the toolbar may warn on leave when local draft content exists.
- **Saved drafts** are stored as **`card_submission`** posts with status meta **`saved`** (or **`scheduled`**). Layers live in **`_vce_submission_layers`**; the virtual card’s panel attachments are never modified.
- Save endpoint: **`POST /wp-json/vce/v1/submission`** (`Card_Submission_Rest`). JSON body: **`parentId`**, **`layers`**, and optional **`submission_id`**. When **`submission_id`** is `0` or omitted, a new **`card_submission`** is created; otherwise the existing post is updated (layers + **`post_modified`**). Response includes **`id`**, **`preview_url`**, **`url`**, and **`edit_url`**.
- **Schedule or Send** form: **When to send** = **Now** or **Schedule** (shows **`datetime-local`**). **Your Name** defaults to the logged-in user’s display name. **Recipient Email** uses AJAX autocomplete (`admin-ajax.php?action=vce_recipient_emails`) over unique **`_vce_receiver_email`** values from the current user’s past submissions (REST fallback: **`GET /wp-json/vce/v1/recipient-emails`**).
- Dispatch save: same submission endpoint with **`dispatch: true`**, **`recipientEmail`**, **`sendMode`** (`now` | `schedule`), and optional **`scheduledAt`**. **Schedule** sets status **`scheduled`** and stores **`_vce_scheduled_at`**; **Now** does not persist a schedule time (meta is cleared on send). Immediate send still uses **`POST /wp-json/vce/v1/send-email`** after save.
- **`Submission_Scheduler`**: WP-Cron every 5 minutes sends due **`scheduled`** submissions via **`Card_Email_Rest::send_submission_email()`**.
- After each successful save, the plugin stores the post ID in user option **`LAST_DRAFT_SUBMISSION_{user_id}`** so the editor can resume the most recently saved draft. Sending email (**`Card_Email_Rest`**) clears that option for the current user.
- **`_vce_submission_layers`** is a map keyed by panel index (`"0"`, `"1"`, …). Each value holds Fabric **`objects`** plus **`baseW`** / **`baseH`** (editor canvas size when saved) so coordinates scale in preview/submission.
- **Loading a draft in the editor** (`Card_Panels_Widget::render()`):
  1. If the URL has **`?id={submission_id}`** (from **My Submissions → Edit**), that submission is loaded.
  2. Otherwise, if the user has **`LAST_DRAFT_SUBMISSION_{user_id}`**, that submission is loaded.
  3. The widget temporarily treats the submission as the current post so panel images come from the parent **`virtual_card`** and layers from **`_vce_submission_layers`**.
- The front-end editor is shown when the loaded submission’s status is **`saved`** or **`scheduled`** and **`vce_can_use_front_editor()`** is true (not only when the queried post is a **`virtual_card`** with the widget’s “enable front editor” setting). **`submission_id`** is passed to JS as **`vcePanelEditor.submissionApi.submission_id`** so subsequent saves update the same post.
- **My Submissions (WooCommerce My Account**, endpoint **`my-submissions`**, template **`templates/frontend/my-submissions.php`**): logged-in users see a numbered table of their **`card_submission`** posts (by **`_vce_submission_sender_id`**). Columns include **Receiver Email** (`_vce_receiver_email`). Status badges: **Saved**, **Scheduled**, **Sent**, **Viewed**. **Edit** (saved/scheduled only) links to the parent virtual card with **`?id={submission_id}`**. **Preview** links open the submission’s front-end view (sent/viewed).

Set **Query ID** to **`custom_e_cards`** on Posts / Loop Grid / Loop Carousel / Archive Posts / Portfolio widgets that list **`virtual_card`** posts.

**Category filters (front end)**  
Handled by **`Ecard_Category_Filter`** (used by **`custom_e_cards`** and **`[vce_ecard_category_tabs]`**):

- **`vce_category`** (recommended for custom tabs): `?vce_category=birthday` (term **slug**). Comma-separated slugs use **`IN`**. Numeric values in this param are resolved to slugs for backward compatibility.
- **Elementor `e-filter-*`**: still supported, e.g. `?e-filter-…-virtual_card_category=birthday` or `=15`.

Invalid term IDs are ignored. **`include_children`** is enabled for hierarchical categories.

**Sort overrides (URL)**  
`?orderby=` and `?order=` override the widget sort when present (standard WordPress orderby values).

**Display order (widget + URL)**  
- Adds **Display order** to Elementor **Order By** for supported query widgets.
- Choose **Order By → Display order** and **Order → ASC** (lower metabox numbers first) or **DESC**.
- Sorts by post meta **`order`** (`Panel_Meta::ORDER_META_KEY`). Cards without a value appear **last**, then by publish date.
- URL equivalent: `?orderby=vce_display_order&order=ASC` (`Panel_Meta::ORDERBY_DISPLAY_ORDER`).

**Logout**  
`wp_logout` redirects to **`/login/`**.

### Front-end editor and submissions

- Editor UI: **`templates/frontend/card-panels-editor.php`**, **`assets/js/frontend-panel-editor.js`** (depends on **`fabric`**, **`vce-frontend-panel-renderer`**, optionally **`vce-debug-client`**).
- Toolbar: font, size, text color, preset swatches, text background, bold / italic / underline, filmstrip, **Final review**, **Save submission**.
- Unsaved edits are **not** written to the **`virtual_card`** or **`_virtual_card_panels`**; they persist only after **Save submission** creates a **`card_submission`** with **`_vce_submission_layers`**.
- Save: **`POST /wp-json/vce/v1/submission`** (`Card_Submission_Rest`, public — restrict at edge if needed).
- Layer payload: map keyed by panel index (`"0"`, `"1"`, …) with Fabric **`objects`**, **`baseW`**, **`baseH`**.
- After save, opens preview URL in a new tab when allowed.

### Final review and submission view

- Full-page modal layout in **`assets/css/frontend-panel-editor.css`**.
- Layered preview: base **`<img>`** (real panel URL) + overlay PNG from Fabric.
- Shared helpers: **`assets/js/frontend-panel-renderer.js`** (`buildPreviewSlides`).
- Submission carousel: **`assets/js/frontend-panel-submission.js`** + **`templates/frontend/card-panels-submission.php`**.
- **`Plugin::append_submission_final_view`** appends the submission viewer on single **`card_submission`** via **`the_content`** when the widget is not on the template.

### Email (`Card_Email_Rest`)

- **`POST /wp-json/vce/v1/send-email`** — front-end send (public; restrict at edge if needed).
- **`POST /wp-json/vce/v1/admin-send-email`** — admin send from submissions list (`manage_options`).
- **`GET /wp-json/vce/v1/recipient-emails`** — logged-in recipient autocomplete (`?search=`).
- **`wp_ajax_vce_recipient_emails`** — same data for front-end autocomplete (preferred on cached pages).
- Shared send logic: **`Card_Email_Rest::send_submission_email()`** (REST, admin, and scheduled cron).
- HTML template: **`templates/emails/card-email.php`**.
- Updates submission meta (receiver, status **`sent`**, clears **`_vce_scheduled_at`**, send counts) and logs via **`Submission_Logger`**.

### View tracking (`Card_View_Rest`)

- **`POST /wp-json/vce/v1/track-view`** — increments **`_vce_viewed_count`**; sets status from **`sent`** to **`viewed`** when applicable; logs via **`Submission_Logger`**.

### Shortcodes and access control

| Shortcode | Behavior |
|-----------|----------|
| **`[vce_dynamic_title]`** | Renders `<h1>` from **`_vce_second_level_label`** or post title when `vce_can_access_virtual_card()`; otherwise redirects to pricing/login |
| **`[user_account_menu]`** | Login icon or avatar dropdown (`User_Account`, `assets/css/user-account.css`, `assets/js/user-account.js`) |
| **`[vce_ecard_category_tabs]`** | Purple category tabs; filters the loop via **`?vce_category={slug}`** (see below) |

### E-card category tabs (`[vce_ecard_category_tabs]`)

Replaces the Elementor **Taxonomy Filter** on the e-cards page. Tab links use the **term slug** in the URL. The card loop (Query ID **`custom_e_cards`**) reads the same parameter and filters **`virtual_card_category`**.

**Elementor setup**

1. Add a **Shortcode** widget above the loop: `[vce_ecard_category_tabs]`
2. Loop Grid / Posts: post type **`virtual_card`**, Query ID **`custom_e_cards`**
3. **Remove** the Elementor Taxonomy Filter widget (avoids duplicate lists and double filtering)

**Basic shortcode**

```
[vce_ecard_category_tabs]
```

**Exclude categories from the tab list** (cards in those categories are unchanged; only the tab is hidden):

```
[vce_ecard_category_tabs exclude="sample-1,sample-2"]
```

Use **slugs** (recommended), or numeric term IDs:

```
[vce_ecard_category_tabs exclude="12,15"]
[vce_ecard_category_tabs exclude_ids="12,15"]
[vce_ecard_category_tabs exclude="sample-1" exclude_ids="15"]
```

**More examples**

```
[vce_ecard_category_tabs show_all="yes" all_label="All"]
[vce_ecard_category_tabs hide_empty="yes"]
[vce_ecard_category_tabs exclude="sample-1,sample-2" hide_empty="yes"]
[vce_ecard_category_tabs ids="5,8,12"]
[vce_ecard_category_tabs parent="0"]
```

**URL examples**

| URL | Result |
|-----|--------|
| `/e-cards/` | All cards |
| `/e-cards/?vce_category=birthday` | Cards in category slug `birthday` |
| `/e-cards/?vce_category=christmas-new-year` | Cards in that slug |

**Shortcode attributes**

| Attribute | Default | Description |
|-----------|---------|-------------|
| `show_all` | `yes` | Show an **All** tab (clears `vce_category`) |
| `all_label` | `All` | Label for the All tab |
| `parent` | *(empty)* | Only child terms of this parent term ID |
| `ids` | *(empty)* | Comma-separated term IDs to **include** in tabs |
| `exclude` | *(empty)* | Comma-separated **slugs** and/or IDs to **hide** from tabs |
| `exclude_ids` | *(empty)* | Comma-separated term IDs to hide (same as `exclude`) |
| `hide_empty` | `no` | Hide terms with no posts |
| `orderby` | `name` | `get_terms` orderby |
| `order` | `ASC` | `ASC` or `DESC` |
| `base_url` | current page | Permalink used for tab links |

**Related code**

| File | Role |
|------|------|
| `includes/class-ecard-category-tabs.php` | Shortcode markup + CSS enqueue (FOUC-safe) |
| `includes/class-ecard-category-filter.php` | Parses `vce_category` / `e-filter` → `tax_query` |
| `includes/class-um-hooks.php` | Applies filter on **`custom_e_cards`** query |
| `assets/css/ecard-category-tabs.css` | Tab appearance |

**`vce_can_access_virtual_card()`** (`includes/class-shortcodes.php`):

- **Administrators**: always allowed.
- Categories whose slug contains **`sample`**: allowed for everyone.
- Otherwise: logged-in user with active subscription via global **`$ads_subscription_post`**.

**Pricing notice**  
Redirects to **`/pricing/?notice=subscription_required`** (guests to **`/login/`** first). On the **pricing** page, injects a notice before Elementor heading **`.elementor-element-0ab951d`**.

- `woocommerce_account_menu_items`: Removes "edit-account", adds "Account Details" and **"My Submissions"** menu items.
- `woocommerce_get_endpoint_url`: Points Account Details to UM profile page (`/account-details/`).
- `um_profile_permalink`: Changes UM profile link to WooCommerce my-account (`/my-account/`).
- `um_get_option_filter__account_tab_privacy`: Disables Privacy tab in UM account page.

- **Tags** field on attachment details (after File URL) in media modal and attachment edit screen.
- Stored in **`_vce_attachment_tags`** (comma-separated; max 50 tags, 100 chars each).
- **[Tagify](https://github.com/yairEO/tagify)** + **`admin-ajax.php?action=vce_suggest_attachment_tags`** for suggestions (cached transient).
- **`assets/js/admin-attachment-tags.js`**, **`assets/css/admin-attachment-tags.css`**.

### Profile hooks (`Profile_Hooks`)

- WooCommerce **Account Details** → UM **`/account-details/`**; UM profile link → WooCommerce **`/my-account/`**.
- UM profile tabs and edit menu customized; Privacy tab disabled.
- **Flow**: WooCommerce my-account **Account Details** → UM account → **View Profile** → WooCommerce my-account.

## REST API (plugin)

| Route | Method | Permission | Role |
|-------|--------|------------|------|
| **`/wp-json/vce/v1/submission`** | `POST` | Public | Create/update **`card_submission`**, store layers; optional **`dispatch`** + schedule fields |
| **`/wp-json/vce/v1/recipient-emails`** | `GET` | Logged in | Unique recipient emails for current user’s submissions |
| **`/wp-json/vce/v1/send-email`** | `POST` | Public | Send submission email to recipient |
| **`/wp-json/vce/v1/admin-send-email`** | `POST` | `manage_options` | Admin send from submissions list |
| **`/wp-json/vce/v1/track-view`** | `POST` | Public | Increment view count; update status when **`sent`** |
| **`/wp-json/vce/v1/debug-client`** | `POST` | Admin + **`VCE_DEBUG`** | Append browser debug log lines |

Public REST routes should be restricted at the server or edge if the site is exposed to untrusted traffic.

**Submission status meta** (`Panel_Meta::SUBMISSION_STATUS` on **`card_submission`**): **`saved`**, **`scheduled`**, **`sent`**, **`viewed`**. Admin list/meta box and My Submissions use matching labels and colors (including **Scheduled** in **`admin/class-card-submission-meta-box.php`**).

## File layout

| Path | Role |
|------|------|
| `virtual-card-elementor.php` | Bootstrap, constants, helpers, activation hook |
| `includes/class-plugin.php` | Hooks orchestration, Elementor assets, submission `the_content` append |
| `includes/class-post-type.php` | CPT + taxonomy, classic editor for submissions |
| `includes/class-panel-meta.php` | Meta key constants |
| `includes/class-editor-access.php` | Front-end editor access (`logged_in` vs `guest`) |
| `includes/class-template.php` | Template loader |
| `includes/class-debug-log.php` | Diagnostic logging + debug client assets |
| `includes/class-vce-debug-rest.php` | REST **`vce/v1/debug-client`** |
| `includes/class-card-submission-rest.php` | REST **`vce/v1/submission`** (create/update, dispatch schedule) |
| `includes/class-card-email-rest.php` | REST send + recipient emails; shared **`send_submission_email()`** |
| `includes/class-submission-scheduler.php` | WP-Cron: send due scheduled submissions |
| `admin/class-card-submission-meta-box.php` | Submission status display in admin |
| `includes/class-template.php` | Template loader |
| `includes/class-profile-hooks.php` | WooCommerce & UM profile integration hooks |
| `includes/class-user-account.php` | My Submissions WooCommerce endpoint + shortcode |
| `includes/class-um-hooks.php` | Logout redirect, UM/ECard filtering hooks |
| `admin/class-panel-meta-box.php` | Card Panels + display order meta boxes, save handlers |
| `admin/class-card-labels-meta-box.php` | Labels & Status meta box (Favorite, First/Second Level Labels) |
| `admin/class-virtual-card-admin-columns.php` | Virtual Cards list: panels count, WIX ID, category filter, favorite filter |
| `admin/class-card-submission-admin.php` | Submissions list columns, filters, send modal, parent meta box |
| `assets/css/admin-card-submission.css` | Admin submissions list + send modal styles |
| `templates/admin/card-submission-send-modal.php` | Admin send card modal markup |
| `templates/admin/partials/submission-status-badge.php` | Status pill (list + reusable) |
| `admin/class-attachment-tags.php` | Attachment Tags field + AJAX + Tagify enqueue |
| `admin/class-vce-debug-page.php` | **Tools → VCE debug** admin page |
| `elementor/class-card-panels-widget.php` | Elementor widget |
| `templates/admin/panel-meta-box.php` | Admin markup |
| `templates/frontend/card-panels.php` | Frontend panel grid markup |
| `templates/frontend/card-panels-editor.php` | Front-end editor shell |
| `templates/frontend/card-panels-submission.php` | Submission final-view modal (carousel) |
| `templates/frontend/my-submissions.php` | My Submissions table (WooCommerce My Account) |
| `includes/class-card-submission-rest.php` | REST **`vce/v1/submission`** |
| `includes/class-card-email-rest.php` | REST **`vce/v1/send-email`**, **`admin-send-email`** |
| `includes/class-card-view-rest.php` | REST **`vce/v1/track-view`** |
| `includes/class-submission-logger.php` | Submission activity log helper |
| `includes/class-shortcodes.php` | `[vce_dynamic_title]`, access helpers, pricing notice |
| `includes/class-user-account.php` | `[user_account_menu]` shortcode |
| `includes/class-profile-hooks.php` | WooCommerce & UM profile integration |
| `includes/class-ecard-category-filter.php` | Shared category URL parsing + `tax_query` |
| `includes/class-ecard-category-tabs.php` | `[vce_ecard_category_tabs]` shortcode |
| `includes/class-um-hooks.php` | `custom_e_cards` query, display order sort, logout redirect |
| `admin/class-panel-meta-box.php` | Card Panels + display order meta boxes |
| `admin/class-card-labels-meta-box.php` | Labels & Status meta box |
| `admin/class-virtual-card-admin-columns.php` | List columns, category + favorite filters |
| `admin/class-virtual-card-gallery-page.php` | Card Gallery screen + AJAX |
| `admin/class-card-submission-admin.php` | Submissions list, send email, parent picker |
| `admin/class-card-submission-meta-box.php` | Submission tracking (read-only edit) |
| `admin/class-attachment-tags.php` | Attachment tags + AJAX + Tagify |
| `admin/class-vce-debug-page.php` | **Tools → VCE debug** |
| `elementor/class-card-panels-widget.php` | Elementor **Card Panels** widget |
| `templates/admin/panel-meta-box.php` | Card Panels admin markup |
| `templates/admin/virtual-card-gallery.php` | Card Gallery markup |
| `templates/frontend/card-panels.php` | Frontend panel grid |
| `templates/frontend/card-panels-editor.php` | Front-end editor shell |
| `templates/frontend/card-panels-submission.php` | Submission carousel modal |
| `templates/emails/card-email.php` | Submission email HTML |
| `assets/css/admin-panel.css` | Admin panel meta styles |
| `assets/css/admin-gallery.css` | Card Gallery styles |
| `assets/css/admin-attachment-tags.css` | Tagify in media sidebar |
| `assets/css/frontend-panel.css` | Widget / grid styles |
| `assets/css/frontend-panel-editor.css` | Editor + preview/submission modal |
| `assets/css/user-account.css` | Account menu shortcode |
| `assets/css/ecard-category-tabs.css` | E-card category tabs shortcode |
| `assets/js/admin-panel.js` | Admin media picker + reorder |
| `assets/js/admin-gallery.js` | Card Gallery AJAX |
| `assets/js/admin-card-send.js` | Admin send email modal |
| `assets/js/admin-attachment-tags.js` | Tagify + media modal |
| `assets/js/frontend-panel-editor.js` | Front-end editor, save submission |
| `assets/js/frontend-panel-renderer.js` | Shared Fabric preview helpers |
| `assets/js/frontend-panel-submission.js` | Submission carousel viewer |
| `assets/js/user-account.js` | Account menu shortcode |
| `assets/js/vce-debug-client.js` | Browser → REST debug lines |

## Example: query virtual cards in PHP

```php
$query = new WP_Query([
	'post_type'      => 'virtual_card',
	'post_status'    => 'publish',
	'posts_per_page' => 10,
]);
```

Sort by display order meta:

```php
$query = new WP_Query([
	'post_type'  => 'virtual_card',
	'meta_key'   => 'order',
	'orderby'    => 'meta_value_num',
	'order'      => 'ASC',
]);
```

## License

If you distribute this plugin, use a license consistent with WordPress (commonly **GPL-2.0-or-later**). The repository does not ship a `LICENSE` file by default.
