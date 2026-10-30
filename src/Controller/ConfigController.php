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

namespace GlpiPlugin\Pimpmyglpi\Controller;

use Config;
use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use Glpi\UI\ThemeManager;
use GlpiPlugin\Pimpmyglpi\GlpiCompat;
use GlpiPlugin\Pimpmyglpi\ImageSlots;
use GlpiPlugin\Pimpmyglpi\ImageStore;
use GlpiPlugin\Pimpmyglpi\ImageValidationException;
use GlpiPlugin\Pimpmyglpi\Runtime;
use GlpiPlugin\Pimpmyglpi\Settings;
use GlpiPlugin\Pimpmyglpi\ThemeCompiler;
use Plugin;
use Session;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Configuration page: /plugins/pimpmyglpi/config
 *
 * Same protections as the GLPI setup pages: "Setup > General" update right, recent
 * re-authentication (GLPI 12), CSRF checked by GLPI (form token on GLPI 11, headers on GLPI 12).
 */
final class ConfigController extends AbstractController
{
    /** Example files shipped in public/examples, applied by the "Apply the examples" button. */
    private const EXAMPLES = [
        ImageSlots::FAVICON           => 'favicon.ico',
        ImageSlots::LOGO_MENU         => 'logo-PimpMyGLPI-250-white.png',
        ImageSlots::LOGO_MENU_REDUCED => 'logo-P-100-white.png',
        // The example background is dark: light logo on the login page.
        ImageSlots::LOGO_LOGIN        => 'logo-PimpMyGLPI-250-white.png',
        ImageSlots::LOGO_LOGIN_DARK   => 'logo-PimpMyGLPI-250-white.png',
        ImageSlots::LOGIN_BACKGROUND  => 'login-background.jpg',
    ];

    /** GLPI images shown as "current" when a slot is not customized. */
    private const DEFAULT_IMAGES = [
        ImageSlots::FAVICON           => '/pics/favicon.ico',
        ImageSlots::LOGO_MENU         => '/pics/logos/logo-GLPI-100-white.png',
        ImageSlots::LOGO_MENU_REDUCED => '/pics/logos/logo-G-100-white.png',
        ImageSlots::LOGO_LOGIN        => '/pics/logos/logo-GLPI-250-black.png',
        ImageSlots::LOGO_LOGIN_DARK   => '/pics/logos/logo-GLPI-250-white.png',
    ];

    #[Route('/config', name: 'config', methods: ['GET'])]
    #[SecurityStrategy(Firewall::STRATEGY_ADMIN_ACCESS)]
    public function show(Request $request): Response
    {
        self::checkAccess();

        $settings = Settings::load();
        $images   = $settings->getImages();
        $palettes = self::getPalettes();

        $global_slots = [];
        foreach (self::getSlotTexts() as $slot => [$label, $help]) {
            $global_slots[] = [
                'slot'    => $slot,
                'label'   => $label,
                'help'    => $help,
                'formats' => strtoupper(implode(', ', ImageSlots::getDefinition($slot)['types'])),
                'accept'  => ImageSlots::getAcceptAttribute($slot),
                'image'   => self::describeImage($slot, $images),
                'dark'    => in_array($slot, [ImageSlots::LOGO_MENU, ImageSlots::LOGO_MENU_REDUCED, ImageSlots::LOGO_LOGIN_DARK], true),
                'default' => self::DEFAULT_IMAGES[$slot] ?? null,
            ];
        }

        $palette_images = [];
        foreach ($palettes as $key => $palette) {
            $logos = [];
            foreach (ImageSlots::PALETTE_LOGOS as $logo) {
                $logos[$logo] = self::describeImage(ImageSlots::paletteSlot($key, $logo), $images);
            }
            if (array_filter($logos) !== []) {
                $palette_images[] = ['key' => $key, 'name' => $palette['name'], 'dark' => $palette['dark'], 'logos' => $logos];
            }
        }

        $examples_url = $request->getBasePath() . '/plugins/pimpmyglpi/examples/';

        return $this->render('@pimpmyglpi/config.html.twig', [
            'form_url'       => self::getUrl($request),
            'settings'       => $settings->toArray(),
            'choices'        => self::getChoiceLabels(),
            'palettes'       => $palettes,
            'global_slots'   => $global_slots,
            'palette_logos'  => ImageSlots::PALETTE_LOGOS,
            'palette_images' => $palette_images,
            'logo_labels'    => self::getPaletteLogoLabels(),
            'logo_accept'    => ImageSlots::getAcceptAttribute(ImageSlots::LOGO_MENU),
            'examples_url'   => $examples_url,
            'base_path'      => $request->getBasePath(),
            'examples'       => [
                'logo-PimpMyGLPI-250-white.png', 'logo-PimpMyGLPI-250-black.png', 'logo-PimpMyGLPI-250-grey.png',
                'logo-PimpMyGLPI-100-white.png', 'logo-PimpMyGLPI-100-black.png', 'logo-PimpMyGLPI-100-grey.png',
                'logo-P-100-white.png', 'logo-P-100-black.png', 'logo-P-100-grey.png',
                'favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'login-background.jpg',
            ],
            'upload_limit'   => ini_get('upload_max_filesize') ?: '',
            'csrf_token'     => GlpiCompat::getCsrfToken(),
        ]);
    }

    #[Route('/config', name: 'config_update', methods: ['POST'])]
    #[SecurityStrategy(Firewall::STRATEGY_ADMIN_ACCESS)]
    public function update(Request $request): Response
    {
        self::checkAccess();

        $input    = $request->request->all();
        $settings = Settings::load();
        $images   = $settings->getImages();
        $palettes = self::getPalettes();
        $errors   = [];

        // ---- Text and choice settings ----------------------------------------------------
        $values = [
            Settings::APP_NAME       => Settings::normalizeAppName(self::string($input, Settings::APP_NAME)),
            Settings::COLORS_ON_DARK => self::bool($input, Settings::COLORS_ON_DARK) ? '1' : '0',
            Settings::CUSTOM_CSS     => ThemeCompiler::sanitizeCustomCss(self::string($input, Settings::CUSTOM_CSS)),
        ];

        $palette = self::string($input, Settings::FORCED_PALETTE);
        if ($palette !== '' && !isset($palettes[$palette])) {
            $errors[] = __('Unknown palette.', 'pimpmyglpi');
        }
        $values[Settings::FORCED_PALETTE] = $palette;

        foreach (array_keys(Settings::CHOICES) as $setting) {
            $value = self::string($input, $setting) ?: 'default';
            if (!Settings::isValidChoice($setting, $value)) {
                $errors[] = __('Invalid value.', 'pimpmyglpi');
                $value = 'default';
            }
            $values[$setting] = $value;
        }

        foreach ([Settings::PRIMARY_COLOR, Settings::MENU_COLOR, Settings::LOGIN_BG_COLOR] as $setting) {
            $color = self::bool($input, $setting . '_enabled') ? Settings::normalizeColor(self::string($input, $setting)) : '';
            if ($color === null) {
                $errors[] = __('Invalid color: use the #RRGGBB format.', 'pimpmyglpi');
                $color = '';
            }
            $values[$setting] = $color;
        }

        if (mb_strlen(self::string($input, Settings::CUSTOM_CSS)) > Settings::CUSTOM_CSS_MAX_LENGTH) {
            $errors[] = sprintf(__('The custom CSS is too long: %d characters at most.', 'pimpmyglpi'), Settings::CUSTOM_CSS_MAX_LENGTH);
        }

        // ---- Images: everything is validated before anything is written ----------------
        $to_delete = [];
        foreach ((array) ($input['delete'] ?? []) as $slot) {
            if (is_string($slot) && isset($images[$slot])) {
                $to_delete[] = $slot;
            }
        }

        $uploads = [];
        foreach (ImageSlots::getGlobalSlots() as $slot) {
            $uploads[$slot] = $request->files->get($slot);
        }
        $palette_key = self::string($input, 'palette_key');
        foreach (ImageSlots::PALETTE_LOGOS as $logo) {
            $file = $request->files->get('palette_' . $logo);
            if ($file instanceof UploadedFile) {
                if (!isset($palettes[$palette_key])) {
                    $errors[] = __('Choose the palette the logos are for.', 'pimpmyglpi');
                    break;
                }
                $uploads[ImageSlots::paletteSlot($palette_key, $logo)] = $file;
            }
        }

        $validated = [];
        $labels    = self::getSlotTexts();
        foreach ($uploads as $slot => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $parsed = ImageSlots::parse($slot);
            $label  = ($labels[$parsed['base'] ?? ''] ?? [$slot])[0];
            if (($parsed['palette'] ?? null) !== null) {
                $label .= ' (' . ($palettes[$parsed['palette']]['name'] ?? $parsed['palette']) . ')';
            }
            if (!$file->isValid()) {
                $errors[] = sprintf(__('%1$s: the upload failed (%2$s).', 'pimpmyglpi'), $label, self::uploadError($file->getError()));
                continue;
            }
            try {
                $validated[$slot] = [$file->getPathname(), ImageStore::validateFile($slot, $file->getPathname())];
            } catch (ImageValidationException $e) {
                $errors[] = sprintf('%s: %s', $label, $e->getMessage());
            }
        }

        if ($errors !== []) {
            foreach (array_unique($errors) as $error) {
                Session::addMessageAfterRedirect(htmlescape($error), false, ERROR);
            }
            Session::addMessageAfterRedirect(__s('Nothing was saved.', 'pimpmyglpi'), false, ERROR);

            return new RedirectResponse(self::getUrl($request));
        }

        try {
            foreach ($to_delete as $slot) {
                ImageStore::delete($slot);
                unset($images[$slot]);
            }
            foreach ($validated as $slot => [$path, $info]) {
                $images[$slot] = ImageStore::store($slot, $path, $info);
            }

            $values[Settings::IMAGES] = json_encode($images, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            Settings::save($values);
            self::compile();
            Session::addMessageAfterRedirect(__s('Settings saved', 'pimpmyglpi'));
        } catch (Throwable $e) {
            self::reportFailure($e);
        }

        return new RedirectResponse(self::getUrl($request));
    }

    #[Route('/config/examples', name: 'config_examples', methods: ['POST'])]
    #[SecurityStrategy(Firewall::STRATEGY_ADMIN_ACCESS)]
    public function applyExamples(Request $request): Response
    {
        self::checkAccess();

        try {
            $images    = Settings::load()->getImages();
            $directory = Plugin::getPhpDir('pimpmyglpi') . '/public/examples/';
            foreach (self::EXAMPLES as $slot => $file) {
                $info          = ImageStore::validateFile($slot, $directory . $file);
                $images[$slot] = ImageStore::store($slot, $directory . $file, $info);
            }
            Settings::save([Settings::IMAGES => json_encode($images, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]);
            self::compile();
            Session::addMessageAfterRedirect(__s('The example images are applied.', 'pimpmyglpi'));
        } catch (Throwable $e) {
            self::reportFailure($e);
        }

        return new RedirectResponse(self::getUrl($request));
    }

    #[Route('/config/reset', name: 'config_reset', methods: ['POST'])]
    #[SecurityStrategy(Firewall::STRATEGY_ADMIN_ACCESS)]
    public function reset(Request $request): Response
    {
        self::checkAccess();

        try {
            ImageStore::deleteAll();
            Settings::save(Settings::DEFAULTS);
            self::compile();
            Session::addMessageAfterRedirect(__s('GLPI appearance restored.', 'pimpmyglpi'));
        } catch (Throwable $e) {
            self::reportFailure($e);
        }

        return new RedirectResponse(self::getUrl($request));
    }

    private static function checkAccess(): void
    {
        Session::checkRight(Config::$rightname, UPDATE);
        GlpiCompat::checkConfigReAuthentication();
    }

    private static function compile(): void
    {
        ThemeCompiler::compile(Settings::load());
        Runtime::reset();
    }

    private static function reportFailure(Throwable $e): void
    {
        global $PHPLOGGER;
        $PHPLOGGER->error(sprintf('PimpMyGLPI: %s', $e->getMessage()), ['exception' => $e]);
        Session::addMessageAfterRedirect(
            __s('The changes could not be saved: check that GLPI can write in its files directory.', 'pimpmyglpi'),
            false,
            ERROR
        );
    }

    /**
     * @return array<string, array{name: string, dark: bool}>
     */
    private static function getPalettes(): array
    {
        $palettes = [];
        foreach (ThemeManager::getInstance()->getAllThemes() as $theme) {
            if (ImageSlots::isValidPaletteKey($theme->getKey())) {
                $palettes[$theme->getKey()] = ['name' => $theme->getName(), 'dark' => $theme->isDarkTheme()];
            }
        }
        uasort($palettes, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $palettes;
    }

    /**
     * @param array<string, array{ext: string, mime: string, hash: string, width: int, height: int}> $images
     *
     * @return array{url: string, width: int, height: int, ext: string, tone: string}|null
     */
    private static function describeImage(string $slot, array $images): ?array
    {
        if (!isset($images[$slot])) {
            return null;
        }

        return [
            'url'    => Runtime::getAssetUrl($slot, $images[$slot]['hash']),
            'width'  => $images[$slot]['width'],
            'height' => $images[$slot]['height'],
            'ext'    => strtoupper($images[$slot]['ext']),
            'tone'   => $images[$slot]['tone'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    private static function getSlotTexts(): array
    {
        return [
            ImageSlots::FAVICON           => [__('Favicon', 'pimpmyglpi'), __('Icon of the browser tab. Square, 32 × 32 px or more.', 'pimpmyglpi')],
            ImageSlots::LOGO_MENU         => [__('Main menu logo', 'pimpmyglpi'), __('GLPI format: 100 × 55 px (250 × 138 px for sharp screens). Light logo, the menu is dark.', 'pimpmyglpi')],
            ImageSlots::LOGO_MENU_REDUCED => [__('Collapsed menu logo', 'pimpmyglpi'), __('Shown when the menu is collapsed. Square, GLPI format: 53 × 53 px.', 'pimpmyglpi')],
            ImageSlots::LOGO_LOGIN        => [__('Login page logo', 'pimpmyglpi'), __('GLPI format: 250 × 138 px. Dark logo for light palettes, or a light logo on a dark background image.', 'pimpmyglpi')],
            ImageSlots::LOGO_LOGIN_DARK   => [__('Login page logo (dark palettes)', 'pimpmyglpi'), __('Used when the default palette is dark. Light logo, 250 × 138 px. Falls back to the logo above.', 'pimpmyglpi')],
            ImageSlots::LOGIN_BACKGROUND  => [__('Login page background', 'pimpmyglpi'), __('Covers the whole page. 1920 × 1080 px recommended.', 'pimpmyglpi')],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function getPaletteLogoLabels(): array
    {
        return [
            ImageSlots::LOGO_MENU         => __('Main menu logo', 'pimpmyglpi'),
            ImageSlots::LOGO_MENU_REDUCED => __('Collapsed menu logo', 'pimpmyglpi'),
            ImageSlots::LOGO_LOGIN        => __('Login page logo', 'pimpmyglpi'),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function getChoiceLabels(): array
    {
        return [
            Settings::RADIUS => [
                'default' => __('GLPI default', 'pimpmyglpi'),
                'square'  => __('Square', 'pimpmyglpi'),
                'rounded' => __('Rounded', 'pimpmyglpi'),
            ],
            Settings::FONT => [
                'default' => __('GLPI default (Inter)', 'pimpmyglpi'),
                'system'  => __('Operating system font', 'pimpmyglpi'),
                'sans'    => 'Arial / Helvetica',
                'serif'   => 'Georgia (serif)',
            ],
            Settings::LOGIN_CARD => [
                'default' => __('GLPI default', 'pimpmyglpi'),
                'glass'   => __('Frosted glass', 'pimpmyglpi'),
            ],
        ];
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('file too large for the server', 'pimpmyglpi'),
            UPLOAD_ERR_PARTIAL                        => __('file partially received', 'pimpmyglpi'),
            default                                   => __('server error', 'pimpmyglpi'),
        };
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function string(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function bool(array $input, string $key): bool
    {
        return in_array($input[$key] ?? '0', ['1', 'on', 'true'], true);
    }

    private static function getUrl(Request $request): string
    {
        return $request->getBasePath() . '/plugins/pimpmyglpi/config';
    }
}
