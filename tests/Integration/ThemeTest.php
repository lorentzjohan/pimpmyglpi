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

use GlpiPlugin\Pimpmyglpi\ImageSlots;
use GlpiPlugin\Pimpmyglpi\Runtime;
use GlpiPlugin\Pimpmyglpi\Settings;
use GlpiPlugin\Pimpmyglpi\ThemeCompiler;

use function Safe\file_get_contents;

/**
 * Compiled theme (stylesheet and runtime data) and what GLPI reads on every page.
 */
final class ThemeTest extends IntegrationTestCase
{
    public function testCompiledThemeIsWrittenAndReadBack(): void
    {
        $images = $this->storeExampleImages([
            ImageSlots::LOGO_MENU => 'logo-PimpMyGLPI-250-white.png',
            ImageSlots::FAVICON   => 'favicon.ico',
        ]);
        Settings::save([
            Settings::APP_NAME       => 'Helpdesk Example',
            Settings::PRIMARY_COLOR  => '#7c3aed',
            Settings::FORCED_PALETTE => 'darker',
        ]);

        $runtime = ThemeCompiler::compile(Settings::load());

        $css_path = ThemeCompiler::getCssPath();
        $this->assertFileExists($css_path);
        $this->assertSame(0o640, self::permissions($css_path));
        $this->assertSame(0o640, self::permissions(ThemeCompiler::getRuntimePath()));

        $css = file_get_contents($css_path);
        $this->assertStringContainsString('--tblr-primary: #7c3aed', $css);
        $this->assertStringContainsString(
            '--glpi-logo: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/logo_menu?h=' . $images[ImageSlots::LOGO_MENU]['hash'] . '")',
            $css,
        );

        // What every page reads, without any database query
        Runtime::reset();
        $this->assertSame($runtime, Runtime::get());
        $this->assertSame('Helpdesk Example', Runtime::getString('app_name'));
        $this->assertSame('darker', Runtime::getString('forced_palette'));
        $this->assertSame(
            ['ext' => 'ico', 'mime' => 'image/x-icon', 'hash' => $images[ImageSlots::FAVICON]['hash']],
            Runtime::getImage(ImageSlots::FAVICON),
        );
        $this->assertNull(Runtime::getImage(ImageSlots::LOGIN_BACKGROUND));
    }

    public function testLoginLogoFollowsTheBackgroundImage(): void
    {
        $images = $this->storeExampleImages([
            ImageSlots::LOGO_LOGIN       => 'logo-PimpMyGLPI-250-black.png',
            ImageSlots::LOGO_LOGIN_DARK  => 'logo-PimpMyGLPI-250-white.png',
            ImageSlots::LOGIN_BACKGROUND => 'login-background.jpg',
        ]);
        ThemeCompiler::compile(Settings::load());

        // Dark background image: the logo for a dark background on every palette.
        $css = file_get_contents(ThemeCompiler::getCssPath());
        $dark_logo = 'logo_login_dark?h=' . $images[ImageSlots::LOGO_LOGIN_DARK]['hash'];
        $this->assertStringContainsString('--glpi-logo-dark-login: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/' . $dark_logo, $css);
        $this->assertStringContainsString('--glpi-logo-light-login: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/' . $dark_logo, $css);
    }

    public function testDefaultSettingsKeepTheGlpiAppearance(): void
    {
        $runtime = ThemeCompiler::compile(Settings::load());

        $this->assertSame('', file_get_contents(ThemeCompiler::getCssPath()));
        $this->assertSame('', $runtime['css']);
        $this->assertSame('', $runtime['app_name']);
    }
}
