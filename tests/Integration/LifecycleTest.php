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

namespace GlpiPlugin\Pimpmyglpi\Tests\Integration;

use Config;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Pimpmyglpi\ImageSlots;
use GlpiPlugin\Pimpmyglpi\ImageStore;
use GlpiPlugin\Pimpmyglpi\Settings;
use GlpiPlugin\Pimpmyglpi\ThemeCompiler;
use Plugin;

/**
 * Plugin integration in GLPI: hooks, settings storage, update and uninstall.
 */
final class LifecycleTest extends IntegrationTestCase
{
    public function testConfigurationPageIsRegistered(): void
    {
        global $PLUGIN_HOOKS;

        $this->assertSame('config', $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['pimpmyglpi'] ?? null);
    }

    public function testSettingsAreStoredInGlpi(): void
    {
        Settings::save([Settings::APP_NAME => 'Helpdesk', Settings::RADIUS => 'square']);

        $settings = Settings::load();
        $this->assertSame('Helpdesk', $settings->get(Settings::APP_NAME));
        $this->assertSame('square', $settings->get(Settings::RADIUS));
    }

    public function testUpdateKeepsTheSettingsAndAddsTheNewOnes(): void
    {
        Config::deleteConfigurationValues(Settings::CONTEXT, [Settings::LOGIN_CARD]);
        Settings::save([Settings::APP_NAME => 'Helpdesk']);

        Settings::install();

        $stored = Config::getConfigurationValues(Settings::CONTEXT);
        $this->assertSame('Helpdesk', $stored[Settings::APP_NAME]);
        $this->assertSame(Settings::DEFAULTS[Settings::LOGIN_CARD], $stored[Settings::LOGIN_CARD]);
    }

    public function testUninstallRemovesTheSettingsAndTheFiles(): void
    {
        require_once Plugin::getPhpDir('pimpmyglpi') . '/hook.php';

        $this->storeExampleImages([ImageSlots::LOGO_MENU => 'logo-PimpMyGLPI-250-white.png']);
        ThemeCompiler::compile(Settings::load());
        $this->assertFileExists(ThemeCompiler::getCssPath());

        $this->assertTrue(plugin_pimpmyglpi_uninstall());

        $this->assertSame([], Config::getConfigurationValues(Settings::CONTEXT));
        $this->assertFileDoesNotExist(ThemeCompiler::getCssPath());
        $this->assertFileDoesNotExist(ThemeCompiler::getRuntimePath());
        $this->assertDirectoryDoesNotExist(ImageStore::getDirectory());

        // Back to the installed state for the next tests
        $this->assertTrue(plugin_pimpmyglpi_install());
        $this->assertCount(count(Settings::DEFAULTS), Config::getConfigurationValues(Settings::CONTEXT));
    }
}
