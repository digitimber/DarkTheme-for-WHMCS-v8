<?php
/**
 * DigiTimber DarkTheme (DTDT) for WHMCS
 *
 * Dark mode for the WHMCS Blend admin theme, with an optional
 * per-user dark/light toggle (preference stored in browser localStorage).
 *
 * @package    DTDT
 * @author     Digitimber
 * @copyright  Copyright (c) 2026 Digitimber
 * @license    GPL-3.0-or-later
 * @link       https://www.digitimber.com
 *
 * Derived in part from:
 *   - "Blend Dark Mode" by WevrLabs Hosting (GPL-3.0)
 *     https://github.com/WevrLabs-Group/WHMCS-Blend-Admin-Theme-Dark-Mode
 *   - Toggle mechanism inspired by @LoneStarDataRanch's community contribution.
 * Full attribution in README.md.
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version. This program is distributed in the hope that
 * it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See
 * the GNU General Public License for more details. You should have
 * received a copy of the GNU General Public License along with this
 * program. If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Addon configuration.
 *
 * @return array
 */
function dtdt_config()
{
    return [
        'name'        => 'DigiTimber DarkTheme (DTDT) for WHMCS',
        'description' => 'Dark mode for the WHMCS Blend admin theme. '
                       . 'Optionally adds a per-admin dark/light toggle to the top nav bar.',
        'author'      => 'Digitimber',
        'language'    => 'english',
        'version'     => '1.0.0',
        'fields'      => [
            'datetime_enable' => [
                'FriendlyName' => 'Display date and time on nav bar',
                'Type'         => 'yesno',
                'Default'      => 'yes',
                'Description'  => 'Show current date and time in the admin top nav bar.',
            ],
            'ticketcount_enable' => [
                'FriendlyName' => 'Display open tickets count',
                'Type'         => 'yesno',
                'Default'      => 'yes',
                'Description'  => 'Show an animated badge with the count of tickets awaiting reply '
                                . 'in the admin top nav bar.',
            ],
            'toggle_enable' => [
                'FriendlyName' => 'Display light mode toggle',
                'Type'         => 'yesno',
                'Default'      => 'yes',
                'Description'  => 'Show a sun/moon toggle button in the admin top nav bar. '
                                . 'When enabled, each admin can choose their preference and '
                                . 'it will be remembered in their browser. First-time visitors '
                                . 'get the theme that matches their OS/browser preference. '
                                . 'When disabled, the dark theme is always active.',
            ],
        ],
    ];
}

/**
 * Activation hook. Runs when the admin clicks "Activate" in Setup > Addon Modules.
 *
 * @return array
 */
function dtdt_activate()
{
    return [
        'status'      => 'success',
        'description' => 'DigiTimber DarkTheme activated. Reload any admin page to see the dark UI.',
    ];
}

/**
 * Deactivation hook. Runs when the admin clicks "Deactivate" in Setup > Addon Modules.
 *
 * @return array
 */
function dtdt_deactivate()
{
    return [
        'status'      => 'success',
        'description' => 'DigiTimber DarkTheme deactivated. Reload any admin page to restore the default UI.',
    ];
}
