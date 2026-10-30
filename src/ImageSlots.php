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

use function Safe\preg_match;

/**
 * Image "slots": every image the plugin can replace, with its constraints.
 *
 * Palette-specific logos use the slot name "palette.<palette key>.<logo slot>".
 */
final class ImageSlots
{
    public const FAVICON           = 'favicon';
    /** Main menu logo for dark menus (light logo): GLPI menus are dark, this is the default one. */
    public const LOGO_MENU               = 'logo_menu';
    /** Main menu logo for light menus (dark logo): used when the main menu color is light. */
    public const LOGO_MENU_LIGHT         = 'logo_menu_light';
    public const LOGO_MENU_REDUCED       = 'logo_menu_reduced';
    public const LOGO_MENU_REDUCED_LIGHT = 'logo_menu_reduced_light';
    /** Login page logo for a light background (dark logo). */
    public const LOGO_LOGIN              = 'logo_login';
    /** Login page logo for a dark background (light logo). */
    public const LOGO_LOGIN_DARK         = 'logo_login_dark';
    public const LOGIN_BACKGROUND        = 'login_background';

    /** Logos that can be replaced for a single palette. */
    public const PALETTE_LOGOS = [self::LOGO_MENU, self::LOGO_MENU_REDUCED, self::LOGO_LOGIN];

    private const LOGO_DEFINITION = [
        'types'    => ['png', 'webp', 'svg', 'jpg'],
        'max_size' => 2 * 1024 * 1024,
        'max_px'   => 4096,
    ];

    /** @var array<string, array{types: list<string>, max_size: int, max_px: int}> */
    private const DEFINITIONS = [
        self::FAVICON           => ['types' => ['ico', 'png', 'svg'], 'max_size' => 512 * 1024, 'max_px' => 1024],
        self::LOGO_MENU               => self::LOGO_DEFINITION,
        self::LOGO_MENU_LIGHT         => self::LOGO_DEFINITION,
        self::LOGO_MENU_REDUCED       => self::LOGO_DEFINITION,
        self::LOGO_MENU_REDUCED_LIGHT => self::LOGO_DEFINITION,
        self::LOGO_LOGIN              => self::LOGO_DEFINITION,
        self::LOGO_LOGIN_DARK         => self::LOGO_DEFINITION,
        self::LOGIN_BACKGROUND        => ['types' => ['jpg', 'png', 'webp'], 'max_size' => 8 * 1024 * 1024, 'max_px' => 10000],
    ];

    /**
     * @return list<string>
     */
    public static function getGlobalSlots(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function paletteSlot(string $palette, string $logo): string
    {
        return 'palette.' . $palette . '.' . $logo;
    }

    /**
     * @return array{base: string, palette: ?string}|null
     */
    public static function parse(string $slot): ?array
    {
        if (isset(self::DEFINITIONS[$slot])) {
            return ['base' => $slot, 'palette' => null];
        }
        if (
            preg_match('/^palette\.([a-z0-9_-]{1,64})\.([a-z_]+)$/', $slot, $matches) === 1
            && in_array($matches[2], self::PALETTE_LOGOS, true)
        ) {
            return ['base' => $matches[2], 'palette' => $matches[1]];
        }

        return null;
    }

    public static function isValidSlot(string $slot): bool
    {
        return self::parse($slot) !== null;
    }

    public static function isValidPaletteKey(string $key): bool
    {
        return preg_match('/^[a-z0-9_-]{1,64}$/', $key) === 1;
    }

    /**
     * @return array{types: list<string>, max_size: int, max_px: int}
     */
    public static function getDefinition(string $slot): array
    {
        $parsed = self::parse($slot);
        if ($parsed === null) {
            throw new \InvalidArgumentException(sprintf('Unknown image slot "%s".', $slot));
        }

        return self::DEFINITIONS[$parsed['base']];
    }

    /**
     * Value for the `accept` attribute of the file input.
     */
    public static function getAcceptAttribute(string $slot): string
    {
        return implode(',', array_map(
            static fn(string $ext): string => '.' . $ext . ($ext === 'jpg' ? ',.jpeg' : ''),
            self::getDefinition($slot)['types']
        ));
    }
}
