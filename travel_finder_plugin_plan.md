# Travel Finder — WordPress plugin plan

A plugin for mamanvoyage.com that lets a visitor filter your many travel posts to find the trip best suited to their plans. Filters are weighted 0/1/2; posts are scored by summing the weights of the selected filters and ranked.

**Decisions captured from your answers**

- Languages: **build FR-only data now, but the schema and code are designed for per-language (Polylang) from day one** — adding EN/DE later is data entry, not a rebuild.
- Match logic: **exclude zero-score posts.** Only posts matching at least one selected filter are shown, ranked by score. With no filter selected, show the top 16 by views.
- The 156 `post_id`s in your file are the **French (default-language)** posts.
- Proposed plugin slug/prefix: `travel-finder` / `tvf_`.

The data: 6 categories — Intérêt (10 filters), Saison (4), durée (3), budget (3), age enfants (3), geographie (6) = **29 filters**. Values 0/1/2 (one blank cell will be imported as 0).

---

## 1. Data structure

### Recommendation: one custom table + a code-defined filter registry

Two layers, separating *what the filters are* (stable, rarely changes) from *each post's weights* (the data you maintain).

**a) Filter & category definitions → PHP registry (in the plugin), not the DB.**
The 29 filters and their 6 categories almost never change, and their labels need translating (FR/EN/DE). A PHP array is version-controlled, fast, and plays naturally with Polylang's string translation. Shape:

```php
// includes/filters-registry.php
return [
  'interet' => [
    'label'   => __('Intérêt', 'travel-finder'),
    'order'   => 1,
    'filters' => [
      'plage_cote'   => __('Plage & côte', 'travel-finder'),
      'nature_rando' => __('Nature & randonnée', 'travel-finder'),
      'gastronomie'  => __('Gastronomie', 'travel-finder'),
      // …10 total
    ],
  ],
  'saison'  => [ 'label' => __('Saison','travel-finder'), 'order'=>2, 'filters'=>[ 'hiver'=>…, 'printemps'=>…, 'ete'=>…, 'automne'=>… ] ],
  'duree'   => [ … '2_3_jours', 'semaine', 'plus' ],
  'budget'  => [ … 'economique', 'medium', 'eleve' ],
  'age_enfants' => [ … 'bebes', 'kids', 'ados' ],
  'geographie'  => [ … 'france','angleterre','mediterranee','europe','sans_decalage','plus_loin' ],
];
```

Each filter has a stable string `slug` (used in URLs and DB) and a translatable `label`. The category carries the section grouping you flagged (row 1 of your sheet) and a display order.

**b) Per-post weights → one custom table.**

```sql
CREATE TABLE wp_tvf_post_filter (
  post_id     BIGINT UNSIGNED NOT NULL,   -- the WP post id (FR now; EN/DE later)
  lang        VARCHAR(8)      NOT NULL,   -- 'fr' | 'en' | 'de' (from Polylang)
  filter_slug VARCHAR(64)     NOT NULL,   -- e.g. 'nature_rando'
  weight      TINYINT UNSIGNED NOT NULL,  -- 0 | 1 | 2
  PRIMARY KEY (post_id, filter_slug),
  KEY idx_lang_filter (lang, filter_slug),
  KEY idx_lang_weight (lang, weight)
) ENGINE=InnoDB;
```

Why this design:

- **Per-language ready.** In Polylang each translation is its own post with its own `post_id`. Today we insert rows only for the FR ids. Later, the EN and DE posts get their own rows keyed by their own ids. The `lang` column is filled from Polylang at write time so the visitor query can restrict to the current language with a single indexed condition — exactly your eventual "per language" mode, no migration needed.
- **Why a custom table, not post_meta or a taxonomy.** A WP taxonomy can't store a 0/1/2 weight, and you can't `ORDER BY SUM(weight)` over taxonomy terms. Post_meta could hold the numbers but scoring across 29 keys per post is awkward and slow. The custom table makes the scoring query trivial and fast. Size is tiny: 156 × 29 ≈ 4.5k rows now, ~13.5k with all three languages.

**The scoring query** (the heart of the visitor page):

```sql
SELECT pf.post_id, SUM(pf.weight) AS score, CAST(pm.meta_value AS UNSIGNED) AS views
FROM wp_tvf_post_filter pf
JOIN wp_posts p       ON p.ID = pf.post_id AND p.post_status = 'publish'
LEFT JOIN wp_postmeta pm ON pm.post_id = pf.post_id AND pm.meta_key = %s   -- views meta key
WHERE pf.lang = %s                      -- current language
  AND pf.filter_slug IN (…selected…)    -- the chosen filters
  AND pf.weight > 0
GROUP BY pf.post_id
HAVING score > 0                        -- exclude zero-score posts
ORDER BY score DESC, views DESC
LIMIT 16;
```

With no filters selected, skip the score logic and return the top 16 published posts in the current language by views.

> **Views meta key: `views`** (confirmed). The secondary sort uses `wp_postmeta.meta_key = 'views'`, cast to an unsigned integer; posts with no views row sort last.

**Alternative (if you'd rather manage the filter list from the admin without code):** replace the PHP registry with two small tables, `wp_tvf_category` and `wp_tvf_filter`, plus a label-per-language table. More flexible, slightly more plumbing. Given your 29 filters are stable, I recommend the registry — but it's a one-line decision either way.

### Bulk import (so you don't re-enter 156 posts)

A one-time importer populates `wp_tvf_post_filter` for all FR posts and validates that every `post_id` exists, is published, and is French, reporting any that don't resolve.

**Expected CSV**:

- UTF-8 encoding, comma delimiter (UTF-8 matters for accented labels).
- The two header rows and the leading sequential `id` column can stay — columns are mapped **by position**, not header text.
- `post_id` column = the French WordPress post IDs.
- The 29 weight columns must remain in their current order (Intérêt 10 → Saison 4 → durée 3 → budget 3 → age enfants 3 → geographie 6). The one rule: **don't reorder or delete columns.**
- Values 0/1/2; blank = 0.

---

## 2. Backend (admin) page

Your two-row template/copy idea is good for fast data entry. I'd keep it and add a few things that save real time.

### Primary screen — "Travel Finder → Edit weights"

A single page under its own admin menu, with:

1. **Language selector** (top): FR / EN / DE. Defaults to FR. (Only FR has data now; the control is there so per-language editing later needs no new UI.)

2. **Template row (source):** a searchable post picker (Select2 / autocomplete by title — necessary with 150+ posts). Picking one loads its current weights into the grid below as a read-only reference column.

3. **Target row (the post being edited):** a second searchable picker, **prepopulated with the most recently modified published travel post**, with a badge showing whether it already has weights ("complete" / "empty / N missing").

4. **The weights grid:** all 29 filters grouped under their 6 category headings. Each filter is a compact **3-state segmented control `[0 | 1 | 2]`** (clear, one click, no typing). The reference column shows the template post's values side by side.

5. **Buttons:** **Copy from template** (fills the target column from the source — editable before saving), **Reset**, and **Save** (AJAX, no page reload, nonce + capability checked).

### Additions I recommend

- **A "post editor" meta box (the ergonomic win).** Add the same 29-filter grid as a meta box on the travel post edit screen, so you set/adjust weights while writing or updating a post — with a small "copy weights from another post" picker inside it. Most edits will happen here naturally; the dedicated batch page above is for catching up / bulk work. The two write to the same table.
- **Coverage dashboard.** A list of published posts that have **no weights yet** (and posts with partial data), so you always know what's left. This is the single most useful thing for getting to 100% coverage.
- **Validation & safety.** Weights constrained to 0/1/2; AJAX save with nonce; capability `edit_posts` (or `manage_options` if you want it admin-only).

This keeps your template→copy→edit→save flow exactly as you described, adds inline editing where you actually work, and gives you a clear "what's missing" view. If you prefer a single big matrix (posts × filters, inline-edit every cell), I can offer that as a secondary "power" screen — but the template/copy + meta box combo is faster for day-to-day upkeep.

---

## 3. Visitor UI

Delivered as a **shortcode** `[travel_finder]` you drop on a page. Structure, top to bottom:

- **Title** (TBD — placeholder, easily editable) and a short **intro paragraph** explaining the goal.
- **Live summary line:** "Votre sélection : Nature & randonnée, été, France" — updates as filters change; empty-state text when nothing is selected.
- **Filter area, two rows as you specified:**
  - **Row 1:** the 10 *Intérêt* filters.
  - **Row 2:** the remaining sections — *Saison, durée, budget, age enfants, geographie* — each as a labelled group.
  - Each filter is a toggle (checkbox styled as a chip/pill, `aria-pressed`), so it's keyboard- and screen-reader-accessible.
- **Results grid:** up to **16 cards**, ordered by **score desc, then views desc**, matching the mamanvoyage card style (featured image, title overlay). A result count and a "Réinitialiser" (reset) button. A friendly "no match" state.

### SEO / works-without-JS (progressive enhancement)

This is the important architectural choice and it's very doable:

- **Server-side rendering is the baseline.** The shortcode reads the selected filters from the URL query string (e.g. `?f=nature_rando,ete,france`), runs the scoring query in PHP, and renders the filters + the 16 cards as plain HTML. So the page is fully formed for crawlers and for users with JS disabled.
- **No-JS path:** each filter is a real link/`<form>`. Clicking it changes the query string and the browser does a **full reload**; the server re-renders the filtered list. Everything works with zero JavaScript.
- **JS path (progressive enhancement):** a small script intercepts the clicks, updates the selection and summary line, fetches new results from a REST endpoint (`/wp-json/tvf/v1/results?f=…` returning the rendered cards), swaps the results container, and updates the URL with `history.pushState` — **no full reload**. If JS fails, the links still work.
- **Crawl hygiene:** the base page (no filters) is the canonical, indexable URL. Filtered combinations carry `rel=canonical` back to the base and a `noindex` robots hint, so Google indexes the page once instead of crawling thousands of filter permutations (which would be duplicate-content noise). The individual travel posts remain the real SEO targets — this page is a discovery tool that funnels to them. Clean, shareable URLs (`?f=…` with readable slugs) mean a visitor can bookmark or share a specific filtered view.

### Design, and the `/frontend-design` skill

Use the built-in `/frontend-design` skill if available

### Mobile

Yes, it works on mobile, and I'll design it to. The reference site is already responsive. Specifics: the two filter rows **wrap** (or become horizontally scrollable chip strips) instead of overflowing; the 16-card grid uses CSS `grid` with `auto-fill`, so it's 4 columns on desktop, ~2 on tablet, 1 on phone; the summary line and reset stay pinned and legible. You're designing desktop-first, which is fine — the responsive rules are inexpensive to add at the same time.

---

## Cross-cutting

- **Performance:** the scoring query is light and indexed. I'll cache rendered results in a transient keyed by `lang + filter-combo`, busted whenever weights are saved — so repeated/common filter combinations are instant.
- **Security:** prepared statements throughout; nonces + capability checks on all admin and REST writes; output escaping on the frontend.
- **i18n / Polylang:** all UI strings translatable; filter/category labels registered for Polylang string translation; the visitor query already filters by current language. FR ships now; EN/DE is data entry when you're ready.
- **Proposed file layout:**

```
mavo-travel-finder/
  mavo-travel-finder.php            # bootstrap, activation (creates table), menu
  includes/
    filters-registry.php       # the 6 categories + 29 filters
    class-tvf-store.php         # DB read/write, scoring query
    class-tvf-admin.php         # batch page + meta box + AJAX save
    class-tvf-importer.php      # CSV/ODS → table
    class-tvf-frontend.php      # shortcode, REST endpoint, SSR
  assets/
    admin.css / admin.js
    frontend.css / frontend.js
```

---

## Decisions locked

- Views meta key: **`views`**.
- Page title & intro: **placeholder text** for now (easily editable later).
- Filter list: **code registry** (the 6 categories / 29 filters live in the plugin, not the DB).
- Import: **CSV** format as specified above.
- Cards: **featured image + title only** (mamanvoyage style), no filter tags.

