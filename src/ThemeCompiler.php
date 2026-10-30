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

use function Safe\json_encode;
use function Safe\preg_replace;

/**
 * Compiles the settings into the two files read at run time:
 *  - `theme.css`: the stylesheet added to every page (logos, colors, login page, custom CSS);
 *  - `runtime.json`: what `plugin_init` needs (title, favicon, forced palette, cache keys).
 *
 * Logos are replaced through the CSS variables GLPI already uses (`--glpi-logo`...),
 * so no GLPI file and no template is modified.
 */
final class ThemeCompiler
{
    public const RUNTIME_VERSION = 1;

    /** Replaced by the GLPI base path when the stylesheet is served. */
    public const BASE_PATH_PLACEHOLDER = '__PIMPMYGLPI_BASE_PATH__';

    private const FONTS = [
        'system' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'sans'   => 'Arial, "Helvetica Neue", Helvetica, sans-serif',
        'serif'  => 'Georgia, Cambria, "Times New Roman", serif',
    ];

    private const RADIUS = [
        'square'  => ['0', '0', '0', '0'],
        'rounded' => ['0.75rem', '0.5rem', '1rem', '1.25rem'],
    ];

    public static function getDirectory(): string
    {
        return GLPI_PLUGIN_DOC_DIR . '/pimpmyglpi';
    }

    public static function getCssPath(): string
    {
        return self::getDirectory() . '/theme.css';
    }

    public static function getRuntimePath(): string
    {
        return self::getDirectory() . '/runtime.json';
    }

    /**
     * Rebuilds both files from the given settings and returns the runtime data.
     *
     * @return array<string, mixed>
     */
    public static function compile(Settings $settings): array
    {
        $images = $settings->getImages();
        $css    = self::buildCss($settings, $images);

        $runtime = [
            'version'        => self::RUNTIME_VERSION,
            'app_name'       => $settings->get(Settings::APP_NAME),
            'forced_palette' => $settings->get(Settings::FORCED_PALETTE),
            'theme_color'    => $settings->get(Settings::PRIMARY_COLOR) ?: $settings->get(Settings::MENU_COLOR),
            'css'            => $css === '' ? '' : substr(sha1($css), 0, 16),
            'images'         => array_map(
                static fn(array $image): array => ['ext' => $image['ext'], 'mime' => $image['mime'], 'hash' => $image['hash']],
                $images,
            ),
        ];

        Filesystem::write(self::getCssPath(), $css);
        Filesystem::write(self::getRuntimePath(), json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $runtime;
    }

    /**
     * @param array<string, array{ext: string, mime: string, hash: string, width: int, height: int}> $images
     */
    public static function buildCss(Settings $settings, array $images): string
    {
        $blocks = [];

        // ---- Logos ---------------------------------------------------------------------
        // Each logo has a variant for dark and for light backgrounds; the one that matches the
        // actual background is used. A missing variant falls back to the other one.
        $menu_dark     = self::firstUrl($images, ImageSlots::LOGO_MENU, ImageSlots::LOGO_MENU_LIGHT);
        $menu_light    = self::firstUrl($images, ImageSlots::LOGO_MENU_LIGHT, ImageSlots::LOGO_MENU);
        $reduced_dark  = self::firstUrl($images, ImageSlots::LOGO_MENU_REDUCED, ImageSlots::LOGO_MENU_REDUCED_LIGHT);
        $reduced_light = self::firstUrl($images, ImageSlots::LOGO_MENU_REDUCED_LIGHT, ImageSlots::LOGO_MENU_REDUCED);
        $login_light   = self::firstUrl($images, ImageSlots::LOGO_LOGIN, ImageSlots::LOGO_LOGIN_DARK);
        $login_dark    = self::firstUrl($images, ImageSlots::LOGO_LOGIN_DARK, ImageSlots::LOGO_LOGIN);

        $root = [];
        // GLPI menus are dark in every palette.
        if ($menu_dark !== null) {
            $root['--glpi-logo'] = $menu_dark;
        }

        if ($reduced_dark !== null) {
            $root['--glpi-logo-reduced'] = $reduced_dark;
        }

        // Login page: GLPI shows --glpi-logo-dark-login on light palettes and
        // --glpi-logo-light-login on dark palettes. When the plugin sets the background,
        // its tone decides instead of the palette.
        $login_tone = self::getLoginBackgroundTone($settings, $images);
        if ($login_tone === null) {
            if ($login_light !== null) {
                $root['--glpi-logo-dark-login'] = $login_light;
            }

            if ($login_dark !== null) {
                $root['--glpi-logo-light-login'] = $login_dark;
            }
        } else {
            $login_logo = $login_tone === 'dark' ? $login_dark : $login_light;
            if ($login_logo !== null) {
                $root['--glpi-logo-dark-login']  = $login_logo;
                $root['--glpi-logo-light-login'] = $login_logo;
            } elseif ($login_tone === 'dark') {
                // GLPI's own logos: the light one on a dark background...
                $root['--glpi-logo-dark-login'] = 'var(--glpi-logo-light-login)';
            } else {
                // ... and the dark one on a light background.
                $root['--glpi-logo-light-login'] = 'var(--glpi-logo-dark-login)';
            }
        }

        if ($root !== []) {
            $blocks[] = self::rule(':root', $root);
        }

        // Light main menu (custom color): logos for light menus, or GLPI's dark logos.
        $menu_color = $settings->get(Settings::MENU_COLOR);
        if ($menu_color !== '' && self::isLight($menu_color)) {
            $blocks[] = self::rule(self::colorScope($settings), [
                '--glpi-logo'         => $menu_light ?? 'var(--glpi-logo-dark)',
                '--glpi-logo-reduced' => $reduced_light ?? 'var(--glpi-logo-dark-reduced)',
            ]);
        }

        $palette_logos = [];
        foreach (array_keys($images) as $slot) {
            $parsed = ImageSlots::parse($slot);
            if ($parsed === null || $parsed['palette'] === null) {
                continue;
            }

            $selector = sprintf(':root[data-glpi-theme="%s"]', $parsed['palette']);
            $url      = self::url($slot, $images);
            switch ($parsed['base']) {
                case ImageSlots::LOGO_MENU:
                    $palette_logos[$selector]['--glpi-logo'] = $url;
                    break;
                case ImageSlots::LOGO_MENU_REDUCED:
                    $palette_logos[$selector]['--glpi-logo-reduced'] = $url;
                    break;
                case ImageSlots::LOGO_LOGIN:
                    $palette_logos[$selector]['--glpi-logo-dark-login'] = $url;
                    $palette_logos[$selector]['--glpi-logo-light-login'] = $url;
                    break;
            }
        }

        foreach ($palette_logos as $selector => $declarations) {
            $blocks[] = self::rule($selector, $declarations);
        }

        $has_logo = false;
        foreach (array_keys($images) as $slot) {
            $base = ImageSlots::parse($slot)['base'] ?? '';
            if (!in_array($base, [ImageSlots::FAVICON, ImageSlots::LOGIN_BACKGROUND], true)) {
                $has_logo = true;
                break;
            }
        }

        if ($has_logo) {
            // Custom logos rarely have GLPI's exact ratio: fit them in GLPI's boxes.
            $blocks[] = self::rule('.page .glpi-logo, .navbar-brand .glpi-logo', [
                'background-size'     => 'contain !important',
                'background-position' => 'center !important',
            ]);
            $blocks[] = self::rule('.page-anonymous .glpi-logo', ['object-fit' => 'contain']);
        }

        // ---- Colors --------------------------------------------------------------------
        $colors  = [];
        $primary = $settings->get(Settings::PRIMARY_COLOR);
        if ($primary !== '') {
            $colors['--tblr-primary-rgb'] = self::rgb($primary);
            $colors['--tblr-primary']     = $primary;
            $colors['--tblr-primary-fg']  = self::isLight($primary) ? '#1e293b' : '#ffffff';
        }

        if ($menu_color !== '') {
            $fg = self::isLight($menu_color) ? '#1e293b' : '#f4f6fa';
            $colors['--glpi-mainmenu-bg']       = $menu_color;
            $colors['--glpi-mainmenu-fg']       = $fg;
            $colors['--glpi-mainmenu-fg-muted'] = sprintf('rgba(%s, 0.7)', self::rgb($fg));
        }

        if ($colors !== []) {
            $blocks[] = self::rule(self::colorScope($settings), $colors);
        }

        // ---- Shape and font ------------------------------------------------------------
        $style  = [];
        $radius = $settings->get(Settings::RADIUS);
        if (isset(self::RADIUS[$radius])) {
            [$base, $small, $large, $xl] = self::RADIUS[$radius];
            $style['--tblr-border-radius']    = $base;
            $style['--tblr-border-radius-sm'] = $small;
            $style['--tblr-border-radius-lg'] = $large;
            $style['--tblr-border-radius-xl'] = $xl;
        }

        $font = $settings->get(Settings::FONT);
        if (isset(self::FONTS[$font])) {
            $style['--tblr-font-sans-serif']   = self::FONTS[$font];
            $style['--tblr-body-font-family']  = self::FONTS[$font];
        }

        if ($style !== []) {
            $blocks[] = self::rule(':root[data-glpi-theme]', $style);
            if (isset(self::FONTS[$font])) {
                $blocks[] = self::rule('body', ['font-family' => self::FONTS[$font]]);
            }
        }

        // ---- Login page ----------------------------------------------------------------
        $login_bg_color = $settings->get(Settings::LOGIN_BG_COLOR);
        $has_bg_image   = isset($images[ImageSlots::LOGIN_BACKGROUND]);
        if ($has_bg_image || $login_bg_color !== '') {
            $background = [];
            if ($login_bg_color !== '') {
                $background['background-color'] = $login_bg_color . ' !important';
            }

            if ($has_bg_image) {
                $background['background-image']      = self::url(ImageSlots::LOGIN_BACKGROUND, $images) . ' !important';
                $background['background-size']       = 'cover !important';
                $background['background-position']   = 'center !important';
                $background['background-repeat']     = 'no-repeat !important';
                $background['background-attachment'] = 'fixed !important';
            }

            $blocks[] = self::rule('body.welcome-anonymous', $background);

            // Text around the login card must stay readable on the new background.
            $light_text = $login_tone !== 'light';
            $blocks[] = self::rule(
                '.page-anonymous .container-tight > .text-center, .page-anonymous .container-tight > .text-center a, .page-anonymous .container-tight > .text-center .text-muted',
                $light_text
                    ? ['color' => '#f8fafc !important', 'text-shadow' => '0 1px 3px rgba(0, 0, 0, 0.55)']
                    : ['color' => '#1e293b !important'],
            );
        }

        if ($settings->get(Settings::LOGIN_CARD) === 'glass') {
            $blocks[] = self::rule('.page-anonymous .main-content-card', [
                'background-color'        => 'rgba(255, 255, 255, 0.8) !important',
                'backdrop-filter'         => 'blur(14px) saturate(140%)',
                '-webkit-backdrop-filter' => 'blur(14px) saturate(140%)',
                'border'                  => '1px solid rgba(255, 255, 255, 0.35) !important',
                'box-shadow'              => '0 12px 40px rgba(0, 0, 0, 0.28) !important',
            ]);
            $blocks[] = self::rule(':root[data-glpi-theme-dark="1"] .page-anonymous .main-content-card', [
                'background-color' => 'rgba(17, 24, 39, 0.72) !important',
                'border-color'     => 'rgba(255, 255, 255, 0.12) !important',
            ]);
        }

        // ---- Custom CSS ----------------------------------------------------------------
        $custom = self::sanitizeCustomCss($settings->get(Settings::CUSTOM_CSS));
        if ($custom !== '') {
            $blocks[] = "/* Custom CSS */\n" . $custom;
        }

        if ($blocks === []) {
            return '';
        }

        return "/* Generated by PimpMyGLPI: edit it from Setup > Plugins > PimpMyGLPI. */\n\n"
            . implode("\n\n", $blocks) . "\n";
    }

    /**
     * Custom CSS is written as is in the stylesheet: only NUL bytes and an attempt to close
     * an HTML element are removed (the file is served as text/css, never inlined in HTML).
     */
    public static function sanitizeCustomCss(string $css): string
    {
        $css = str_replace("\0", '', $css);
        $css = preg_replace('#</\s*style[^>]*>?#i', '', $css);

        return trim(mb_substr($css, 0, Settings::CUSTOM_CSS_MAX_LENGTH));
    }

    public static function isLight(string $hex_color): bool
    {
        [$r, $g, $b] = array_map(intval(...), explode(',', self::rgb($hex_color)));
        // Relative luminance (WCAG)
        $channel = static function (int $value): float {
            $c = $value / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        return $luminance > 0.4;
    }

    /**
     * Tone of the login page background set by the plugin: the background image (its tone is
     * measured when it is uploaded), else the background color. Null: GLPI's own background,
     * which follows the palette.
     *
     * @param array<string, array{ext: string, mime: string, hash: string, width: int, height: int, tone?: string}> $images
     */
    public static function getLoginBackgroundTone(Settings $settings, array $images): ?string
    {
        $image_tone = $images[ImageSlots::LOGIN_BACKGROUND]['tone'] ?? '';
        if (isset($images[ImageSlots::LOGIN_BACKGROUND]) && in_array($image_tone, ['light', 'dark'], true)) {
            return $image_tone;
        }

        $color = $settings->get(Settings::LOGIN_BG_COLOR);
        if ($color !== '') {
            return self::isLight($color) ? 'light' : 'dark';
        }

        return isset($images[ImageSlots::LOGIN_BACKGROUND]) ? 'dark' : null;
    }

    /**
     * URL of the first slot that holds an image.
     *
     * @param array<string, array{ext: string, mime: string, hash: string, width: int, height: int}> $images
     */
    private static function firstUrl(array $images, string ...$slots): ?string
    {
        foreach ($slots as $slot) {
            if (isset($images[$slot])) {
                return self::url($slot, $images);
            }
        }

        return null;
    }

    private static function colorScope(Settings $settings): string
    {
        return $settings->get(Settings::COLORS_ON_DARK) === '1'
            ? ':root[data-glpi-theme]'
            : ':root[data-glpi-theme-dark="0"]';
    }

    private static function rgb(string $hex_color): string
    {
        $hex = ltrim($hex_color, '#');

        return implode(', ', [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))]);
    }

    /**
     * Absolute URL. A relative URL in a CSS variable would be resolved against the GLPI
     * stylesheet that uses the variable, not against this one. The base path of GLPI is only
     * known when the stylesheet is served, so a placeholder is written here.
     *
     * @param array<string, array{ext: string, mime: string, hash: string, width: int, height: int}> $images
     */
    private static function url(string $slot, array $images): string
    {
        return sprintf('url("%s/plugins/pimpmyglpi/asset/%s?h=%s")', self::BASE_PATH_PLACEHOLDER, $slot, $images[$slot]['hash']);
    }

    /**
     * @param array<string, string> $declarations
     */
    private static function rule(string $selector, array $declarations): string
    {
        $lines = [];
        foreach ($declarations as $property => $value) {
            $lines[] = sprintf('    %s: %s;', $property, $value);
        }

        return $selector . " {\n" . implode("\n", $lines) . "\n}";
    }
}
