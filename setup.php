<?php

/**
 * -------------------------------------------------------------------------
 * PimpMyGLPI plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * This file is part of PimpMyGLPI.
 *
 * PimpMyGLPI is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PimpMyGLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PimpMyGLPI. If not, see <https://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 LORENTZ Johan
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/lorentzjohan
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Pimpmyglpi\Runtime;

/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define('PLUGIN_PIMPMYGLPI_VERSION', '1.1.0');

// Minimal GLPI version, inclusive: GLPI 11.x
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define('PLUGIN_PIMPMYGLPI_MIN_GLPI_VERSION', '11.0.0');

// Maximum GLPI version, exclusive: every GLPI 12.x release is accepted
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define('PLUGIN_PIMPMYGLPI_MAX_GLPI_VERSION', '13.0.0');

// Minimal PHP version, inclusive (same as GLPI 11; GLPI 12 itself requires PHP 8.3)
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define('PLUGIN_PIMPMYGLPI_MIN_PHP_VERSION', '8.2.0');

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * Runs on every request without any database query: the customization is compiled
 * into small files when the settings are saved.
 */
function plugin_init_pimpmyglpi(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['pimpmyglpi'] = 'config';

    Runtime::boot();
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array{
 *      name: string,
 *      version: string,
 *      author: string,
 *      license: string,
 *      homepage: string,
 *      requirements: array{
 *          glpi: array{min: string, max: string},
 *          php: array{min: string}
 *      }
 * }
 */
function plugin_version_pimpmyglpi(): array
{
    return [
        'name'         => 'PimpMyGLPI',
        'version'      => PLUGIN_PIMPMYGLPI_VERSION,
        'author'       => 'LORENTZ Johan',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/lorentzjohan',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_PIMPMYGLPI_MIN_GLPI_VERSION,
                'max' => PLUGIN_PIMPMYGLPI_MAX_GLPI_VERSION,
            ],
            'php'  => [
                'min' => PLUGIN_PIMPMYGLPI_MIN_PHP_VERSION,
            ],
        ],
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONAL
 */
function plugin_pimpmyglpi_check_prerequisites(): bool
{
    foreach (['fileinfo', 'simplexml', 'libxml'] as $extension) {
        if (!extension_loaded($extension)) {
            echo sprintf('The PHP "%s" extension is required by the PimpMyGLPI plugin.', $extension);
            return false;
        }
    }

    return true;
}

/**
 * Check configuration process
 * OPTIONAL
 *
 * @param bool $verbose Whether to display message on failure. Defaults to false.
 */
function plugin_pimpmyglpi_check_config(bool $verbose = false): bool
{
    return true;
}
