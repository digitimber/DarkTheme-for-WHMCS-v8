<?php
/**
 * DigiTimber DarkTheme (DTDT) for WHMCS
 *
 * Hook registrations:
 *   - Inject dark-blend.css on every admin page (Blend theme only).
 *     Loading is conditional on the user's preference when the toggle
 *     is enabled; otherwise it loads unconditionally.
 *   - Inject custom.css if the admin has created one (for personal overrides).
 *   - Append date/time, open-ticket-count, and dark/light toggle widgets
 *     to the admin top nav bar.
 *
 * Toggle mechanism (conditional stylesheet loading):
 *
 *   On every page request we emit an inline <script> BEFORE the dark CSS
 *   link would normally appear. That script decides the theme:
 *
 *     1. If localStorage['dtdt-dark-mode'] is "true"  -> load dark.
 *     2. If localStorage['dtdt-dark-mode'] is "false" -> don't load dark.
 *     3. If localStorage['dtdt-dark-mode'] is null    -> match system
 *        preference via window.matchMedia('(prefers-color-scheme: dark)'),
 *        then persist the decision so future visits are deterministic.
 *
 *   When loading is needed, the inline script builds a <link id="dtdt-css">
 *   element and appends it to <head>. Because this runs synchronously
 *   before the browser paints, there is no flash of unstyled content.
 *
 *   The toggle button's click handler removes or re-adds the same
 *   <link id="dtdt-css"> element and updates localStorage.
 *
 *   When `toggle_enable` is "no" the CSS is hard-included via a plain
 *   <link> tag - no JS, no toggle, always dark. This preserves the
 *   original addon's "just activate for dark mode" behavior.
 *
 * @package   DTDT
 * @author    Digitimber
 * @copyright Copyright (c) 2026 Digitimber
 * @license   GPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version. See LICENSE in the module root.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;

/**
 * localStorage key name. Exposed as a constant so PHP and JS stay in sync.
 */
const DTDT_LS_KEY = 'dtdt-dark-mode';

/**
 * DOM id of the injected <link> element. The toggle JS finds it by id.
 */
const DTDT_LINK_ID = 'dtdt-css';

/**
 * Build an absolute URL to an asset file inside this module.
 *
 * Earlier versions used relative URLs like `../modules/addons/dtdt/...`
 * which worked on classic admin pages at `/admin/something.php` where
 * `..` climbed one level to the WHMCS root. They broke on WHMCS 8's
 * routed pretty URLs (e.g. `/admin/billing/invoice/29740`) where `..`
 * does not reach the WHMCS root, causing the stylesheet to 404 silently
 * and the page to render in light mode regardless of toggle preference.
 *
 * Using SystemURL gives us a URL that resolves the same way from every
 * admin page.
 *
 * @param string $relativePath Path inside the module dir, e.g. 'css/dark-blend.css'.
 * @param int|null $version Optional cache-bust version (typically a file mtime).
 * @return string
 */
function dtdt_asset_url($relativePath, $version = null)
{
    try {
        $base = rtrim((string) Setting::getValue('SystemURL'), '/');
    } catch (\Exception $e) {
        $base = '';
    }
    $url = $base . '/modules/addons/dtdt/' . ltrim($relativePath, '/');
    if ($version !== null) {
        $url .= '?v=' . $version;
    }
    return $url;
}

/**
 * Inject the dark theme stylesheet into the admin <head>.
 *
 * Two code paths:
 *   - Toggle OFF: emit a plain <link> tag (always dark, original behavior).
 *   - Toggle ON:  emit an inline script that decides based on localStorage
 *                 and only then injects the <link>. No FOUC because the
 *                 script runs synchronously before paint.
 *
 * @param array $vars WHMCS template vars. Contains 'template' = current admin theme.
 * @return string <link> tag, <script> block, or empty string.
 */
function dtdt_hook_maincss($vars)
{
    // Template-var check: if populated and not Blend, skip (another admin
    // theme is active and we should not style it). If empty, let through:
    // some legacy WHMCS admin pages (e.g. invoices.php, clients.php) do
    // not populate $vars['template'] even though they render under Blend,
    // and gating on the var here caused the stylesheet to miss those pages.
    if (!empty($vars['template']) && $vars['template'] !== 'blend') {
        return '';
    }

    // Cache-bust using the file's mtime so updates to the stylesheet are
    // picked up immediately without forcing a manual hard reload.
    $cssPath = __DIR__ . '/css/dark-blend.css';
    $ver     = file_exists($cssPath) ? filemtime($cssPath) : time();
    $href    = dtdt_asset_url('css/dark-blend.css', $ver);

    // Simple case: toggle disabled, always dark, just emit a <link>.
    if (!dtdt_setting_enabled('toggle_enable')) {
        return '<link id="' . DTDT_LINK_ID . '" '
             . 'href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" '
             . 'rel="stylesheet" type="text/css" />';
    }

    // Toggle case: inline script decides whether to inject the <link>.
    // Must run synchronously in <head>, before paint, to avoid FOUC.
    $hrefJson = json_encode($href);
    $keyJson  = json_encode(DTDT_LS_KEY);
    $idJson   = json_encode(DTDT_LINK_ID);

    return <<<SCRIPT
<script type="text/javascript">
(function () {
    try {
        var key = {$keyJson};
        var linkId = {$idJson};
        var href = {$hrefJson};
        var pref = null;
        try { pref = window.localStorage.getItem(key); } catch (e) { /* ignore */ }
        var wantDark;
        if (pref === 'true') {
            wantDark = true;
        } else if (pref === 'false') {
            wantDark = false;
        } else {
            // First-time visitor: match OS/browser preference, then persist.
            wantDark = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            try { window.localStorage.setItem(key, wantDark ? 'true' : 'false'); } catch (e) { /* ignore */ }
        }
        if (wantDark) {
            var link = document.createElement('link');
            link.id = linkId;
            link.rel = 'stylesheet';
            link.type = 'text/css';
            link.href = href;
            (document.head || document.documentElement).appendChild(link);
        }
    } catch (e) { /* never break the admin page over this */ }
})();
</script>
SCRIPT;
}

/**
 * Inject custom.css if the admin has created one in the module directory.
 *
 * This gives admins a place to put their own tweaks that will survive
 * module updates (the custom.css file is not overwritten on upgrade).
 *
 * Note: custom.css always loads when present, regardless of dark/light
 * toggle state. Users who want their overrides to only apply in dark
 * mode can scope their rules with a `#dtdt-css ~ * { ... }` pattern,
 * or better yet, check for the presence of `link#dtdt-css` themselves.
 *
 * @param array $vars WHMCS template vars.
 * @return string <link> tag or empty string.
 */
function dtdt_hook_customcss($vars)
{
    // Same logic as dtdt_hook_maincss: let through when template var is
    // empty (legacy pages), skip only when explicitly a different theme.
    if (!empty($vars['template']) && $vars['template'] !== 'blend') {
        return '';
    }

    $customPath = __DIR__ . '/custom.css';
    if (!file_exists($customPath)) {
        return '';
    }

    $ver  = filemtime($customPath);
    $href = dtdt_asset_url('custom.css', $ver);
    return '<link href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" '
         . 'rel="stylesheet" type="text/css" />';
}

/**
 * Append date/time, ticket-count, and toggle widgets to the admin top nav bar.
 *
 * Each widget is conditional on its own module setting. The actual DOM
 * injection happens client-side via jQuery on DOMContentLoaded, prepending
 * into `ul.right-nav` (same insertion point the original module used).
 *
 * @param array $vars WHMCS template vars.
 * @return string <script> block or empty string.
 */
function dtdt_hook_navbar_stats($vars)
{
    // Only run on WHMCS 8.x. The nav structure is different on older
    // versions and this hook would inject into the wrong place.
    if (!dtdt_is_whmcs_v8()) {
        return '';
    }

    $showTime    = dtdt_setting_enabled('datetime_enable');
    $showTickets = dtdt_setting_enabled('ticketcount_enable');
    $showToggle  = dtdt_setting_enabled('toggle_enable');

    if (!$showTime && !$showTickets && !$showToggle) {
        return '';
    }

    $fragments = [];

    if ($showTickets) {
        $ticketsTotal = dtdt_count_awaiting_tickets();
        if ($ticketsTotal > 0) {
            $fragments[] = dtdt_build_tickets_fragment($ticketsTotal);
        }
    }

    if ($showTime) {
        $fragments[] = dtdt_build_time_fragment();
    }

    if ($showToggle) {
        $fragments[] = dtdt_build_toggle_fragment();
    }

    if (empty($fragments)) {
        return '';
    }

    // JSON-encode so jQuery can safely inject the HTML string without
    // escaping issues from stray quotes in translated strings.
    $html     = json_encode(implode('', $fragments));
    $keyJson  = json_encode(DTDT_LS_KEY);
    $idJson   = json_encode(DTDT_LINK_ID);

    // The toggle handler is only emitted if the toggle widget is enabled,
    // so we conditionally include it. It rebuilds the <link> href the
    // same way maincss does, so if we update the file we only need to
    // change it in one place (PHP computes mtime; JS inherits via data-*).
    $toggleJs = '';
    if ($showToggle) {
        $cssPath  = __DIR__ . '/css/dark-blend.css';
        $ver      = file_exists($cssPath) ? filemtime($cssPath) : time();
        $hrefJson = json_encode(dtdt_asset_url('css/dark-blend.css', $ver));

        $toggleJs = <<<JS

    // --- Toggle handler -------------------------------------------------
    var \$btn = \$('#dtdt-theme-toggle');
    if (\$btn.length) {
        // Sync button icon with current state on page load.
        var isDarkNow = !!document.getElementById({$idJson});
        \$btn.removeClass('fa-sun fa-moon')
             .addClass(isDarkNow ? 'fa-sun' : 'fa-moon');

        \$btn.closest('li').on('click', function (e) {
            e.preventDefault();
            var existing = document.getElementById({$idJson});
            if (existing) {
                // Currently dark - switch to light.
                existing.parentNode.removeChild(existing);
                try { window.localStorage.setItem({$keyJson}, 'false'); } catch (e2) {}
                \$btn.removeClass('fa-sun').addClass('fa-moon');
            } else {
                // Currently light - switch to dark.
                var link = document.createElement('link');
                link.id = {$idJson};
                link.rel = 'stylesheet';
                link.type = 'text/css';
                link.href = {$hrefJson};
                (document.head || document.documentElement).appendChild(link);
                try { window.localStorage.setItem({$keyJson}, 'true'); } catch (e2) {}
                \$btn.removeClass('fa-moon').addClass('fa-sun');
            }
            // Brief rotation for feedback.
            var el = \$btn.get(0);
            if (el) {
                el.style.transition = 'transform 0.3s ease';
                el.style.transform  = 'rotate(360deg)';
                setTimeout(function () { el.style.transform = 'rotate(0deg)'; }, 300);
            }
        });
    }
JS;
    }

    return <<<SCRIPT
<style type="text/css">
/* DTDT widget base styles - apply in BOTH dark and light mode, because the
   Blend navbar itself is dark blue in both modes so widget text/icons need
   to be light. Dark-mode token-based rules in dark-blend.css layer on top
   of these via natural cascade order. */
.dtdt-nav-time,
.dtdt-nav-time small,
.dtdt-nav-time .v8navstats,
.dtdt-nav-time .v8navstats span,
.dtdt-tickets-nav .tickets-nav,
.dtdt-nav-toggle .theme-toggle-icon {
    color: #ffffff;
}
.dtdt-nav-time {
    max-width: 180px;
}
.dtdt-nav-time small {
    padding: 0 10px;
    line-height: 34px;
    display: inline-flex;
    cursor: default;
}
.dtdt-nav-time i.fas.fa-clock {
    font-size: 17px;
}
.dtdt-nav-time .v8navstats {
    font-size: 17px;
    font-weight: 500;
}
.dtdt-nav-time .v8navstats .icon-container {
    margin: 0 5px 0 0;
}
.dtdt-nav-time .nav-date,
.dtdt-nav-time .nav-clock {
    font-weight: 900;
    color: #d0d0d0;
}
.dtdt-tickets-nav .dtdt-tickets-count {
    font-size: 20px;
    font-weight: 700;
    color: #ff6b6b;
}
.dtdt-tickets-nav small.v8navstatsul {
    display: inline-flex;
    align-items: center;
    flex-wrap: nowrap;
    margin: -10px 0 0 0;
}
.dtdt-tickets-nav .v8navstatsul .icon-container {
    font-size: 1.5em;
    display: inline-flex;
    margin: 0 7px 0 0;
}
.dtdt-nav-toggle {
    cursor: pointer;
}
.dtdt-nav-toggle .theme-toggle-container {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    transition: background-color 0.2s ease;
}
.dtdt-nav-toggle .theme-toggle-icon {
    font-size: 16px;
    transition: color 0.2s ease, transform 0.3s ease;
}
</style>
<script type="text/javascript">
jQuery(function (\$) {
    var \$rightNav = \$('ul.right-nav').first();
    if (\$rightNav.length) {
        \$rightNav.prepend({$html});
    }{$toggleJs}
});
</script>
SCRIPT;
}

/* ------------------------------------------------------------------ *
 *  Helpers                                                            *
 * ------------------------------------------------------------------ */

/**
 * Detect whether the installed WHMCS is version 8.x.
 *
 * Uses the WHMCS_VERSION constant when available (faster, no DB hit),
 * falls back to tblconfiguration if the constant isn't defined for
 * some reason.
 *
 * @return bool
 */
function dtdt_is_whmcs_v8()
{
    if (defined('WHMCS_VERSION')) {
        return (int) explode('.', WHMCS_VERSION)[0] === 8;
    }

    try {
        $row = Capsule::table('tblconfiguration')
            ->where('setting', 'Version')
            ->value('value');
        return $row && (int) explode('.', $row)[0] === 8;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Look up an addon module setting and return whether it's enabled.
 *
 * WHMCS yesno fields store the literal string "on" (enabled) or "" (disabled).
 * We treat anything truthy as enabled for safety.
 *
 * @param string $name Setting key.
 * @return bool
 */
function dtdt_setting_enabled($name)
{
    try {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', 'dtdt')
            ->where('setting', $name)
            ->value('value');
        return !empty($value) && $value !== '0';
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Count open tickets awaiting a staff reply.
 *
 * Joins tbltickets to tblticketstatuses so we only count statuses
 * flagged with showawaiting=1 (i.e. Open / Customer-Reply / Answered
 * depending on how the admin has configured their statuses).
 * Merged tickets are excluded.
 *
 * @return int
 */
function dtdt_count_awaiting_tickets()
{
    try {
        return (int) Capsule::table('tbltickets as t')
            ->leftJoin('tblticketstatuses as s', 't.status', '=', 's.title')
            ->where('s.showawaiting', '1')
            ->where('t.merged_ticket_id', '0')
            ->count();
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * Build the <li> HTML for the awaiting-tickets widget.
 *
 * @param int $count
 * @return string
 */
function dtdt_build_tickets_fragment($count)
{
    $count   = (int) $count;
    $label   = $count . ' ' . AdminLang::trans('stats.ticketsawaitingreply');
    $labelE  = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

    return '<li class="dtdt-tickets-nav">'
         . '<a href="supporttickets.php" '
         .    'class="tickets-nav" '
         .    'data-toggle="tooltip" '
         .    'data-placement="bottom" '
         .    'title="' . $labelE . '" '
         .    'data-original-title="' . $labelE . '">'
         .   '<small class="v8navstatsul">'
         .     '<span class="icon-container">'
         .       '<i class="fad fa-comments"></i>'
         .     '</span>'
         .     '<span class="v8navstats dtdt-tickets-count">' . $count . '</span>'
         .   '</small>'
         . '</a>'
         . '</li>';
}

/**
 * Build the <li> HTML for the date/time widget.
 *
 * Uses the server's configured timezone, same as the rest of WHMCS.
 *
 * @return string
 */
function dtdt_build_time_fragment()
{
    $titleE = htmlspecialchars(date('M d Y, H:i'), ENT_QUOTES, 'UTF-8');
    $shortE = htmlspecialchars(date('M d, H:i'), ENT_QUOTES, 'UTF-8');

    return '<li class="nav-time dtdt-nav-time" title="' . $titleE . '">'
         . '<small>'
         .   '<span class="v8navstats">'
         .     '<span class="icon-container">'
         .       '<i class="icon fas fa-clock"></i>'
         .     '</span>'
         .     '<span class="nav-date">' . $shortE . '</span>'
         .     '<span class="nav-clock"></span>'
         .   '</span>'
         . '</small>'
         . '</li>';
}

/**
 * Build the <li> HTML for the dark/light mode toggle button.
 *
 * The class `fa-moon` vs `fa-sun` is corrected by the toggle handler on
 * DOMContentLoaded - we don't know here whether the stylesheet is loaded
 * yet, so we emit both fa-sun AND fa-moon classes and let JS strip the
 * wrong one. This avoids a brief icon flash.
 *
 * @return string
 */
function dtdt_build_toggle_fragment()
{
    $title = AdminLang::trans('stats.darkmodetoggle');
    // Fall back to English if the language key is missing (AdminLang returns
    // the key itself as a string when no translation is found).
    if ($title === 'stats.darkmodetoggle' || $title === '') {
        $title = 'Toggle dark/light mode';
    }
    $titleE = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    return '<li class="nav-toggle dtdt-nav-toggle" '
         .    'title="' . $titleE . '" '
         .    'data-toggle="tooltip" '
         .    'data-placement="bottom">'
         . '<small>'
         .   '<span class="theme-toggle-container">'
         .     '<i class="theme-toggle-icon fas fa-moon" id="dtdt-theme-toggle"></i>'
         .   '</span>'
         . '</small>'
         . '</li>';
}

/* ------------------------------------------------------------------ *
 *  Hook registrations                                                 *
 * ------------------------------------------------------------------ */

add_hook('AdminAreaHeadOutput',   1, 'dtdt_hook_maincss');
add_hook('AdminAreaFooterOutput', 1, 'dtdt_hook_customcss');
add_hook('AdminAreaHeaderOutput', 1, 'dtdt_hook_navbar_stats');
