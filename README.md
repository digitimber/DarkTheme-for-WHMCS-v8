# DigiTimber DarkTheme (DTDT) for WHMCS

Dark mode for the WHMCS Blend admin theme, with an optional per-admin dark/light toggle. Activate the addon and every admin page gets a dark UI; or let each admin pick their preference and it's remembered in their browser.

Tested on WHMCS 8.13.x with the Blend admin theme.

## Install

1. Copy the `dtdt/` folder to `modules/addons/dtdt/` in your WHMCS install.
2. Sign into the admin area, go to **Setup > Addon Modules**, find **DigiTimber DarkTheme (DTDT) for WHMCS** in the list, and click **Activate**.
3. Click **Configure**, assign access to the admin roles that should see the UI, save.
4. Reload any admin page.

To update, replace the `dtdt/` folder with the new version. The CSS cache-busts automatically from the file's modification time, so you do not need to tell anyone to hard-reload.

## Configuration

Three toggles on the addon config page:

- **Display date and time** - adds a clock to the top right nav (WHMCS 8 only).
- **Display open tickets count** - adds an animated red badge with the count of tickets awaiting reply (WHMCS 8 only). Hidden automatically when the count is zero.
- **Display dark/light mode toggle** - shows a sun/moon button in the nav bar. When enabled, each admin can choose their own theme and it will be remembered in their browser. First-time visitors get whichever theme matches their OS/browser preference (`prefers-color-scheme`). When disabled, the dark theme loads for everyone unconditionally (the original addon's behavior).

All three are on by default.

## How the toggle works

When the toggle is enabled, we don't load the dark CSS unconditionally. Instead:

1. An inline script runs in `<head>` before any CSS is parsed. It checks `localStorage['dtdt-dark-mode']`.
2. If the user has never set a preference, the script reads `window.matchMedia('(prefers-color-scheme: dark)')` and persists the decision so future visits are deterministic.
3. If the decision is "dark", the script dynamically builds a `<link>` element and appends it to `<head>` before the browser paints. No flash of unstyled content.
4. Clicking the sun/moon button removes or re-adds that same `<link>` element and updates localStorage.

Side effects worth knowing about:

- Each admin gets their own preference per-browser. An admin who uses WHMCS from a laptop and a phone will have two independent preferences.
- Clearing browser storage resets the preference back to the system default.
- If localStorage is unavailable (private browsing with strict settings), the toggle still works in-session but won't persist.

## Customising

If you want to add your own CSS tweaks without editing the distributed stylesheet (so your tweaks survive module updates), copy `custom.css.example` to `custom.css` in the module folder and put your overrides there. The module loads it automatically after `dark-blend.css`.

**Note:** `custom.css` loads in both dark and light mode. If you want overrides that apply ONLY in dark mode, check for the dark stylesheet's presence in your own selectors or wrap them with `@supports selector(:has(link#dtdt-css)) { body:has(link#dtdt-css) ... }` in modern browsers.

The stylesheet is token-driven. The top of `css/dark-blend.css` defines a CSS variable palette (`--color-dark-*`, `--color-link`, `--status-*`, `--alert-*`, etc.) - override any of these in `custom.css` and the change cascades everywhere.

### Using a renamed or customised Blend theme

If you have cloned the Blend admin theme under a different folder name (for example, `modules/admin/mycompany-blend/`), the module treats it correctly on most pages by default. The CSS hook only refuses to load on pages where WHMCS reports a different, non-empty admin theme name - so as long as your custom theme is Blend-derived and you don't also have another admin theme activated, you should be fine without any edits.

If you specifically want to restrict the module to a named set of themes (e.g. "Blend and my two customised copies only"), edit `hooks.php` and find the two lines that read:

```php
if (!empty($vars['template']) && $vars['template'] !== 'blend') {
```

Replace each with:

```php
if (!empty($vars['template']) && !in_array($vars['template'], ['blend', 'YOUR_THEME_NAME'], true)) {
```

substituting your actual theme folder name for `YOUR_THEME_NAME`. Both occurrences need the same edit: one in the CSS-injection hook and one in the custom-css hook.

## Uninstall

1. Go to **Setup > Addon Modules**, find the addon, click **Deactivate**.
2. Delete the `modules/addons/dtdt/` folder.

That's it. The module adds no database tables, writes no files, and makes no changes to core WHMCS files. The only client-side artefact is the localStorage `dtdt-dark-mode` key, which becomes an orphan in each admin's browser after uninstall (it does no harm, but fastidious admins can clear it manually if they like).

## Credits and attribution

The dark theme CSS in this module is derived from, and stands on the shoulders of, the excellent work by **WevrLabs Hosting** on their [Blend Dark Mode](https://github.com/WevrLabs-Group/WHMCS-Blend-Admin-Theme-Dark-Mode) addon (GPL-3.0-licensed). The upstream project has become inactive (pull requests have been sitting unmerged for years), which is part of the motivation for this fork: to carry forward the Blend dark-theme work under active maintenance for current WHMCS versions.

The **dark/light toggle mechanism** was inspired by an open pull request from [@LoneStarDataRanch](https://github.com/LoneStarDataRanch) to the upstream WevrLabs project (August 2025, still unmerged at the time of this writing). The implementation in this module is a rewrite rather than a direct port (we use conditional stylesheet loading rather than body-class scoping, and the FOUC prevention runs in a synchronous pre-boot script), but the UX concept and localStorage key pattern are borrowed from that work.

This fork extends the original in a few directions:

- Rewritten CSS structure with a full CSS-variable token system (~67 tokens covering surfaces, borders, text, links, status colors, alerts, labels, banners, and legacy edge cases) so the whole theme can be reskinned by editing `:root`.
- Comprehensive coverage of WHMCS 8.13 admin surfaces including the Layers dashboard module, Lagom 2 client-area framework classes that bleed into admin context, staffboard module (including its ThickBox modal), inline-style status colors from admin PHP files (Selectize dropdowns, reports.php bgcolor rows, orders.php registrar/server panels, supportcenter.php ticket stats, systemsupportrequest.php help boxes, calendar.php popups).
- Rewritten hooks.php: modern jQuery, Capsule query builder instead of raw SQL, HTML escaping, undefined-variable safety, file-mtime cache busting.
- Dark/light toggle with system-preference defaulting and FOUC prevention.
- Dropped Lara theme support.

## License

GPL-3.0-or-later. See `LICENSE` for the full text.

The upstream **Blend Dark Mode** addon by WevrLabs Hosting is GPL-3.0-licensed, so this derivative inherits the same terms as required by the GPL. In plain English: you are free to use, modify, and redistribute this module (including in your own commercial WHMCS installation), but if you distribute a modified version to anyone else, you must license that modified version under GPL-3.0-or-later and make the source of your changes available to the recipients. You also need to retain the copyright notices for both Digitimber and WevrLabs in any redistribution.

Using the module on your own WHMCS server to serve customers does not count as redistribution, so day-to-day use imposes no obligations.
