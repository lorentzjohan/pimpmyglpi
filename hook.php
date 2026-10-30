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

use GlpiPlugin\Pimpmyglpi\Filesystem;
use GlpiPlugin\Pimpmyglpi\ImageStore;
use GlpiPlugin\Pimpmyglpi\Runtime;
use GlpiPlugin\Pimpmyglpi\Settings;
use GlpiPlugin\Pimpmyglpi\ThemeCompiler;

/**
 * Plugin install process
 *
 * Only adds the missing settings: existing settings and images are kept on update.
 */
function plugin_pimpmyglpi_install(): bool
{
    Settings::install();

    // The theme is compiled again on the next page, by the web server user: the console may run
    // as another user (root), whose files the web server could not read with private permissions.
    Filesystem::remove(ThemeCompiler::getCssPath());
    Filesystem::remove(ThemeCompiler::getRuntimePath());
    Runtime::reset();

    return true;
}

/**
 * Plugin uninstall process: settings, images and compiled files are removed.
 */
function plugin_pimpmyglpi_uninstall(): bool
{
    Settings::uninstall();
    Filesystem::removeDirectory(ImageStore::getDirectory());
    Filesystem::removeDirectory(ThemeCompiler::getDirectory());
    Runtime::reset();

    return true;
}
