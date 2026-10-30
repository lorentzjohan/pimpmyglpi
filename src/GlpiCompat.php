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

namespace GlpiPlugin\Pimpmyglpi;

use Config;
use Session;

/**
 * Small compatibility layer between GLPI 11.x and GLPI 12.x.
 *
 *  - CSRF: GLPI 11 validates a `_glpi_csrf_token` field on every POST, GLPI 12 relies on the
 *    `Sec-Fetch-Site` / `Origin` headers and deprecates the token API;
 *  - re-authentication of sensitive pages (Config itemtype): GLPI 12 only.
 */
final class GlpiCompat
{
    public static function usesCsrfTokens(): bool
    {
        return version_compare(GLPI_VERSION, '12.0.0-dev', '<');
    }

    /**
     * CSRF token to put in a form, or an empty string when GLPI does not use tokens anymore.
     */
    public static function getCsrfToken(): string
    {
        if (!self::usesCsrfTokens()) {
            return '';
        }

        // Deprecated in GLPI 12, only called on GLPI 11.
        // @phpstan-ignore staticMethod.deprecated
        return Scalar::toString(Session::getNewCSRFToken());
    }

    /**
     * Requires a recent re-authentication, like GLPI 12 does for its own setup pages.
     */
    public static function checkConfigReAuthentication(): void
    {
        // Always true on GLPI 12, always false on GLPI 11.
        // @phpstan-ignore function.alreadyNarrowedType, function.impossibleType
        if (method_exists(Config::class, 'checkReAuthenticationOrRedirect')) {
            Config::checkReAuthenticationOrRedirect();
        }
    }
}
