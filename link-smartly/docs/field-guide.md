# Link Smartly — Field Guide

A plain-language reference for every setting and input in the plugin.
If a field has a **Recommended** value, use that unless you have a specific reason not to.

---

## Keywords Tab

### Add New Keyword Mapping

| Field | What It Does | Recommended | Example |
|-------|-------------|-------------|---------|
| **Keyword Phrase** *(required)* | The exact word or phrase the plugin will look for inside your posts. Matching is case-insensitive, so "Contact Us" and "contact us" are treated the same. | Pick natural phrases your readers would encounter. Avoid single generic words like "the" or "or". | `contact us`, `pricing plans`, `free trial` |
| **Target URL** *(required)* | The page this keyword will link to. Use a relative path for your own site or a full URL for external sites. | Copy the URL directly from your browser address bar. For your own site, use relative paths (start with `/`). | `/contact/`, `/services/web-design/`, `https://example.com/page/` |
| **Group** *(optional)* | A label to organize your keywords. Groups only appear in the admin to help you filter — visitors never see them. | Leave empty if you have fewer than 20 keywords. Use groups like "Navigation", "Products", "Blog" for larger lists. | `Navigation`, `Products`, `Seasonal` |
| **Synonyms** *(optional)* | Other phrases that mean the same thing and should link to the same target URL. Separate with commas. | Only add synonyms you are sure appear in your content. Don't add synonyms "just in case". | `reach out, get in touch` (for keyword "contact us") |
| **Max Uses** *(optional)* | A lifetime limit on how many times this keyword can be auto-linked across all your posts combined. `0` means unlimited. | Leave at `0` (unlimited) for most keywords. Only set a limit for promotional keywords you want to cap. | `0` (unlimited), `50` (limit to 50 posts) |
| **Nofollow** *(optional)* | Controls whether search engines should follow this link. "Use global setting" inherits from the Settings tab. | **Always leave on "Use global setting"** unless you specifically know you need this one keyword to behave differently. | `Use global setting (recommended)` |
| **New Tab** *(optional)* | Controls whether clicking this link opens a new browser tab. "Use global setting" inherits from the Settings tab. | **Always leave on "Use global setting"** unless this specific keyword links to an external site. | `Use global setting (recommended)` |
| **Schedule — From / Until** *(optional)* | Makes this keyword active only during a specific date range. Outside the range, the keyword is ignored. | **Leave both empty** for permanent keywords. Only set dates for time-limited campaigns (holiday sales, events, etc.). | From: `2026-11-25` Until: `2026-12-02` (Black Friday week) |

### Quick-Start: Minimum Required

To add a keyword, you only need two fields:

1. **Keyword Phrase** — the text to match
2. **Target URL** — where it should link

Everything else is optional and has safe defaults. You can always come back and edit later.

---

## Settings Tab

### Enable Auto-Linking

| Setting | What It Does | Default | Recommendation |
|---------|-------------|---------|----------------|
| **Enable Auto-Linking** | Master on/off switch. When off, no links are added anywhere, but all your keywords and settings are preserved. | ✅ On | Leave on. If you need to pause temporarily (e.g., while redesigning), uncheck and re-check later. |

### Max Links Per Post

| Setting | What It Does | Default | Recommendation |
|---------|-------------|---------|----------------|
| **Max Links Per Post** | The maximum number of auto-links the plugin will insert into a single post. Even if 10 keywords match, only this many will become links. | `3` | **3** for posts under 1,000 words. **5** for long-form content (2,000+ words). Never go above 10 — it looks spammy. |

### Minimum Content Words

| Setting | What It Does | Default | Recommendation |
|---------|-------------|---------|----------------|
| **Minimum Content Words** | Posts shorter than this word count are skipped entirely — no auto-links will be added. | `300` | **300** is a safe default. Set to `0` only if you want very short posts to get links too (not recommended). |

### Post Types

| Setting | What It Does | Default | Recommendation |
|---------|-------------|---------|----------------|
| **Post Types** | Which content types get auto-links. Checkboxes appear for every public post type on your site. | Posts ✅, Pages ✅ | Check **Posts** and **Pages**. Only check custom post types (like "Products") if you want auto-links there too. |

### Link CSS Class

| Setting | What It Does | Default | Recommendation |
|---------|-------------|---------|----------------|
| **Link CSS Class** | A CSS class name added to every auto-generated link tag. Developers use this for custom styling or click tracking. | `lsm-auto-link` | **Leave the default** unless your developer tells you to change it. Changing this has no visual effect unless you also write custom CSS. |

### Link Attributes

| Setting | What It Does | Default | Recommendation |
|---------|-------------|---------|----------------|
| **Add title attribute** | Shows a small tooltip when visitors hover over a link. Helps accessibility (screen readers). | ✅ On | **Leave on.** It helps accessibility at zero cost. |
| **Add rel="nofollow"** | Tells search engines NOT to pass SEO value (link equity) through these links. | ❌ Off | **⚠️ Keep this OFF for internal links.** The entire purpose of internal linking is to pass SEO value between your pages. Turning this on defeats that purpose. Only enable if ALL your keywords link to external sites. |
| **Open in new tab** | Makes clicks open a new browser tab instead of navigating away from the current page. | ❌ Off | **Keep OFF for internal links.** Opening internal links in new tabs annoys readers and creates tab clutter. Only enable if most keywords link to external sites. |

---

## Analytics Tab

This tab is read-only — there are no settings to configure. It shows:

| Card | What It Shows |
|------|--------------|
| **Total Keywords** | How many keyword mappings you have defined. |
| **Active Keywords** | How many are currently enabled (not paused or expired). |
| **Total Links Inserted** | Cumulative count of auto-links added across all posts since you installed the plugin. |
| **Posts With Links** | Number of unique posts that contain at least one auto-link. |

### Buttons

| Button | What It Does | When to Use |
|--------|-------------|-------------|
| **Scan All Posts** | Re-scans all published posts to count where each keyword appears. Useful after adding many new keywords. | After bulk-importing new keywords, or if counts look outdated. |
| **Reset All Counts** | Sets all link counts back to zero. Does NOT remove keywords or actual links from posts. | Only if you want a fresh start on analytics (e.g., after a major site restructure). |

---

## Import / Export Tab

### Export

Downloads all your keywords as a `.csv` file you can open in Excel or Google Sheets. Use this to:

- **Back up** your keywords before making bulk changes
- **Share** keyword lists between sites
- **Edit in a spreadsheet** and re-import

### Import

Upload a `.csv` file to add keywords in bulk.

**Required CSV columns:** `keyword`, `url`

**Optional CSV columns:**

| Column | Values | Default if Missing |
|--------|--------|-------------------|
| `active` | `1` (active) or `0` (inactive) | `1` (active) |
| `group` | Any text label | Empty (no group) |
| `synonyms` | Comma-separated phrases | Empty |
| `nofollow` | `default`, `yes`, or `no` | `default` |
| `new_tab` | `default`, `yes`, or `no` | `default` |
| `max_uses` | A number, `0` = unlimited | `0` (unlimited) |
| `start_date` | Date in `YYYY-MM-DD` format | Empty (always active) |
| `end_date` | Date in `YYYY-MM-DD` format | Empty (never expires) |

**Import Mode:**

| Mode | What Happens | Safe? |
|------|-------------|-------|
| **Append** *(default)* | Adds imported keywords to your existing list. Nothing is deleted. | ✅ Yes |
| **Replace** | Deletes ALL existing keywords first, then imports the file. | ⚠️ Destructive — export a backup first! |

### Example CSV

```csv
keyword,url,group,active
contact us,/contact/,Navigation,1
our services,/services/,Navigation,1
pricing plans,/pricing/,Conversion,1
free trial,/free-trial/,Conversion,1
```

---

## Preview Tab

| Field | What It Does |
|-------|-------------|
| **Choose a post** | Pick any published post or page from the dropdown. |
| **Preview Links** button | Runs a dry-run simulation showing which keywords would be linked and where. No changes are saved to your post. |

Use this to verify your keywords work as expected before going live. It's especially helpful after importing a large batch of new keywords.

---

## Post Editor: Exclude This Post

When editing a post or page, you'll see a **Link Smartly** checkbox in the sidebar:

| Setting | What It Does |
|---------|-------------|
| **Disable auto-linking on this post** | When checked, this specific post will never get any auto-links, regardless of keyword matches. |

Use this for posts where auto-links would be inappropriate (e.g., legal pages, landing pages with strict formatting).

---

## Safe Defaults Summary

For users who just want it to work without thinking about SEO settings:

| Setting | Safe Default | Why |
|---------|-------------|-----|
| Enable Auto-Linking | ✅ On | Plugin doesn't do anything when off |
| Max Links Per Post | 3 | Natural link density for average posts |
| Minimum Content Words | 300 | Avoids cluttering short posts |
| Post Types | Posts + Pages | Covers most sites |
| Link CSS Class | `lsm-auto-link` | No visible effect, useful for developers |
| Title Attribute | ✅ On | Improves accessibility |
| Nofollow | ❌ Off | **Critical: keep off for internal links** |
| New Tab | ❌ Off | Internal links should stay in same tab |
| Per-keyword Nofollow | Use global setting | Inherits from above |
| Per-keyword New Tab | Use global setting | Inherits from above |
| Max Uses | 0 (unlimited) | No reason to limit most keywords |
| Schedule | Empty (always active) | Most keywords don't expire |
| Import Mode | Append | Safe, doesn't delete anything |
