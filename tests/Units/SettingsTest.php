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

namespace GlpiPlugin\Pimpmyglpi\Tests\Units;

use GlpiPlugin\Pimpmyglpi\ImageSlots;
use GlpiPlugin\Pimpmyglpi\ImageStore;
use GlpiPlugin\Pimpmyglpi\Settings;
use GlpiPlugin\Pimpmyglpi\ThemeCompiler;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    public function testColors(): void
    {
        $this->assertSame('#7c3aed', Settings::normalizeColor(' #7C3AED '));
        $this->assertSame('', Settings::normalizeColor(''));
        $this->assertNull(Settings::normalizeColor('#7c3'));
        $this->assertNull(Settings::normalizeColor('red'));
        $this->assertNull(Settings::normalizeColor('#7c3aed;}body{display:none'));
        $this->assertTrue(ThemeCompiler::isLight('#e2e8f0'));
        $this->assertFalse(ThemeCompiler::isLight('#1e1b4b'));
    }

    public function testAppName(): void
    {
        $this->assertSame('My helpdesk', Settings::normalizeAppName("  My\nhelpdesk  "));
        $this->assertSame(Settings::APP_NAME_MAX_LENGTH, mb_strlen(Settings::normalizeAppName(str_repeat('é', 200))));
    }

    public function testSlots(): void
    {
        $this->assertTrue(ImageSlots::isValidSlot('logo_menu'));
        $this->assertTrue(ImageSlots::isValidSlot('palette.auror.logo_menu'));
        $this->assertFalse(ImageSlots::isValidSlot('palette.auror.favicon'));
        $this->assertFalse(ImageSlots::isValidSlot('palette.../.logo_menu'));
        $this->assertFalse(ImageSlots::isValidSlot('../../config/config_db'));
        $this->assertSame(['base' => 'logo_login', 'palette' => 'darker'], ImageSlots::parse('palette.darker.logo_login'));
    }

    public function testSvgSanitizer(): void
    {
        $this->assertTrue(ImageStore::isSafeSvg('<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g"/></defs><rect fill="url(#g)"/></svg>'));
        $this->assertFalse(ImageStore::isSafeSvg('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'));
        $this->assertFalse(ImageStore::isSafeSvg('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'));
        $this->assertFalse(ImageStore::isSafeSvg('<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><rect/></a></svg>'));
        $this->assertFalse(ImageStore::isSafeSvg('<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/x.png"/></svg>'));
        $this->assertFalse(ImageStore::isSafeSvg('<!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'));
        $this->assertFalse(ImageStore::isSafeSvg('<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(https://example.com/a.css);</style></svg>'));
        $this->assertFalse(ImageStore::isSafeSvg('<html><body>not an svg</body></html>'));
    }

    public function testCssGeneration(): void
    {
        $this->assertSame('', ThemeCompiler::buildCss(Settings::fromArray([]), []));

        $image = ['ext' => 'png', 'mime' => 'image/png', 'hash' => 'abcdef0123456789', 'width' => 250, 'height' => 138, 'tone' => 'dark'];
        $css   = ThemeCompiler::buildCss(
            Settings::fromArray([Settings::PRIMARY_COLOR => '#7c3aed', Settings::RADIUS => 'square']),
            ['logo_menu' => $image, 'palette.auror.logo_login' => $image],
        );
        $this->assertStringContainsString('--glpi-logo: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/logo_menu?h=abcdef0123456789")', $css);
        $this->assertStringContainsString(':root[data-glpi-theme="auror"]', $css);
        $this->assertStringContainsString(':root[data-glpi-theme-dark="0"]', $css);
        $this->assertStringContainsString('--tblr-primary-rgb: 124, 58, 237', $css);
        $this->assertStringContainsString('--tblr-border-radius: 0', $css);
    }

    /**
     * @return array{ext: string, mime: string, hash: string, width: int, height: int, tone: string}
     */
    private function image(string $hash, string $tone = ''): array
    {
        return ['ext' => 'png', 'mime' => 'image/png', 'hash' => $hash, 'width' => 250, 'height' => 138, 'tone' => $tone];
    }

    public function testLoginLogoFollowsTheBackground(): void
    {
        $logos = [
            ImageSlots::LOGO_LOGIN      => $this->image('aaaaaaaaaaaaaaaa'),
            ImageSlots::LOGO_LOGIN_DARK => $this->image('bbbbbbbbbbbbbbbb'),
        ];

        // No background set by the plugin: GLPI's palette-based choice.
        $css = ThemeCompiler::buildCss(Settings::fromArray([]), $logos);
        $this->assertStringContainsString('--glpi-logo-dark-login: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/logo_login?h=aaaaaaaaaaaaaaaa")', $css);
        $this->assertStringContainsString('--glpi-logo-light-login: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/logo_login_dark?h=bbbbbbbbbbbbbbbb")', $css);

        // Dark background image: the logo for a dark background everywhere.
        $images = $logos + [ImageSlots::LOGIN_BACKGROUND => $this->image('cccccccccccccccc', 'dark')];
        $this->assertSame('dark', ThemeCompiler::getLoginBackgroundTone(Settings::fromArray([]), $images));
        $css = ThemeCompiler::buildCss(Settings::fromArray([]), $images);
        $this->assertStringContainsString('--glpi-logo-dark-login: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/logo_login_dark?h=bbbbbbbbbbbbbbbb")', $css);
        $this->assertStringNotContainsString('asset/logo_login?h=', $css);

        // Light background color: the logo for a light background everywhere.
        $settings = Settings::fromArray([Settings::LOGIN_BG_COLOR => '#f1f5f9']);
        $this->assertSame('light', ThemeCompiler::getLoginBackgroundTone($settings, $logos));
        $css = ThemeCompiler::buildCss($settings, $logos);
        $this->assertStringContainsString('--glpi-logo-light-login: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/logo_login?h=aaaaaaaaaaaaaaaa")', $css);
        $this->assertStringNotContainsString('asset/logo_login_dark?h=', $css);

        // A single variant is used for both backgrounds.
        $css = ThemeCompiler::buildCss(Settings::fromArray([]), [ImageSlots::LOGO_LOGIN_DARK => $this->image('bbbbbbbbbbbbbbbb')]);
        $this->assertStringContainsString('--glpi-logo-dark-login: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/logo_login_dark?h=bbbbbbbbbbbbbbbb")', $css);
    }

    public function testLightMenuUsesTheLogosForLightMenus(): void
    {
        $settings = Settings::fromArray([Settings::MENU_COLOR => '#e2e8f0']);

        $css = ThemeCompiler::buildCss($settings, [
            ImageSlots::LOGO_MENU       => $this->image('aaaaaaaaaaaaaaaa'),
            ImageSlots::LOGO_MENU_LIGHT => $this->image('dddddddddddddddd'),
        ]);
        $this->assertStringContainsString(':root[data-glpi-theme-dark="0"] {' . "\n" . '    --glpi-logo: url("' . ThemeCompiler::BASE_PATH_PLACEHOLDER . '/plugins/pimpmyglpi/asset/logo_menu_light?h=dddddddddddddddd")', $css);

        // Without any custom logo: GLPI's dark logos.
        $css = ThemeCompiler::buildCss($settings, []);
        $this->assertStringContainsString('--glpi-logo: var(--glpi-logo-dark)', $css);
        $this->assertStringContainsString('--glpi-logo-reduced: var(--glpi-logo-dark-reduced)', $css);
    }

    public function testCustomCssCannotCloseAnElement(): void
    {
        $this->assertSame('a{color:red}', ThemeCompiler::sanitizeCustomCss("</style>a{color:red}\0"));
    }
}
