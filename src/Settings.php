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
use JsonException;

use function Safe\preg_match;
use function Safe\preg_replace;

/**
 * Plugin settings, stored in `glpi_configs` (context "plugin:pimpmyglpi").
 *
 * The database is the source of truth. What pages need at run time is compiled into
 * small files by {@see ThemeCompiler}, so that no query is run on every request.
 */
final readonly class Settings
{
    public const CONTEXT = 'plugin:pimpmyglpi';

    public const APP_NAME          = 'app_name';

    public const FORCED_PALETTE    = 'forced_palette';

    public const PRIMARY_COLOR     = 'primary_color';

    public const MENU_COLOR        = 'menu_color';

    public const COLORS_ON_DARK    = 'colors_on_dark_palettes';

    public const RADIUS            = 'radius';

    public const FONT              = 'font';

    public const LOGIN_BG_COLOR    = 'login_background_color';

    public const LOGIN_CARD        = 'login_card';

    public const CUSTOM_CSS        = 'custom_css';

    public const IMAGES            = 'images';

    /** @var array<string, string> */
    public const DEFAULTS = [
        self::APP_NAME       => '',
        self::FORCED_PALETTE => '',
        self::PRIMARY_COLOR  => '',
        self::MENU_COLOR     => '',
        self::COLORS_ON_DARK => '0',
        self::RADIUS         => 'default',
        self::FONT           => 'default',
        self::LOGIN_BG_COLOR => '',
        self::LOGIN_CARD     => 'default',
        self::CUSTOM_CSS     => '',
        self::IMAGES         => '{}',
    ];

    /** Allowed values of the "choice" settings. */
    public const CHOICES = [
        self::RADIUS     => ['default', 'square', 'rounded'],
        self::FONT       => ['default', 'system', 'sans', 'serif'],
        self::LOGIN_CARD => ['default', 'glass'],
    ];

    public const APP_NAME_MAX_LENGTH   = 60;

    public const CUSTOM_CSS_MAX_LENGTH = 65535;

    /**
     * @param array<string, string> $values
     */
    private function __construct(private array $values) {}

    public static function load(): self
    {
        $values = [];
        foreach (Config::getConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS)) as $name => $value) {
            $values[(string) $name] = Scalar::toString($value);
        }

        return self::fromArray($values);
    }

    /**
     * @param array<string, string> $values
     */
    public static function fromArray(array $values): self
    {
        return new self(array_merge(self::DEFAULTS, array_intersect_key($values, self::DEFAULTS)));
    }

    public function get(string $name): string
    {
        return $this->values[$name] ?? self::DEFAULTS[$name] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * Stored images, by slot.
     *
     * @return array<string, array{ext: string, mime: string, hash: string, width: int, height: int, tone: string}>
     */
    public function getImages(): array
    {
        try {
            $images = json_decode($this->get(self::IMAGES), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($images)) {
            return [];
        }

        $valid = [];
        foreach ($images as $slot => $image) {
            if (
                is_string($slot) && ImageSlots::isValidSlot($slot)
                && is_array($image)
                && is_string($image['ext'] ?? null) && isset(ImageStore::MIME_TYPES[$image['ext']])
                && is_string($image['hash'] ?? null) && preg_match('/^[a-f0-9]{12,64}$/', $image['hash']) === 1
            ) {
                $valid[$slot] = [
                    'ext'    => $image['ext'],
                    'mime'   => ImageStore::MIME_TYPES[$image['ext']],
                    'hash'   => $image['hash'],
                    'width'  => Scalar::toInt($image['width'] ?? 0),
                    'height' => Scalar::toInt($image['height'] ?? 0),
                    'tone'   => in_array($image['tone'] ?? '', ['light', 'dark'], true) ? Scalar::toString($image['tone']) : '',
                ];
            }
        }

        ksort($valid);

        return $valid;
    }

    /**
     * Saves the given settings (unknown keys are ignored).
     *
     * @param array<string, string> $values
     */
    public static function save(array $values): void
    {
        Config::setConfigurationValues(self::CONTEXT, array_intersect_key($values, self::DEFAULTS));
    }

    public static function install(): void
    {
        $stored  = Config::getConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS));
        $missing = array_diff_key(self::DEFAULTS, $stored);
        if ($missing !== []) {
            Config::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    public static function uninstall(): void
    {
        Config::deleteConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS));
    }

    /**
     * "#RRGGBB" in lowercase, or null when the value is not a 6-digit hexadecimal color.
     * An empty value means "not set" and is returned as an empty string.
     */
    public static function normalizeColor(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }

    /**
     * Application name: single line, no control characters, bounded length.
     */
    public static function normalizeAppName(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

        return trim(mb_substr(trim($value), 0, self::APP_NAME_MAX_LENGTH));
    }

    public static function isValidChoice(string $setting, string $value): bool
    {
        return in_array($value, self::CHOICES[$setting] ?? [], true);
    }
}
