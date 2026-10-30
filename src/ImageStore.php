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

use RuntimeException;
use SimpleXMLElement;
use Throwable;

use function Safe\filesize;
use function Safe\getimagesize;
use function Safe\glob;
use function Safe\hash_file;
use function Safe\imagecolorat;
use function Safe\imagecreatefromstring;
use function Safe\preg_match;
use function Safe\simplexml_load_string;
use function Safe\unpack;

/**
 * Validation and storage of the uploaded images.
 *
 * Files are kept outside of the GLPI web root (GLPI_PLUGIN_DOC_DIR) under a name built
 * from the slot only, and are served by {@see Controller\AssetController}: GLPI core
 * files are never modified, so GLPI updates do not undo the customization.
 */
final class ImageStore
{
    /** @var array<string, string> */
    public const MIME_TYPES = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
    ];

    public static function getDirectory(): string
    {
        return GLPI_PLUGIN_DOC_DIR . '/pimpmyglpi/images';
    }

    public static function getPath(string $slot, string $ext): string
    {
        if (!ImageSlots::isValidSlot($slot) || !isset(self::MIME_TYPES[$ext])) {
            throw new RuntimeException('Invalid image slot or extension.');
        }

        return self::getDirectory() . '/' . $slot . '.' . $ext;
    }

    /**
     * Checks a file against the constraints of a slot, by its content (never by its name).
     *
     * @return array{ext: string, width: int, height: int}
     *
     * @throws ImageValidationException
     */
    public static function validateFile(string $slot, string $path): array
    {
        $definition = ImageSlots::getDefinition($slot);

        if (!is_file($path) || !is_readable($path)) {
            throw new ImageValidationException(__('The file could not be read.', 'pimpmyglpi'));
        }

        $size = filesize($path);
        if ($size <= 0) {
            throw new ImageValidationException(__('The file is empty.', 'pimpmyglpi'));
        }

        if ($size > $definition['max_size']) {
            throw new ImageValidationException(sprintf(
                __('The file is too large: %s at most.', 'pimpmyglpi'),
                self::formatSize($definition['max_size']),
            ));
        }

        $ext = self::detectType($path);
        if ($ext === null && self::looksLikeSvg($path)) {
            throw new ImageValidationException(__('This SVG file contains elements that are not allowed (scripts, external links or entities).', 'pimpmyglpi'));
        }

        if ($ext === null || !in_array($ext, $definition['types'], true)) {
            throw new ImageValidationException(sprintf(
                __('Unsupported file type. Accepted formats: %s.', 'pimpmyglpi'),
                strtoupper(implode(', ', $definition['types'])),
            ));
        }

        [$width, $height] = self::getDimensions($path, $ext);
        if ($ext !== 'svg' && ($width < 1 || $height < 1)) {
            throw new ImageValidationException(__('The image could not be read.', 'pimpmyglpi'));
        }

        if ($width > $definition['max_px'] || $height > $definition['max_px']) {
            throw new ImageValidationException(sprintf(
                __('The image is too large: %d px at most per side.', 'pimpmyglpi'),
                $definition['max_px'],
            ));
        }

        return ['ext' => $ext, 'width' => $width, 'height' => $height];
    }

    /**
     * Copies a validated file into the store (the source is left untouched).
     *
     * @param array{ext: string, width: int, height: int} $validated
     *
     * @return array{ext: string, mime: string, hash: string, width: int, height: int, tone: string}
     */
    public static function store(string $slot, string $source, array $validated): array
    {
        $target = self::getPath($slot, $validated['ext']);
        Filesystem::copy($source, $target);

        // A slot holds a single file: remove a previous one stored with another extension.
        foreach (array_keys(self::MIME_TYPES) as $ext) {
            if ($ext !== $validated['ext']) {
                Filesystem::remove(self::getPath($slot, $ext));
            }
        }

        return [
            'ext'    => $validated['ext'],
            'mime'   => self::MIME_TYPES[$validated['ext']],
            'hash'   => substr(hash_file('sha256', $target), 0, 16),
            'width'  => $validated['width'],
            'height' => $validated['height'],
            'tone'   => self::getTone($target, $validated['ext']),
        ];
    }

    /**
     * "light" or "dark" according to the visible pixels of a raster image, "" when unknown.
     * Only used to pick a readable background for the previews of the configuration page.
     */
    public static function getTone(string $path, string $ext): string
    {
        if (!in_array($ext, ['png', 'jpg', 'webp'], true) || !function_exists('imagecreatefromstring')) {
            return '';
        }

        try {
            $image = imagecreatefromstring(Filesystem::read($path));
        } catch (Throwable) {
            return '';
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        $step   = max(1, (int) floor(max($width, $height) / 64));
        $sum    = 0.0;
        $count  = 0;
        for ($x = 0; $x < $width; $x += $step) {
            for ($y = 0; $y < $height; $y += $step) {
                $rgba  = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $alpha = 1 - ($rgba['alpha'] / 127);
                if ($alpha < 0.5) {
                    continue;
                }

                $sum += (0.2126 * $rgba['red'] + 0.7152 * $rgba['green'] + 0.0722 * $rgba['blue']) / 255;
                $count++;
            }
        }

        unset($image);

        return $count === 0 ? '' : ($sum / $count > 0.6 ? 'light' : 'dark');
    }

    public static function delete(string $slot): void
    {
        foreach (array_keys(self::MIME_TYPES) as $ext) {
            Filesystem::remove(self::getPath($slot, $ext));
        }
    }

    public static function deleteAll(): void
    {
        if (!is_dir(self::getDirectory())) {
            return;
        }

        foreach (glob(self::getDirectory() . '/*') as $file) {
            if (is_string($file)) {
                Filesystem::remove($file);
            }
        }
    }

    /**
     * Rejects anything that could run code or load external content when the SVG is opened
     * on its own. The files are also served with a sandboxing CSP (defense in depth).
     */
    public static function isSafeSvg(string $content): bool
    {
        if ($content === '' || str_contains($content, "\0")) {
            return false;
        }

        $forbidden = [
            // Active or embedding elements
            '/<\s*(script|foreignobject|iframe|frame|embed|object|applet|audio|video|link|meta|base|form|input|button|textarea|animate\w*|set|handler|listener)\b/i',
            // Event handler attributes
            '/\son[a-z]+\s*=/i',
            // Dangerous URL schemes
            '/(javascript|vbscript|livescript)\s*:/i',
            '/data\s*:\s*(text|application)\//i',
            // DTD / entities (XXE, billion laughs)
            '/<!\s*(doctype|entity)/i',
            // CSS imports and external CSS urls
            '/@import/i',
            '/url\s*\(\s*["\']?\s*(?!#|data:image\/)/i',
            // External references (only local "#id" and inline images allowed)
            '/\bhref\s*=\s*["\']\s*(?!#|data:image\/)/i',
        ];
        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return false;
            }
        }

        // Must be well-formed XML with an <svg> root.
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
            $ok  = strtolower($xml->getName()) === 'svg';
        } catch (Throwable) {
            $ok = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $ok;
    }

    /**
     * Detects the real file type from its content.
     */
    private static function detectType(string $path): ?string
    {
        $head = Filesystem::read($path, 16);

        try {
            $raster = getimagesize($path);
        } catch (Throwable) {
            // Not a raster image
            $raster = null;
        }

        if (is_array($raster)) {
            $type = match ($raster[2]) {
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_ICO  => 'ico',
                default        => null,
            };
            if ($type !== null) {
                return $type;
            }
        }

        if (str_starts_with($head, "\x00\x00\x01\x00")) {
            return 'ico';
        }

        if (self::looksLikeSvg($path) && self::isSafeSvg(Filesystem::read($path))) {
            return 'svg';
        }

        return null;
    }

    private static function looksLikeSvg(string $path): bool
    {
        $content = Filesystem::read($path, 4096);

        return preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*(\[.*?\])?\s*>\s*)?<svg[\s>]/is', $content) === 1;
    }

    /**
     * @return array{int, int}
     */
    private static function getDimensions(string $path, string $ext): array
    {
        if ($ext === 'svg') {
            return [0, 0];
        }

        if ($ext === 'ico') {
            // Largest of the icons stored in the file (directory entries of 16 bytes after a 6-byte header).
            $data  = Filesystem::read($path, 6 + 16 * 64);
            $count = strlen($data) >= 6 ? Scalar::toInt(unpack('v', substr($data, 4, 2))[1] ?? 0) : 0;
            $width = 0;
            $height = 0;
            for ($i = 0; $i < min($count, 64) && strlen($data) >= 6 + 16 * ($i + 1); $i++) {
                $entry_width  = ord($data[6 + 16 * $i]) ?: 256;
                $entry_height = ord($data[7 + 16 * $i]) ?: 256;
                if ($entry_width * $entry_height > $width * $height) {
                    [$width, $height] = [$entry_width, $entry_height];
                }
            }

            return [$width, $height];
        }

        try {
            $info = getimagesize($path);
        } catch (Throwable) {
            return [0, 0];
        }

        return is_array($info) ? [Scalar::toInt($info[0]), Scalar::toInt($info[1])] : [0, 0];
    }


    private static function formatSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? sprintf('%d MB', intdiv($bytes, 1024 * 1024))
            : sprintf('%d KB', intdiv($bytes, 1024));
    }
}
