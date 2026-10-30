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

use Glpi\Plugin\Hooks;
use Glpi\UI\ThemeManager;
use JsonException;
use Throwable;

/**
 * Applies the compiled customization on every request, without any database query:
 * only `runtime.json` (a few hundred bytes) is read.
 */
final class Runtime
{
    /** @var array<string, mixed>|null */
    private static ?array $data = null;

    /**
     * Called from `plugin_init_pimpmyglpi()`. Must never throw: an exception raised during a
     * plugin init makes GLPI deactivate the plugin.
     */
    public static function boot(): void
    {
        try {
            $runtime = self::get();

            self::applyAppName($runtime);

            if (isCommandLine()) {
                return;
            }

            self::applyForcedPalette($runtime);
            self::registerAssets($runtime);
        } catch (Throwable $e) {
            global $PHPLOGGER;
            $PHPLOGGER->error(
                sprintf('PimpMyGLPI: customization skipped after an unexpected error: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    /**
     * Runtime data, compiled from the settings on first use when the file is missing.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $data = self::read();
        if ($data === null) {
            global $DB;
            $data = ($DB !== null && $DB->connected) ? ThemeCompiler::compile(Settings::load()) : [];
        }

        return self::$data = $data;
    }

    public static function reset(): void
    {
        self::$data = null;
    }

    /**
     * @return array{ext: string, mime: string, hash: string}|null
     */
    public static function getImage(string $slot): ?array
    {
        $image = self::get()['images'][$slot] ?? null;

        return is_array($image) ? $image : null;
    }

    public static function getAssetUrl(string $slot, string $hash): string
    {
        global $CFG_GLPI;

        return ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pimpmyglpi/asset/' . rawurlencode($slot) . '?h=' . rawurlencode($hash);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function read(): ?array
    {
        $path = ThemeCompiler::getRuntimePath();
        if (!is_file($path)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) && ($data['version'] ?? null) === ThemeCompiler::RUNTIME_VERSION ? $data : null;
    }

    /**
     * Browser tab title, two-factor authentication label and notification footer.
     *
     * @param array<string, mixed> $runtime
     */
    private static function applyAppName(array $runtime): void
    {
        global $CFG_GLPI;

        $app_name = $runtime['app_name'] ?? '';
        if (is_string($app_name) && $app_name !== '') {
            $CFG_GLPI['app_name'] = $app_name;
        }
    }

    /**
     * @param array<string, mixed> $runtime
     */
    private static function applyForcedPalette(array $runtime): void
    {
        $palette = $runtime['forced_palette'] ?? '';
        if (!is_string($palette) || $palette === '' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (ThemeManager::getInstance()->getTheme($palette) !== null) {
            $_SESSION['glpipalette'] = $palette;
        }
    }

    /**
     * @param array<string, mixed> $runtime
     */
    private static function registerAssets(array $runtime): void
    {
        global $PLUGIN_HOOKS;

        $css_hash = $runtime['css'] ?? '';
        if (is_string($css_hash) && $css_hash !== '') {
            $stylesheet = 'stylesheet?h=' . $css_hash;
            $PLUGIN_HOOKS[Hooks::ADD_CSS]['pimpmyglpi']                = [$stylesheet];
            $PLUGIN_HOOKS[Hooks::ADD_CSS_ANONYMOUS_PAGE]['pimpmyglpi'] = [$stylesheet];
        }

        $tags = [];
        $favicon = self::getImage(ImageSlots::FAVICON);
        if ($favicon !== null) {
            $tags[] = [
                'tag'        => 'link',
                'properties' => [
                    'rel'             => 'icon',
                    'type'            => $favicon['mime'],
                    'href'            => self::getAssetUrl(ImageSlots::FAVICON, $favicon['hash']),
                    'data-pimpmyglpi' => 'favicon',
                ],
            ];
            // The GLPI favicon is declared after plugins' tags: a small script removes it.
            $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['pimpmyglpi']                = ['js/favicon.js'];
            $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT_ANONYMOUS_PAGE]['pimpmyglpi'] = ['js/favicon.js'];
        }

        $theme_color = $runtime['theme_color'] ?? '';
        if (is_string($theme_color) && $theme_color !== '' && Settings::normalizeColor($theme_color) === $theme_color) {
            $tags[] = ['tag' => 'meta', 'properties' => ['name' => 'theme-color', 'content' => $theme_color]];
        }

        if ($tags !== []) {
            $PLUGIN_HOOKS[Hooks::ADD_HEADER_TAG]['pimpmyglpi']                = $tags;
            $PLUGIN_HOOKS[Hooks::ADD_HEADER_TAG_ANONYMOUS_PAGE]['pimpmyglpi'] = $tags;
        }
    }
}
