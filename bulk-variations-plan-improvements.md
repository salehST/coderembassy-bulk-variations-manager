# Bulk Variations Plan — Review & Admin UI Revamp

> Companion to `bulk-variations-plan.md`.
> Reviewer: audit against `../admin-dashboard-pattern/` and general plugin-engineering best practice.
> Scope: call out what's missing / weak in the plan, then rewrite Section 8 (Admin UI) so it actually follows the starter pattern.

---

## Part A — Overall Review of `bulk-variations-plan.md`

The plan is strong on architecture, data layer, background processing and competitive positioning. Where it is thin: product surface, admin UX, compliance, quality engineering, and alignment with the `admin-dashboard-pattern` starter.

### A1. Missing sections / items (by topic)

**Admin UX / product surface**
- No unified admin shell. Pages (Bulk Editor, Jobs, Settings) are listed as separate React mount points instead of one app with sidebar + topbar, which is what the starter pattern was built for.
- No Dashboard / home screen (totals, recent jobs, quick links, pending AI suggestions).
- No onboarding wizard (activation -> connect WooCommerce -> pick first product -> try generator).
- No empty states, skeleton loaders, or toast/notice system specified.
- No in-app Help panel, tours, or contextual documentation links.
- No Templates UI even though `bv_templates` table exists.
- No upgrade/upsell UI pattern (lock icons, tooltip, paywall modal) beyond the marketing modal copy.
- No keyboard shortcuts map (Save, Undo/Redo, Find, Bulk apply, Jump cell) despite the spreadsheet being the hero feature.
- No dark mode / theme tokens — the starter pattern ships one, the plan ignores it.
- No brand assets plan (logo light/dark, favicon, admin menu icon).

**Accessibility & i18n**
- No WCAG 2.1 AA commitment, no focus-ring / contrast tokens, no aria patterns for AG Grid (AG Grid has known a11y gaps that need bridging).
- No RTL pass.
- No explicit text-domain / `load_plugin_textdomain` / .pot generation step. (Text domain is referenced but not specified.)
- No WPML / Polylang compatibility item in MVP for the storefront grid.

**Storefront**
- Shortcode only. Missing a Gutenberg block and a Cart/Checkout Block (Blocks API) compatibility statement — required to pass the WooCommerce "Compatible with Blocks" badge.
- No FSE theme compatibility note.
- No AJAX add-to-cart fallback when WC cart is in Blocks mode (the cart update event names differ).

**Data, privacy, compliance**
- No retention / purge job for `bv_ai_log` and `bv_job_changes` (auto-prune after N days — configurable).
- No "Delete all plugin data" toggle on uninstall (required by WP.org review in many cases).
- No privacy policy content provided via `wp_add_privacy_policy_content` (GDPR).
- No Personal Data Exporter / Eraser hooks (because AI log can contain merchant-entered prompts).
- No consent record for AI calls (who ticked the box, when, IP hash).
- No rate-limit on `/ai/suggest` per-user/per-site — plan says "quota per site" but doesn't define numbers or lockout.
- No audit log for destructive actions (rollback, delete variation, force complete job).

**Performance & safety**
- No pre-job DB snapshot option for very large jobs (rollback via deltas is correct, but merchants expect "backup before run").
- No row-count guardrail (confirm modal if > 1,000 rows affected, extra confirm if > 10,000).
- No concurrency lock — two admins running bulk edits on the same product at the same time will race.
- No backpressure if Action Scheduler queue is already deep (check pending count before enqueuing).
- No memory cap test in the testing plan (just wall-clock).
- No "pause / resume / cancel running job" spec — this is table-stakes for a 5-minute job.

**Quality engineering**
- No CI plan (GitHub Actions matrix: PHP 7.4/8.0/8.1/8.2/8.3 × WP 6.x × WC 7.x/8.x/9.x).
- No static analysis config committed: PHPStan level, PHPCS ruleset (WPCS + WooCommerce Sniffs).
- No E2E plan (Playwright against `wp-env`) — critical for AG Grid regressions.
- No load/perf harness script (seed 10k variations, assert timings).
- No error tracking hook (Sentry / custom logger) — "error_log" column is good, but no surfacing.
- No release checklist (tag, readme.txt bump, assets, zip, WP.org svn).
- No semver / changelog convention.
- No CODEOWNERS / branching model / PR template.

**Distribution & support**
- No WP.org readme.txt spec (stable tag, tested up to, tags, screenshots, upgrade notice section).
- No screenshots plan / marketing asset list.
- No "migrate from Barn2 / VBULKiT" importer — big conversion lever.
- No in-plugin changelog viewer.
- No diagnostic export (System Info) for support tickets.
- No Freemius "opt-in" / "skip" flow specified (required UX).

**AI specifics**
- No model pinning strategy (what happens when `gpt-4o-mini` is deprecated).
- No fallback prompt if the model returns invalid JSON (one repair attempt, then fail).
- No token/cost estimator shown to the merchant before running.
- No BYO-provider examples (Anthropic, local Ollama) even though `AiAdapterInterface` is provider-agnostic — one extra adapter stub would sell the interface.
- No "AI off" hard kill switch at plugin-level (for agencies that forbid outbound calls).

### A2. Weaknesses in what is already written

- **Section 8 is underbuilt.** ~75 lines for the flagship surface vs 300+ lines for the SQL. The UI ASCII is fun but doesn't specify layout, tokens, or components.
- **Section 4 directory layout** uses `admin/src/` and `admin/build/`, but the rest of the plan's Prompt 6 says `admin/src/index.jsx` -> `admin/build`. The starter pattern uses `assets/admin/src/` -> `assets/admin/dist/`. Pick one; the starter is the cleaner convention because it matches WordPress's `assets/` idiom.
- **Mount point ID is inconsistent.** Prompt 6 says `#bv-spreadsheet-root`, but a multi-page app should mount once into `#bv-admin-root` and route internally. This is exactly what the starter pattern demonstrates.
- **Section 6 has a bug.** `BulkEditor::bulkUpdate()` calls `WC_Product_Variable::sync_with_children(/* affected parent IDs */)` with a comment placeholder — that's pseudocode, not working code. The prompt pack will ship the bug to Cursor. Replace with explicit "collect parent IDs, loop sync per parent".
- **Section 7 REST API** lists routes but no request/response schemas beyond one example, no `args` validators, no error shape, no pagination contract on `GET /jobs`, no `ETag`/polling guidance.
- **Section 10 Performance** notes `wc_update_product_lookup_tables()` but that function doesn't exist with that name across all WC versions; the correct public API varies by version — spec needs a version-aware helper.
- **Section 11 Licensing** ignores the Freemius "Pro-only plugin" vs "freemium single codebase" split. The plan says single codebase (good) but doesn't say how Pro code is stripped (Freemius `__premium_only` markers) — that's the exact detail Cursor needs.
- **Section 12 Prompt Pack** is great but assumes Cursor will wire things up. No integration prompt — nothing says "now register everything in `ServiceProvider::register()` and smoke-test". Add a PROMPT 11 — Wire & Smoke Test.
- **Section 15 Testing Plan** has unit + integration but no E2E, no a11y, no visual regression.
- **Section 13 Roadmap** is ambitious but week 6 "Free release" with "basic admin page" will fail WP.org review if the UI isn't polished. Re-sequence: polish admin shell in week 4, spreadsheet in week 5.

### A3. Recommended additions (prioritised)

1. Unified admin shell using `admin-dashboard-pattern` (see Part B).
2. Gutenberg block + Blocks cart compatibility for the storefront grid.
3. Retention/purge cron for jobs, changes, AI log, with setting.
4. "Migrate from Barn2 / VBULKiT" importer — drives conversions.
5. CI matrix, PHPStan, PHPCS, Playwright E2E — add as Section 16 and Prompt 11.
6. Privacy + uninstall cleanup + data export/erase hooks — Section 17.
7. Job controls: pause / resume / cancel, plus pre-flight row-count confirm and concurrency lock.
8. AI cost estimator + provider-agnostic second adapter (Anthropic stub).
9. Accessibility + i18n + RTL checklist — Section 18.
10. Release engineering: readme.txt template, assets list, diagnostic export — Section 19.

---

## Part B — Revised Section 8: Admin UI Specification

This replaces the current §8 end-to-end. It aligns with `admin-dashboard-pattern/` and turns the admin into one cohesive React app instead of three disconnected pages.

### 8.0 Design Principles

1. **One app, many views.** A single React root (`#bv-admin-root`) renders Topbar + Sidebar + view area. The Spreadsheet, Jobs, Templates, AI Assistant, and Settings are views inside this app, not separate pages.
2. **Design tokens only.** All colour, spacing and radius come from CSS custom properties declared on the root. Two themes — light + dark — shipped on day one.
3. **WordPress-native feel, plugin-branded.** Use `--wp-admin-theme-color` as the secondary accent; use the plugin's indigo (`--bv-primary`) as the primary. Respect user's admin colour scheme where possible.
4. **Every Pro feature fails gracefully.** In Free, Pro features are visible but locked with a lock icon + upgrade tooltip — never hidden.
5. **Every destructive action is two-step.** Pre-flight modal with row count + rollback note.
6. **No spinner-of-doom.** Every async surface has skeletons (list) or progress bars (job).
7. **Accessible by default.** Keyboard-reachable, focus-visible, aria-live for progress, WCAG 2.1 AA colour contrast in both themes.

### 8.1 File Layout (aligns with `admin-dashboard-pattern/`)

```text
bulk-variations-add-edit-product/
├── src/Admin/
│   ├── AdminPage.php               # Registers menu, enqueues assets, localises globals
│   ├── RestBootstrapper.php        # Passes rest_url + nonce to JS
│   └── Notices.php                 # Admin notice helpers
│
├── assets/admin/
│   ├── admin.css                   # Design tokens + shell styles (light + dark)
│   ├── logo-light.png
│   ├── logo-dark.png
│   ├── dist/index.js               # Build output (gitignored)
│   └── src/
│       ├── index.js                # Mount + hydrate globals
│       ├── store/
│       │   ├── index.js            # @wordpress/data store: ui + globals + jobs cache
│       │   └── selectors.js
│       ├── api/
│       │   ├── client.js           # apiFetch wrapper with nonce + error toast
│       │   └── endpoints.js        # Typed helpers for /bv/v1/*
│       ├── components/
│       │   ├── App.jsx             # Shell: Topbar + Sidebar + Router
│       │   ├── Topbar.jsx          # Logo swap + user + dark toggle + help
│       │   ├── Sidebar.jsx         # Nav items with active state + pro-lock
│       │   ├── Router.jsx          # Switches views from store.getActiveView()
│       │   ├── views/
│       │   │   ├── Dashboard.jsx
│       │   │   ├── BulkEditor/
│       │   │   │   ├── index.jsx         # AG Grid wrapper
│       │   │   │   ├── Toolbar.jsx       # Bulk actions + formula input
│       │   │   │   ├── ColumnPicker.jsx  # Show/hide columns (persists)
│       │   │   │   └── UndoRedo.jsx
│       │   │   ├── Jobs/
│       │   │   │   ├── index.jsx
│       │   │   │   ├── JobRow.jsx
│       │   │   │   └── RollbackDrawer.jsx
│       │   │   ├── Templates/
│       │   │   │   └── index.jsx
│       │   │   ├── AiAssistant/
│       │   │   │   ├── index.jsx
│       │   │   │   └── tabs/{Generate,SuggestPrices,CleanImport,Summarize}.jsx
│       │   │   ├── Settings/
│       │   │   │   ├── index.jsx
│       │   │   │   ├── General.jsx
│       │   │   │   ├── AiProvider.jsx
│       │   │   │   ├── Retention.jsx
│       │   │   │   └── Diagnostics.jsx
│       │   │   └── Help.jsx
│       │   └── shared/
│       │       ├── Toggle.jsx
│       │       ├── Button.jsx
│       │       ├── Card.jsx
│       │       ├── Badge.jsx            # status pills: queued/running/complete/failed/rolled_back
│       │       ├── ProgressBar.jsx
│       │       ├── EmptyState.jsx
│       │       ├── Skeleton.jsx
│       │       ├── Toast.jsx + ToastProvider.jsx
│       │       ├── ConfirmModal.jsx
│       │       ├── ProLock.jsx          # wraps children, locks if !is_pro
│       │       └── KbdShortcut.jsx
│       └── hooks/
│           ├── useJobsPolling.js
│           ├── useShortcut.js
│           └── useTheme.js             # persists dark mode to user meta
```

### 8.2 PHP Surface — `AdminPage.php`

Mirror the starter's `AdminPage.php` exactly, renaming constants and handle:

```php
<?php
namespace BulkVariations\Admin;

class AdminPage {
    private string $menu_slug = 'bulk-variations';

    public function register_menu(): void {
        add_menu_page(
            __( 'Bulk Variations', 'bulk-variations' ),
            __( 'Bulk Variations', 'bulk-variations' ),
            'manage_woocommerce',
            $this->menu_slug,
            array( $this, 'render_page' ),
            'dashicons-screenoptions', // replace with custom SVG via admin_head
            56
        );

        // Submenu items deep-link into React routes but all mount the same app.
        add_submenu_page( $this->menu_slug, __( 'Dashboard',    'bulk-variations' ), __( 'Dashboard',    'bulk-variations' ), 'manage_woocommerce', $this->menu_slug . '#/dashboard',  '' );
        add_submenu_page( $this->menu_slug, __( 'Bulk Editor',  'bulk-variations' ), __( 'Bulk Editor',  'bulk-variations' ), 'manage_woocommerce', $this->menu_slug . '#/editor',     '' );
        add_submenu_page( $this->menu_slug, __( 'Jobs',         'bulk-variations' ), __( 'Jobs',         'bulk-variations' ), 'manage_woocommerce', $this->menu_slug . '#/jobs',       '' );
        add_submenu_page( $this->menu_slug, __( 'Templates',    'bulk-variations' ), __( 'Templates',    'bulk-variations' ), 'manage_woocommerce', $this->menu_slug . '#/templates',  '' );
        add_submenu_page( $this->menu_slug, __( 'AI Assistant', 'bulk-variations' ), __( 'AI Assistant', 'bulk-variations' ), 'manage_woocommerce', $this->menu_slug . '#/ai',         '' );
        add_submenu_page( $this->menu_slug, __( 'Settings',     'bulk-variations' ), __( 'Settings',     'bulk-variations' ), 'manage_woocommerce', $this->menu_slug . '#/settings',   '' );
    }

    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, $this->menu_slug ) ) return;

        wp_enqueue_style(  'bv-admin', BV_PLUGIN_URL . 'assets/admin/admin.css', array(), BV_VERSION );
        wp_enqueue_script( 'bv-admin', BV_PLUGIN_URL . 'assets/admin/dist/index.js',
            array( 'wp-element', 'wp-i18n', 'wp-data', 'wp-api-fetch', 'wp-components' ),
            BV_VERSION, true
        );
        wp_set_script_translations( 'bv-admin', 'bulk-variations', BV_PLUGIN_PATH . 'languages' );

        $user = wp_get_current_user();
        wp_localize_script( 'bv-admin', 'BulkVariationsAdmin', array(
            'rest_url'    => rest_url( 'bv/v1/' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'version'     => BV_VERSION,
            'is_pro'      => (bool) bv_freemius()->can_use_premium_code(),
            'capabilities'=> array(
                'manage' => current_user_can( 'manage_woocommerce' ),
            ),
            'current_user'=> array(
                'display_name' => $user->display_name,
                'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 48 ) ),
            ),
            'logo_light'  => BV_PLUGIN_URL . 'assets/admin/logo-light.png',
            'logo_dark'   => BV_PLUGIN_URL . 'assets/admin/logo-dark.png',
            'currency'    => array(
                'symbol'   => get_woocommerce_currency_symbol(),
                'position' => get_option( 'woocommerce_currency_pos' ),
                'decimals' => wc_get_price_decimals(),
            ),
            'dateFormat'  => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
            'initial_theme' => get_user_meta( $user->ID, 'bv_admin_theme', true ) ?: 'auto',
        ) );
    }

    public function render_page(): void {
        echo '<div class="wrap"><div id="bv-admin-root" aria-busy="true"></div></div>';
    }
}
```

### 8.3 Design Tokens — `assets/admin/admin.css`

Extend the starter's token set; add semantic, status and spacing tokens. Both themes below pass WCAG AA for body text.

```css
#bv-admin-root, #bv-admin-root * { box-sizing: border-box; }

#bv-admin-root {
    /* Neutrals */
    --bv-bg:       #f5f7fb;
    --bv-surface:  #ffffff;
    --bv-surface-2:#f1f4fb;
    --bv-text:     #0f172a;
    --bv-muted:    #475569;
    --bv-border:   #e2e8f0;
    --bv-border-strong:#cbd5e1;

    /* Brand + accents */
    --bv-primary:      #4f46e5;
    --bv-primary-ink:  #ffffff;
    --bv-primary-soft: rgba(79,70,229,.10);
    --bv-focus-ring:   0 0 0 3px rgba(79,70,229,.35);

    /* Status */
    --bv-ok:     #16a34a;
    --bv-warn:   #d97706;
    --bv-err:    #dc2626;
    --bv-info:   #0ea5e9;
    --bv-locked: #94a3b8;

    /* Shape + motion */
    --bv-radius-sm: 8px;
    --bv-radius:    12px;
    --bv-radius-lg: 16px;
    --bv-shadow:    0 1px 2px rgba(15,23,42,.06), 0 8px 24px rgba(15,23,42,.06);
    --bv-ease:      cubic-bezier(.2,.8,.2,1);

    /* Spacing scale (4-px) */
    --bv-s1: 4px; --bv-s2: 8px; --bv-s3: 12px; --bv-s4: 16px; --bv-s5: 24px; --bv-s6: 32px;

    /* Type */
    --bv-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue",
               Arial, "Noto Sans", sans-serif;
    --bv-fs-12: 12px; --bv-fs-13: 13px; --bv-fs-14: 14px; --bv-fs-16: 16px; --bv-fs-20: 20px;

    background: var(--bv-bg);
    color: var(--bv-text);
    font-family: var(--bv-font);
    min-height: calc(100vh - 32px);
}

#bv-admin-root[data-theme="dark"] {
    --bv-bg:        #0b1020;
    --bv-surface:   #0f172a;
    --bv-surface-2: #111a33;
    --bv-text:      #e5e7eb;
    --bv-muted:     #94a3b8;
    --bv-border:    rgba(148,163,184,.18);
    --bv-border-strong: rgba(148,163,184,.32);
    --bv-primary-soft:  rgba(99,102,241,.18);
    --bv-shadow:    0 1px 2px rgba(0,0,0,.4), 0 10px 30px rgba(0,0,0,.4);
}

/* Shell ------------------------------------------------------------------- */
.bv-topbar {
    display: flex; align-items: center; gap: var(--bv-s3);
    padding: var(--bv-s3) var(--bv-s5);
    background: var(--bv-surface);
    border-bottom: 1px solid var(--bv-border);
    position: sticky; top: 32px; z-index: 20;
}
.bv-topbar__logo { height: 28px; max-width: 160px; object-fit: contain; }
.bv-topbar__spacer { flex: 1; }
.bv-topbar__right { display: flex; align-items: center; gap: var(--bv-s3); }
.bv-topbar__user  { display: flex; align-items: center; gap: var(--bv-s2); color: var(--bv-muted); font-size: var(--bv-fs-13); }
.bv-topbar__avatar{ width: 28px; height: 28px; border-radius: 999px; border: 1px solid var(--bv-border); }

.bv-layout {
    display: grid;
    grid-template-columns: 248px 1fr;
    min-height: calc(100vh - 32px - 49px);
}

.bv-sidebar {
    padding: var(--bv-s3);
    border-right: 1px solid var(--bv-border);
    background: var(--bv-surface);
    position: sticky; top: 81px;
    align-self: start;
}
.bv-nav-item {
    width: 100%; display: flex; align-items: center; gap: var(--bv-s2);
    padding: 10px 12px; border: 0; background: transparent; color: var(--bv-muted);
    border-radius: var(--bv-radius-sm); cursor: pointer; font-weight: 600; font-size: var(--bv-fs-13);
    transition: background .15s var(--bv-ease), color .15s var(--bv-ease);
}
.bv-nav-item:hover           { background: var(--bv-primary-soft); color: var(--bv-text); }
.bv-nav-item.is-active       { background: var(--bv-primary-soft); color: var(--bv-primary); }
.bv-nav-item:focus-visible   { outline: 0; box-shadow: var(--bv-focus-ring); }
.bv-nav-item__badge          { margin-left: auto; font-size: var(--bv-fs-12); }

.bv-content { padding: var(--bv-s5); display: grid; gap: var(--bv-s5); }

/* Cards, badges, buttons -------------------------------------------------- */
.bv-card      { background: var(--bv-surface); border: 1px solid var(--bv-border);
                border-radius: var(--bv-radius); padding: var(--bv-s5); box-shadow: var(--bv-shadow); }
.bv-card__h   { display: flex; align-items: center; gap: var(--bv-s2); margin-bottom: var(--bv-s3); }
.bv-card__h h2{ margin: 0; font-size: var(--bv-fs-16); }

.bv-badge { display: inline-flex; align-items: center; gap: 6px;
            padding: 2px 8px; border-radius: 999px; font-size: var(--bv-fs-12); font-weight: 600; }
.bv-badge--ok    { background: rgba(22,163,74,.12);  color: var(--bv-ok);    }
.bv-badge--warn  { background: rgba(217,119,6,.14);  color: var(--bv-warn);  }
.bv-badge--err   { background: rgba(220,38,38,.12);  color: var(--bv-err);   }
.bv-badge--info  { background: rgba(14,165,233,.12); color: var(--bv-info);  }
.bv-badge--muted { background: rgba(148,163,184,.18);color: var(--bv-muted); }

.bv-btn {
    display: inline-flex; align-items: center; gap: var(--bv-s2);
    padding: 8px 14px; border-radius: var(--bv-radius-sm);
    border: 1px solid var(--bv-border-strong); background: var(--bv-surface);
    color: var(--bv-text); cursor: pointer; font-weight: 600; font-size: var(--bv-fs-13);
}
.bv-btn:hover           { background: var(--bv-surface-2); }
.bv-btn:focus-visible   { outline: 0; box-shadow: var(--bv-focus-ring); }
.bv-btn--primary        { background: var(--bv-primary); color: var(--bv-primary-ink); border-color: transparent; }
.bv-btn--primary:hover  { filter: brightness(.95); }
.bv-btn--danger         { background: var(--bv-err);     color: #fff;              border-color: transparent; }
.bv-btn[disabled],
.bv-btn[aria-disabled="true"]{ opacity: .55; cursor: not-allowed; }

/* Pro lock --------------------------------------------------------------- */
.bv-prolock      { position: relative; }
.bv-prolock__tag { position: absolute; top: 8px; right: 8px; background: var(--bv-locked);
                   color: #fff; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.bv-prolock--locked > *:not(.bv-prolock__tag) { filter: blur(2px) saturate(.6); pointer-events: none; user-select: none; }

/* Responsive -------------------------------------------------------------- */
@media (max-width: 960px) {
    .bv-layout  { grid-template-columns: 1fr; }
    .bv-sidebar { position: static; border-right: 0; border-bottom: 1px solid var(--bv-border); }
}

/* RTL -------------------------------------------------------------------- */
[dir="rtl"] .bv-sidebar { border-right: 0; border-left: 1px solid var(--bv-border); }
```

### 8.4 Navigation

Sidebar items, in order:

1. Dashboard — `dashicons-dashboard`
2. Bulk Editor — `dashicons-editor-table`
3. Jobs — `dashicons-list-view`  _(badge: count of running jobs)_
4. Templates — `dashicons-layout`  _(Pro lock in Free)_
5. AI Assistant — `dashicons-sparkles`  _(Pro lock in Free for full features, Free gets the 3-variation teaser)_
6. Settings — `dashicons-admin-settings`
7. Help — `dashicons-editor-help`

Topbar: logo (swaps light/dark), spacer, quick search (Cmd/Ctrl+K), dark-mode toggle, help icon, user chip.

### 8.5 Views

**Dashboard** — hero panel "Store health at a glance":
- KPI tiles: Variations managed, Last 7 days edits, Running jobs, Failed jobs.
- Recent jobs list (5 rows) with Rollback shortcut.
- Quick actions: "Edit a product", "Run CSV import", "Ask AI".
- Upgrade banner in Free only.

**Bulk Editor** — AG Grid with:
- Product picker (type-ahead) at top; multi-product in Pro.
- Column picker with persistence.
- Sticky toolbar with bulk actions + formula input.
- Row-count footer + "Apply Changes" (creates a job, opens the Job drawer).
- Keyboard: `Ctrl/Cmd+Z` undo, `Ctrl/Cmd+Y` redo, `Ctrl/Cmd+A` select page, `/` focus search, `?` show shortcuts.

**Jobs** — table with status badge, progress bar, row deltas, actions (View, Pause (Pro), Resume (Pro), Cancel, Rollback). Clicking a row opens a drawer with per-row deltas and error log.

**Templates** — card grid. Each card: name, attributes, SKU pattern, "Apply to product", "Duplicate", "Delete". Pro-locked in Free.

**AI Assistant** — four tabs (Generate | Suggest Prices | Clean Import | Summarize). Each tab: input area, "Estimated cost: ~$0.0012" tag, Generate button. Result is a preview table with per-row checkboxes and an "Apply as Job" button. Never writes directly.

**Settings** — tabbed:
- General (menu icon, default chunk size, row-count guardrail, concurrency lock toggle)
- AI Provider (provider picker, API key, model, consent toggle, quota, test-connection, usage meter)
- Retention (jobs TTL, AI log TTL, deltas TTL — cron runs daily)
- Diagnostics (download system info JSON, reset UI preferences, uninstall data toggle)

**Help** — links to docs, changelog, support; in-app tour trigger; keyboard shortcut cheat sheet.

### 8.6 Empty states, loading, and errors

- Every list view has an illustrated empty state with a single CTA.
- Every data fetch shows skeleton rows (3 for lists, 1 for a card).
- Every failed fetch shows an inline error card with a Retry button; 5xx also raises a toast.
- Long tasks surface an `aria-live="polite"` region so screen readers announce progress.

### 8.7 Upsell pattern

`<ProLock feature="ROLLBACK">…</ProLock>` wraps any Pro element. In Free it adds a "Pro" tag, blurs the children, and intercepts clicks to open the upgrade modal. Source of truth is `FeatureFlags::isEnabled()` which the PHP layer localises into `BulkVariationsAdmin.is_pro` + a per-feature map.

### 8.8 Accessibility acceptance criteria

- Colour contrast ratio >= 4.5:1 for body text, 3:1 for large text — both themes.
- All interactive elements reachable by `Tab`, visible focus ring.
- All icons have a text label or `aria-label`.
- AG Grid: set `ensureDomOrder: true`, provide `getRowDescription`, keep row selection in sync with `aria-selected`, test with NVDA and VoiceOver.
- No colour-only status — status always pairs with icon + label.
- Respect `prefers-reduced-motion`.

### 8.9 Internationalisation

- Text domain `bulk-variations`. All strings via `__()` / `_n()` / `_x()`.
- Generate `.pot` via `wp i18n make-pot`.
- `wp_set_script_translations( 'bv-admin', 'bulk-variations' )` in PHP.
- Use `@wordpress/i18n` `sprintf` on the JS side.
- RTL: load `admin-rtl.css` automatically via `wp_style_add_data( 'bv-admin', 'rtl', 'replace' )`.

### 8.10 Telemetry (opt-in only)

- No outbound calls by default except Freemius handshakes that the user consents to.
- If the merchant opts in, ship anonymised: plugin version, WP version, WC version, active theme, enabled features, job counts (no content).
- Never ship: product titles, prompts, customer data, emails, API keys.

---

## Part C — Patch Notes for the Rest of the Plan

These are the minimum edits to reconcile the plan with the revised UI and close the gaps from Part A. Apply in a follow-up PR to `bulk-variations-plan.md`.

- **§4 Directory Structure** — replace the `admin/` tree with the `assets/admin/` tree from §8.1 above. Keep a `src/Admin/` PHP tree for `AdminPage.php`, `Notices.php`, `RestBootstrapper.php`.
- **§4 Stack Decisions** — add a row: `Admin design system` -> `tokens + light/dark, based on admin-dashboard-pattern` -> `Why: shared look across all views, theme-able by agencies`.
- **§6 `BulkEditor::bulkUpdate()`** — replace the `WC_Product_Variable::sync_with_children(/* affected parent IDs */)` placeholder with a concrete implementation that collects `wp_posts.post_parent` for the affected variation IDs, dedupes, and calls `WC_Product_Variable::sync( $parent_id )` (or `sync_with_children` where available) per parent.
- **§7 REST API** — add: `args` schemas per route, pagination contract (`page`, `per_page`, `X-WP-Total`, `X-WP-TotalPages`), error shape `{ code, message, data:{ status, detail } }`, and `POST /jobs/{id}/pause`, `POST /jobs/{id}/resume`, `POST /jobs/{id}/cancel`.
- **§9 AI** — add: JSON-repair fallback (one retry with the failing output embedded in the prompt as "the previous response was invalid JSON, return only valid JSON"), cost estimator (tokens x price), Anthropic adapter stub, AI hard-kill constant (`define('BV_DISABLE_AI', true)`).
- **§10 Performance** — replace the non-public `wc_update_product_lookup_tables()` call with a version-aware helper that falls back to `WC_Product_Variable::sync( $parent_id )` and `wc_update_product_lookup_tables_column()` where available.
- **§11 Licensing** — add explicit Freemius `__premium_only` code-strip pattern, and a "Pro / Free split" diagram. Add the opt-in / skip flow.
- **§12 Prompt Pack** — rewrite PROMPT 6 to scaffold the unified shell from §8.1, not a standalone spreadsheet. Add PROMPT 11 — Wire & Smoke Test. Add PROMPT 12 — CI + Static Analysis. Add PROMPT 13 — Blocks + Frontend Gutenberg block. Add PROMPT 14 — Privacy & Uninstall.
- **§13 Roadmap** — re-sequence weeks 3-6: week 3 admin shell + Dashboard + Settings skeleton, week 4 Jobs + CSV import, week 5 AG Grid editor, week 6 polish + WP.org submission.
- **§15 Testing Plan** — add E2E (Playwright against `wp-env`), visual regression (Chromatic or Loki), a11y (axe-core), load test (10k variations seed + benchmark script), memory cap assertion.
- **New §16 — Quality Engineering**: GitHub Actions matrix (PHP 7.4/8.0/8.1/8.2/8.3 x WP last 2 x WC last 3), PHPStan level 6, PHPCS (WPCS + WooSniffs), Prettier + ESLint + Stylelint, conventional commits, semver.
- **New §17 — Privacy, Uninstall, Data Export**: `wp_add_privacy_policy_content`, personal data exporter/eraser for AI log, uninstall toggle, full data purge routine, retention cron.
- **New §18 — Accessibility & i18n**: WCAG 2.1 AA contract, RTL plan, text-domain + .pot workflow, WPML/Polylang compatibility.
- **New §19 — Release Engineering**: readme.txt template, WP.org assets list (banner/icon/screenshots), diagnostic export, Freemius onboarding screens, support ticket field mapping.
