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
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/lorentzjohan
 * -------------------------------------------------------------------------
 */

$current_plugin_folder = basename(dirname(__DIR__));

require __DIR__ . '/../../../tests/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';

if (!Plugin::isPluginActive($current_plugin_folder)) {
    throw new RuntimeException(sprintf('Plugin %s is not active in the test database', $current_plugin_folder));
}
