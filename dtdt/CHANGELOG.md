# Changelog

All notable changes to this module are documented here.

## [1.0.0] - 2026-04-23

First release of DigiTimber DarkTheme (DTDT). Derived from WevrLabs' Blend Dark Mode v3.1.1 (GPL-3.0-licensed upstream) with substantial rework and several new features. Licensed GPL-3.0-or-later, as required by the upstream license.

### Features
- **Full dark UI for the Blend admin theme**, covering WHMCS 8.13+ core, the Layers dashboard, Lagom 2 client-framework classes that bleed into admin context, the staffboard module (including its ThickBox modal), and inline-style status colors from admin PHP source (Selectize dropdowns, reports.php bgcolor rows, orders.php registrar/server panels, supportcenter.php ticket stats, systemsupportrequest.php help boxes, calendar.php popups).
- **CSS variable token system** in `:root` (67 tokens covering surfaces, borders, text, links, status colors, alerts, labels, banners, legacy edge cases) so the whole theme can be reskinned by editing the palette rather than chasing hex values through the stylesheet.
- **Optional dark/light toggle** per admin, stored in localStorage so different admins on the same machine can have different themes. First-time visitors default to their OS/browser `prefers-color-scheme` setting, then that decision is persisted so future visits are deterministic. FOUC prevention: the theme decision runs in an inline `<head>` script before any CSS parsing, so the page paints correctly on first render. When the toggle is disabled, the dark theme loads unconditionally for everyone (the original WevrLabs behavior).
- **Optional date/time widget** in the admin top nav bar.
- **Optional open-ticket-count badge** in the top nav bar, animated, hidden automatically when the count is zero.
- **`custom.css` override file** for personal tweaks that survive module updates.
- **Automatic cache-busting** on CSS via file mtime, so admins do not need hard-reloads after an update.

### Implementation notes
- Modern `jQuery(function($) { ... })` syntax instead of deprecated `$(document).on('ready', ...)`.
- Capsule query builder instead of `Capsule::select(Capsule::raw(...))`.
- `WHMCS_VERSION` constant check instead of a DB round-trip for version detection.
- Undefined-variable safety: partial config (one feature on, one off) no longer throws warnings.
- HTML escaping in navbar widget markup.
- Proper `activate()` / `deactivate()` hooks with user-facing messages.
- Dead jQuery selector (`#v8navstats`) removed.
- Minimum WHMCS version: 8.0.
- Lara admin theme support dropped (was a 37KB separate stylesheet, unused in our deployment).
- Navbar widget (date/time, ticket count, toggle button) base styles are emitted inline by `hooks.php` so they apply in both dark and light mode. This is important because Blend's navbar is dark blue regardless of page theme, so widget text and icons always need light colors. `dark-blend.css` adds only dark-mode-specific overrides (hover states, token-backed colors) on top of that baseline.
- The CSS-injection hooks (`dtdt_hook_maincss`, `dtdt_hook_customcss`) tolerate an empty `$vars['template']` rather than requiring it to be `'blend'`. Some legacy WHMCS admin pages (for example `invoices.php?status=Overdue`, `clients.php`) fire `AdminAreaHeadOutput` without populating the `template` variable. The strict form would skip those pages, which caused the dark stylesheet to disappear on click-through. The new check only bails out when `template` is populated and names a theme other than Blend, so legacy pages work and non-Blend themes are still protected.
- Stylesheet URLs are absolute, built from WHMCS's `SystemURL` setting rather than being relative paths starting with `../modules/`. The old relative URLs resolved correctly from classic admin pages like `/admin/index.php` (where `..` reached WHMCS root) but resolved to 404s from WHMCS 8 routed URLs like `/admin/billing/invoice/29740` (where `..` only climbs one level of the pretty URL). The symptom was the dark stylesheet silently failing to load on invoice-detail, client-summary, and other routed pages, with the page rendering in light mode regardless of the user's toggle preference.

### Customising a renamed Blend theme
If you have cloned the Blend admin theme under a different folder name, see the "Using a renamed or customised Blend theme" section in the README for the `hooks.php` edit needed. Thanks to the user who suggested this on the upstream project.

### Attribution
- Original **"Blend Dark Mode"** addon by [WevrLabs Hosting](https://github.com/WevrLabs-Group/WHMCS-Blend-Admin-Theme-Dark-Mode), GPL-3.0-licensed. This module's CSS foundation is derived from theirs.
- **Toggle mechanism** inspired by an open pull request from [@LoneStarDataRanch](https://github.com/LoneStarDataRanch) to the upstream WevrLabs project (August 2025, unmerged). The implementation here differs (conditional stylesheet loading rather than body-class scoping, and pre-boot synchronous FOUC prevention) but the UX and localStorage key pattern are borrowed from that work.
- **Date/time and ticket-count nav widgets** inspired by Davide Mantenuto (Katamaze) and his [Admin Stats hook](https://github.com/Katamaze/WHMCS-Action-Hook-Factory#admin-stats-for-whmcs-v8), carried forward from the upstream WevrLabs addon.
