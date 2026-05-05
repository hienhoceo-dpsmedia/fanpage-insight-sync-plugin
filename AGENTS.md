# CLAUDE.md - Fanpage Insight Sync Plugin

## Project Overview

A WordPress plugin that:
1. Pulls fanpage data daily from a Google Sheet
2. Sends each new row through an AI image-insight pipeline
3. Stores structured insight JSON in a custom DB table
4. Exposes a `[fanpage_insights]` shortcode with live filter/search UI

---

## Stack

- **Platform:** WordPress (PHP 7.4+)
- **Database:** WordPress custom table via `$wpdb`
- **Scheduler:** WordPress Cron (`wp_schedule_event`)
- **Frontend shortcode:** Vanilla JS + CSS (no jQuery dependency required, but available)
- **AI endpoint:** Configurable via WP Admin settings page (URL, model, prompt, API key)
- **Google Sheet source:** Public CSV export URL derived from the Sheet ID

---

## Google Sheet Sources

- Multiple sheets are supported. Each sheet is configured as a **Sheet Source** in WP Admin.
- **CSV export pattern:** `https://docs.google.com/spreadsheets/d/{SHEET_ID}/export?format=csv&gid={GID}`
- The sheet URL or full spreadsheet URL is entered by the user; the plugin parses out `SHEET_ID` and `GID` automatically.

### Sheet Source Configuration (per sheet, stored as a JSON array in `fpis_sources` option)

```json
[
  {
    "id": "uuid-or-slug",
    "label": "Huu Dai Fanpage List",
    "sheet_id": "1ysbSEX6tlIYsToLB7lXaWM6KUt9U32Tyfp1wQJIGWIo",
    "gid": "1644614207",
    "header_row": 7,
    "column_map": {
      "page_name":    { "header": "TEN FANPAGE",   "unit": null },
      "link_fanpage": { "header": "LINK FANPAGE",  "unit": null },
      "follow":       { "header": "FOLLOW",        "unit": "raw" },
      "thong_tin":    { "header": "THONG TIN",     "unit": null },
      "gioi_tinh":    { "header": "GIOI TINH (%)", "unit": null },
      "ghi_chu":      { "header": "GHI CHU",       "unit": null },
      "tinh_trang":   { "header": "TINH TRANG",    "unit": null }
    },
    "unique_key": "link_fanpage",
    "enabled": true
  }
]
```

### Field Definitions

| Internal key | Role | Notes |
|---|---|---|
| `page_name` | Display name | Required |
| `link_fanpage` | **Unique row identifier** | Required. Trimmed, lowercased for dedup comparison |
| `follow` | Follower/member count | Optional. Raw value normalized to integer (see Number Normalization) |
| `thong_tin` | Insight image URL (needs OG resolve) | Optional. Skip AI if empty |
| `gioi_tinh` | Raw gender string e.g. `Nu 80`, `58% nu` | Optional. Parsed with regex |
| `ghi_chu` | Notes | Optional |
| `tinh_trang` | Status (`con`, `doi ten`, ...) | Optional |
| `is_hidden` | Row visibility | 1 = hidden from frontend (if removed from sheet), 0 = visible |

---

## Number Normalization

The `follow` column (and any quantity column) appears in multiple raw formats depending on the sheet. Always normalize to a plain integer before storing or using in price calculation.

### Observed formats

| Raw value | Column header | Normalized | How |
|---|---|---|---|
| `316.000` | `FOLLOW` | 316,000 | Dots are thousands separators — strip dots |
| `204.000` | `FOLLOW` | 204,000 | Same |
| `37,8` | `Member (K)` | 37,800 | K unit declared in header — comma is decimal: 37.8 × 1000 |
| `301,7` | `Member (K)` | 301,700 | Same: 301.7 × 1000 |
| `525,4` | `Member (K)` | 525,400 | Same: 525.4 × 1000 |
| `38` | `Member (K)` | 38,000 | K unit, whole number: 38 × 1000 |
| `15` | `Member (K)` | 15,000 | Same |

**The K unit is declared in the column header itself (`Member (K)`), not inferred from the value.** When the user maps the `Follow Count` field to a column whose header contains `(K)`, the plugin should auto-suggest `unit: thousands`. The user can override this in the UI.

### Per-column unit config

Each column mapping can declare a `unit` so the normalizer knows how to scale:

```json
"column_map": {
  "follow": { "header": "FOLLOW",      "unit": "raw" },
  "follow": { "header": "Member (K)",  "unit": "thousands" }
}
```

| Unit value | Meaning | Multiply by |
|---|---|---|
| `raw` | Already full number (dots are thousands seps, commas are thousands seps) | 1 |
| `thousands` | Value is in thousands — commas are decimal points | 1,000 |

### Normalization algorithm

```php
function fpis_normalize_follow( string $raw, string $unit = 'raw' ): int {
    $raw = trim( $raw );

    if ( $unit === 'thousands' ) {
        // "37,8" -> 37.8 -> 37800
        // "301,7" -> 301.7 -> 301700
        // "38" -> 38 -> 38000
        $float = (float) str_replace( ',', '.', $raw );
        return (int) round( $float * 1000 );
    }

    // unit = 'raw'
    // "316.000" -> strip dots -> 316000
    // "102.000" -> 102000
    // Handle ambiguous: if string has exactly one dot and digits after suggest
    // thousands (3 digits after dot), treat dot as thousands sep
    if ( preg_match( '/^[\d.]+$/', $raw ) ) {
        // Remove dots used as thousands separators
        return (int) str_replace( '.', '', $raw );
    }

    // Fallback: strip everything non-numeric
    return (int) preg_replace( '/[^\d]/', '', $raw );
}
```

### DB storage

Always store the **normalized integer** in `follow_count` (BIGINT). Also store the original raw string in `follow_raw` (VARCHAR 32) for audit/debug.

Add `follow_raw VARCHAR(32) DEFAULT NULL` to the `fpis_insights` table schema.

---

## Pricing Tiers

Each Sheet Source has an independent pricing config. Prices are calculated from the normalized `follow_count` and displayed in the shortcode table and admin view.

### Pricing config (per source, stored inside the source object in `fpis_sources`)

```json
"pricing": {
  "currency": "VND",
  "multiplier": 1.0,
  "tiers": [
    { "min": 0,       "max": 4999,   "base": 500000,  "per_1k": 50000  },
    { "min": 5000,    "max": 9999,   "base": 1000000, "per_1k": 45000  },
    { "min": 10000,   "max": 49999,  "base": 2000000, "per_1k": 40000  },
    { "min": 50000,   "max": 99999,  "base": 5000000, "per_1k": 35000  },
    { "min": 100000,  "max": null,   "base": 8000000, "per_1k": 30000  }
  ]
}

### Currency Multiplier
Used for non-VND displays (ZH/EN). The multiplier is applied to the calculated base/per_1k prices.
Example: Multiplier `0.00004` converts `500,000 VND` to roughly `$20`.
```

### Pricing formula

```
price = tier.base + floor(follow_count / 1000) * tier.per_1k
```

Where the tier is selected by finding the first entry where `follow_count >= tier.min` and (`tier.max === null` OR `follow_count <= tier.max`).

### PHP implementation

```php
function fpis_calculate_price( int $follow_count, array $pricing ): ?int {
    if ( empty( $pricing['tiers'] ) ) return null;
    foreach ( $pricing['tiers'] as $tier ) {
        $in_range = $follow_count >= $tier['min']
            && ( $tier['max'] === null || $follow_count <= $tier['max'] );
        if ( $in_range ) {
            return (int) ( $tier['base'] + floor( $follow_count / 1000 ) * $tier['per_1k'] );
        }
    }
    return null;
}
```

### DB storage

Store the calculated price in `calculated_price` (BIGINT) and the tier snapshot in `price_tier_json` (TEXT) so repricing does not require re-fetching.

Add to schema:
```sql
calculated_price  BIGINT   DEFAULT NULL,
price_tier_json   TEXT     DEFAULT NULL,
follow_raw        VARCHAR(32) DEFAULT NULL,
```

### Pricing UI in WP Admin

- Each Sheet Source edit screen has a **Pricing Tiers** section
- User can add/remove tiers with fields: Min followers, Max followers (blank = unlimited), Base price, Price per 1K followers
- Currency selector (default VND)
- A live preview shows: given a sample follower count input, what price is calculated
- Prices are recalculated and stored on every sync.
- **Global Recalculate:** If tiers are updated, a background job triggers `fpis_recalculate_all_prices()`.

### Shortcode display

When `show_price="true"` is passed to `[fanpage_insights]`, a **Giá ước tính** (Estimated Price) column is shown, formatted with thousands separator appropriate for the currency (dot for VND: `1.500.000 ₫`).

### Header Row Selection

- The user specifies which row number (1-based) contains the column headers for each sheet.
- The plugin skips all rows above `header_row` when parsing.
- Rows between row 1 and `header_row - 1` are ignored (they may contain announcements, merged cells, etc.).
- Data rows begin at `header_row + 1`.

### Column Mapping UI (WP Admin)

When a user adds or edits a Sheet Source:
1. They paste the Google Sheet URL (any format: `/edit`, `/export`, `/view` - plugin extracts `sheet_id` and `gid`)
2. They enter the header row number (default: 1)
3. Plugin fetches the CSV, reads that row, and presents a dropdown per internal field:
   - `Page Name`, `Link Fanpage (unique key)`, `Follow Count`, `Thong Tin (image URL)`, `Gioi Tinh`, `Ghi Chu`, `Tinh Trang`
4. User maps each internal field to the matching column header from the dropdown (or "-- skip --")
5. For the `Follow Count` field, an additional **Unit** selector appears: `Raw number` (e.g. FOLLOW column with dot-separated thousands) or `Thousands/K` (e.g. Member (K) column where 37,8 means 37,800)
6. `link_fanpage` mapping is required; all others are optional
7. Saved as `column_map` in the source config (each mapping is an object with `header` and `unit`)

### Sync Fail-safes
- **Header Validation:** On every sync, the plugin verifies that all mapped headers still exist at the specified `header_row`. If a header is missing, the sync for that source is aborted, and a "Mapping Error" is displayed in WP Admin.
- **Deduplication:** The `link_fanpage` is the global unique key. If a link already exists in the database, subsequent sources containing the same link are **ignored** to prevent data corruption between sheets.

### Sheet ID / GID Extraction

```php
function fpis_parse_sheet_url( string $url ): array {
    // Extract sheet_id from /d/{id}/ segment
    preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $sid );
    // Extract gid from #gid= or &gid=
    preg_match( '#[#&?]gid=(\d+)#', $url, $gid );
    return [
        'sheet_id' => $sid[1] ?? null,
        'gid'      => $gid[1] ?? '0',
    ];
}
```

---

## Image URL Resolution

The `THONG TIN` column contains a shortlink or redirect URL (postimg.cc, imgur.com, prnt.sc, etc.).
Before sending to AI, resolve it to a direct image URL using OG tag scraping:

```php
function fpis_resolve_image_url( string $page_url ): ?string {
    $response = wp_remote_get( $page_url, [
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'timeout'    => 15,
    ]);
    if ( is_wp_error( $response ) ) return null;
    $html = wp_remote_retrieve_body( $response );
    if ( preg_match(
        '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
        $html, $m
    ) || preg_match(
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
        $html, $m
    )) {
        return strtok( $m[1], '?' ); // strip query string
    }
    return null;
}
```

---

## AI Insight Pipeline

### Settings (configurable in WP Admin)

| Setting key | Default | Description |
|---|---|---|
| `fpis_ai_endpoint` | (required) | Full URL of the AI API endpoint |
| `fpis_ai_model` | `claude-sonnet-4-20250514` | Model identifier |
| `fpis_ai_api_key` | (required) | Bearer token / API key |
| `fpis_ai_prompt` | (see below) | System/user prompt template |
| `fpis_sources` | `[]` | JSON array of Sheet Source configs (see Google Sheet Sources section) |

### Default Prompt Template

The prompt must instruct the AI to return **only** a JSON object. Variables `{image_url}` are replaced at runtime.

```
You are a media analysis expert. Analyze the Facebook page insight image at: {image_url}

Return ONLY a single JSON object with this schema (no markdown, no explanation):
{
  "platform": "facebook",
  "total_followers": number | null,
  "gender": { "male_pct": number | null, "female_pct": number | null, "dominant": "male|female|balanced" },
  "age": {
    "top_group": "18-24|25-34|35-44|...",
    "distribution": { "13-17": number, "18-24": number, "25-34": number, "35-44": number, "45-54": number, "55-64": number, "65+": number },
    "generation": ["GenZ","Millennials","GenX","Boomer"]
  },
  "location": {
    "top_cities": [{ "name": string, "value": number, "unit": "percent|count" }],
    "top_countries": [{ "name": string, "value": number, "unit": "percent|count" }],
    "region_focus": "south|north|central|nationwide|international",
    "has_international": boolean
  },
  "fit": {
    "suitable_industries": [string],
    "unsuitable_industries": [string],
    "tags": [string]
  },
  "description": string
}
```

### Queue Processing

- New rows detected during daily sync are added to an `fpis_queue` option (serialized array of row data)
- A separate WP Cron job (`fpis_process_queue`, every 5 minutes) picks up to 5 items per run
- Each item: resolve image URL -> call AI -> parse JSON -> upsert into `fpis_insights` table
- On parse failure: log error to `ai_error` column, increment `ai_attempts`.
- **Skip Logic:** Rows with empty `thong_tin` OR `ai_attempts >= 3` are marked as `ai_status = 'skipped'` and ignored by the cron (no auto re-analyze).
- **Manual Overwrite:** If an admin manually edits a row, sync will NOT overwrite `description`, `suitable_json`, `unsuitable_json`, or `tags_json` unless "Force AI Sync" is checked.

---

## Database Schema

### Table: `{prefix}fpis_insights`

```sql
CREATE TABLE {prefix}fpis_insights (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  link_fanpage   VARCHAR(512)    NOT NULL,
  page_name      VARCHAR(255)    DEFAULT NULL,
  follow_count   BIGINT          DEFAULT NULL,
  follow_raw     VARCHAR(32)     DEFAULT NULL,
  calculated_price BIGINT        DEFAULT NULL,
  price_tier_json  TEXT          DEFAULT NULL,
  platform       VARCHAR(32)     DEFAULT 'facebook',
  raw_gender     VARCHAR(64)     DEFAULT NULL,
  male_pct       FLOAT           DEFAULT NULL,
  female_pct     FLOAT           DEFAULT NULL,
  dominant       VARCHAR(16)     DEFAULT NULL,
  age_top_group  VARCHAR(16)     DEFAULT NULL,
  age_dist_json  LONGTEXT        DEFAULT NULL,
  generation     VARCHAR(128)    DEFAULT NULL,
  top_cities_json    LONGTEXT    DEFAULT NULL,
  top_countries_json LONGTEXT    DEFAULT NULL,
  region_focus   VARCHAR(32)     DEFAULT NULL,
  has_international  TINYINT(1)  DEFAULT 0,
  suitable_json      LONGTEXT    DEFAULT NULL,
  unsuitable_json    LONGTEXT    DEFAULT NULL,
  tags_json          LONGTEXT    DEFAULT NULL,
  description        LONGTEXT    DEFAULT NULL,
  ghi_chu        TEXT            DEFAULT NULL,
  tinh_trang     VARCHAR(64)     DEFAULT NULL,
  thong_tin_url  VARCHAR(512)    DEFAULT NULL,
  resolved_image_url VARCHAR(512) DEFAULT NULL,
  ai_raw_response    LONGTEXT    DEFAULT NULL,
  ai_error           TEXT        DEFAULT NULL,
  ai_attempts    TINYINT         DEFAULT 0,
  ai_status      VARCHAR(16)     DEFAULT 'pending',
  is_hidden      TINYINT(1)      DEFAULT 0,
  is_manual_edit TINYINT(1)      DEFAULT 0,
  synced_at      DATETIME        DEFAULT NULL,
  analyzed_at    DATETIME        DEFAULT NULL,
  created_at     DATETIME        DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_link_fanpage (link_fanpage(255)),
  KEY idx_dominant (dominant),
  KEY idx_region (region_focus),
  KEY idx_follow (follow_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Plugin File Structure

```
wp-content/plugins/fanpage-insight-sync/
├── fanpage-insight-sync.php       # Main plugin file, register hooks
├── CLAUDE.md                      # This file
├── includes/
│   ├── class-fpis-activator.php   # DB table creation + auto page on activation
│   ├── class-fpis-sync.php        # Google Sheet CSV fetch + diff logic (new + update)
│   ├── class-fpis-queue.php       # Queue management (add / process / retry / manual re-trigger)
│   ├── class-fpis-ai.php          # Image resolve + AI API call + JSON parse
│   ├── class-fpis-db.php          # Upsert and query helpers for wpdb
│   └── class-fpis-shortcode.php   # [fanpage_insights] shortcode + REST endpoints
├── admin/
│   ├── class-fpis-admin.php       # Settings page + source manager + re-analyze actions
│   └── views/
│       ├── settings-page.php      # Global settings (AI endpoint, Zalo, etc.)
│       ├── sources-page.php       # Sheet sources list + add/edit form
│       └── insights-page.php      # Admin table with per-row re-analyze + bulk actions
└── assets/
    ├── fpis-frontend.css
    └── fpis-frontend.js           # Filter/search/pagination logic
```

---

## Shortcode: `[fanpage_insights]`

### Attributes

| Attribute | Default | Description |
|---|---|---|
| `per_page` | `20` | Rows per page |
| `show_filters` | `true` | Show filter bar |
| `source` | (all) | Limit to a specific sheet source ID |
| `columns` | (role-based) | Override visible columns — respects role visibility caps |

### Filter/Search Features

Users can filter and search by:
- Free text search (page name, link)
- Dominant gender (Tất cả / Nữ / Nam / Balanced)
- Region focus (Tất cả / south / north / central / nationwide)
- Age top group (dropdown)
- Follower range (min / max inputs)
- Tags (multi-select or text input)
- Tình trạng / status

Filters available to all roles. Results returned are already role-filtered at the REST layer.

### REST Endpoints

`GET /wp-json/fpis/v1/pages`
Query params: `search`, `dominant`, `region`, `age_group`, `follow_min`, `follow_max`, `tags`, `status`, `source`, `page`, `per_page`
Returns role-filtered fields only.
**Caching:** Responses are cached via WP Transients for 1 hour (cleared on sync completion).

`GET /wp-json/fpis/v1/pages/{id}`
Returns full detail for one row. Role-filtered. Used by the detail modal.

```json
{ "total": 120, "pages": 6, "data": [ { ...row... } ] }
```

---

## Role Visibility

The shortcode renders different columns depending on the current user's role. Role is checked server-side in the REST endpoint — never trust client-side role claims.

| Column | Public (logged-out) | Logged-in (non-admin) | Admin (`manage_options`) |
|---|---|---|---|
| Tên trang | Yes | Yes | Yes |
| Link | Yes | Yes | Yes |
| Follow count | Yes | Yes | Yes |
| Region / Khu vực | Yes | Yes | Yes |
| Giới tính (gender %) | No | Yes | Yes |
| Độ tuổi chính (age group) | No | Yes | Yes |
| Tags | No | Yes | Yes |
| Tình trạng (status) | No | Yes | Yes |
| Giá ước tính (price) | No | Yes | Yes |
| AI description | No | No | Yes |
| Ghi chú (notes) | No | No | Yes |
| Suitable industries | No | No | Yes |
| AI analysis status | No | No | Yes |
| Re-analyze button | No | No | Yes |

The REST endpoint at `/wp-json/fpis/v1/pages` checks `is_user_logged_in()` and `current_user_can('manage_options')` and strips fields from the response accordingly. Never return restricted fields to lower roles even if requested via query params.

---

## Detail Modal

When a logged-in user (or admin) clicks a row in the table, a full-screen modal opens without a page reload.

### Modal sections

1. **Header** — page name, link (external), follow count, tình trạng badge, Zalo contact button
2. **Audience Overview** — gender split bar, age range display (e.g. "Chủ yếu 18-34"), dominant generation badge
3. **Geographic Reach** — top cities list with values, region focus badge, international flag if `has_international`
4. **AI Description** — the `description` field rendered as plain paragraphs
5. **Brand Fit** — suitable industries (green tags), unsuitable industries (red tags)
6. **Admin-only footer** — raw `thong_tin_url`, `resolved_image_url` (clickable), `analyzed_at`, `ai_attempts`, Re-analyze button

### Implementation

- Modal HTML is injected once into the page footer by the shortcode
- JS populates it from the REST endpoint: `GET /wp-json/fpis/v1/pages/{id}`
- Charts use pure CSS bar charts or a lightweight JS chart (no Chart.js dependency — keep bundle small)
- Public visitors clicking a row see nothing (row click is disabled for logged-out users)

---

## Zalo Contact Button

A fixed Zalo / phone number is configured globally in plugin settings (not per row, not per sheet).

### Settings

| Key | Description |
|---|---|
| `fpis_contact_zalo` | Zalo link | `https://zalo.me/2660820902015185905` |
| `fpis_contact_label` | Button label (default: `Liên hệ Zalo`) |
| `fpis_contact_show_public` | Whether public visitors can see button |

### Rendering

- Button appears in both the table row (last column) and the detail modal header
- Link format: `https://zalo.me/{phone}` or the raw URL if already a full URL
- Opens in a new tab (`target="_blank"`)
- Only shown to roles that match `fpis_contact_show_public` setting; always shown to logged-in users

---

## AI Endpoint Configuration

All AI settings are global (shared across all sheet sources) and stored in WP options.

| Option key | Description | Example |
|---|---|---|
| `fpis_ai_endpoint` | Full API URL | `https://api.anthropic.com/v1/messages` |
| `fpis_ai_model` | Model identifier | `claude-opus-4-6` |
| `fpis_ai_api_key` | API key — stored encrypted, never exposed to frontend | `sk-ant-...` |
| `fpis_ai_prompt` | Prompt template — `{image_url}` is replaced at runtime | See default below |
| `fpis_ai_max_tokens` | Max tokens for response (default: `1000`) | `1000` |
| `fpis_ai_timeout` | HTTP timeout in seconds (default: `30`) | `30` |

The API key is sent as: `Authorization: Bearer {fpis_ai_api_key}` on every AI request.

The request body format follows the Anthropic Messages API convention but since the endpoint is configurable, the plugin sends a generic JSON body:

```json
{
  "model": "{fpis_ai_model}",
  "max_tokens": 1000,
  "messages": [
    { "role": "user", "content": "{prompt_with_image_url_substituted}" }
  ]
}
```

The admin settings page includes a **Test Connection** button that sends a minimal request (`"Say OK"`) to verify the endpoint and API key are working before any real analysis runs.

---

## Auto-Created Page on Activation

On plugin activation (`register_activation_hook`), the plugin checks if a WP page with slug `fanpage-insights` already exists. If not, it creates one with:

```php
[
  'post_title'   => 'Danh sách Fanpage',
  'post_content' => '[fanpage_insights]',
  'post_status'  => 'publish',
  'post_type'    => 'page',
  'post_name'    => 'fanpage-insights',
]
```

The page ID is stored in `fpis_frontend_page_id` option. On deactivation, the page is NOT deleted (user may have customized it). A link to the page is shown in the plugin admin settings for easy access.

---



| Hook | Interval | Job |
|---|---|---|
| `fpis_daily_sync` | daily (02:00 server time) | Fetch Sheet CSV, detect new rows, enqueue them |
| `fpis_process_queue` | every 5 minutes | Process up to 5 queued rows through AI pipeline |

---

## Coding Rules

- All strings going into the DB must use `$wpdb->prepare()` - no raw interpolation
- Nonces required on all AJAX / REST write actions
- Capability check `manage_options` for all admin pages
- Use `wp_remote_get` / `wp_remote_post` - never `curl` directly
- Log errors with `error_log( '[FPIS] ' . $message )` - never `var_dump` in production code
- Use `wp_json_encode` / `json_decode( $str, true )` consistently
- All text domain: `fanpage-insight-sync`
- No global variables - use singleton `FPIS_Plugin::instance()`
- Table creation uses `dbDelta()` in activator - not raw `CREATE TABLE IF NOT EXISTS`
- Always sanitize sheet data: `sanitize_text_field()`, `absint()`, `floatval()`
- Never hardcode sheet IDs, GIDs, or column names - always read from the `fpis_sources` option
- Column access must always go through the `column_map` for that source - never by positional index

---

## Key Data Flow

```
[WP Cron daily]
     |
     v
fpis_daily_sync()
  -> load all enabled sources from fpis_sources option
  -> for each source:
       -> fetch CSV from Google Sheet export URL (sheet_id + gid)
       -> skip rows above header_row
       -> read header row, build column index map via source column_map
       -> parse data rows using mapped column positions
        -> normalize unique key (link_fanpage): trim + lowercase
        -> check if link exists in DB:
             - IF EXISTS and `source_id` matches: proceed to UPDATE
             - IF EXISTS and `source_id` differs: IGNORE (secondary source duplicate)
             - IF NOT EXISTS: proceed to NEW
        -> NEW rows     -> add to fpis_queue option array (tagged with source id)
       -> EXISTING rows (matched by link_fanpage) ->
            UPDATE: follow_count, follow_raw, tinh_trang, ghi_chu, synced_at, calculated_price
            PRESERVE: all ai_* fields, analyzed_at, resolved_image_url, ai_raw_response
            DO NOT re-queue for AI unless admin explicitly triggers re-analysis
  -> for rows NO LONGER in CSV: mark as `is_hidden = 1`
     |
     v
[WP Cron every 5 min]
fpis_process_queue()
  -> pop up to 5 items from queue
  -> for each item:
       1. fpis_resolve_image_url( THONG_TIN_url )
       2. POST to AI endpoint with resolved image URL + prompt
            - endpoint URL, model, API key read from plugin settings
            - API key sent as Bearer token in Authorization header
       3. Strip markdown fences, parse JSON from AI response
       4. fpis_db_upsert( link_fanpage, parsed_data )
  -> items that fail: increment ai_attempts, re-queue if ai_attempts < 3
  -> items that exceed 3 attempts: mark as failed, available for manual re-trigger
     |
     v
[Admin triggers re-analysis]
  -> single row: "Re-analyze" button in admin row actions -> push 1 item to fpis_queue, reset ai_attempts to 0
  -> bulk: "Bulk Re-analyze" button in admin list -> push selected rows to fpis_queue, reset ai_attempts
     |
     v
[User visits page with shortcode]
[fanpage_insights]
  -> detect role: admin / logged-in / public
  -> render filter bar + table with role-appropriate columns (see Role Visibility)
  -> JS fetches /wp-json/fpis/v1/pages with current filter state + nonce
  -> renders paginated results with Zalo contact button per row
  -> row click -> opens detail modal with full AI insight (logged-in and admin only)
  -> filter changes -> debounced re-fetch
```

---

## Notes for AI Coding Agent

- When writing `class-fpis-sync.php`: iterate over all enabled sources in `fpis_sources`. For each source, fetch its CSV, skip rows above `header_row`, use row at `header_row` as headers, map columns via `column_map`. Never assume column order or position.
- The `header_row` is 1-based. Row index in the parsed CSV array is `header_row - 1` (0-based).
- The `THONG TIN` column value may be empty for some rows - skip AI analysis for those rows but still store the base row data.
- The `FOLLOW` column value uses `.` as thousands separator (e.g. `316.000`) - parse as integer by stripping non-numeric chars.
- The `GIOI TINH (%)` column contains strings like `Nu 80`, `Nam 85`, `58% nu` - parse with regex, not assumptions.
- AI response may wrap JSON in markdown fences (` ```json `) even if instructed not to - always strip before `json_decode`.
- All Vietnamese text in DB and UI must be stored and rendered as UTF-8 with full diacritics.
