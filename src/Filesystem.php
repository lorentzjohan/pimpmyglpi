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
use Throwable;

use function Safe\chmod;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\glob;
use function Safe\mkdir;
use function Safe\rename;
use function Safe\rmdir;
use function Safe\unlink;

/**
 * File operations of the plugin, all in GLPI_PLUGIN_DOC_DIR.
 *
 * Files are written atomically (temporary file then rename) so that a request never reads a
 * half-written image or stylesheet. Files are only readable by the web server user and its
 * group: they are served by the plugin, never directly by the web server.
 */
final class Filesystem
{
    private const FILE_MODE      = 0640;

    private const DIRECTORY_MODE = 0750;

    public static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        try {
            mkdir($directory, self::DIRECTORY_MODE, true);
        } catch (Throwable $throwable) {
            // Created meanwhile by another request?
            if (!is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create "%s".', $directory), 0, $throwable);
            }
        }
    }

    public static function write(string $path, string $content): void
    {
        self::replace($path, static function (string $tmp) use ($content): void {
            file_put_contents($tmp, $content);
        });
    }

    public static function copy(string $source, string $target): void
    {
        self::replace($target, static function (string $tmp) use ($source): void {
            copy($source, $tmp);
        });
    }

    public static function read(string $path, int $length = -1): string
    {
        return $length >= 0
            ? file_get_contents($path, false, null, 0, $length)
            : file_get_contents($path);
    }

    /**
     * Removes a file if it exists; failures are ignored (best effort cleanup).
     */
    public static function remove(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        try {
            unlink($path);
        } catch (Throwable) {
            // Best effort
        }
    }

    /**
     * Removes the files of a directory (not recursively), then the directory if it is empty.
     */
    public static function removeDirectory(string $directory, bool $remove_files = true): void
    {
        if (!is_dir($directory)) {
            return;
        }

        if ($remove_files) {
            foreach (glob($directory . '/*') as $file) {
                if (is_string($file)) {
                    self::remove($file);
                }
            }
        }

        try {
            rmdir($directory);
        } catch (Throwable) {
            // Not empty: kept
        }
    }

    /**
     * @param callable(string): void $writer Writes the content into the given temporary file.
     */
    private static function replace(string $path, callable $writer): void
    {
        self::ensureDirectory(dirname($path));
        $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
        try {
            $writer($tmp);
            chmod($tmp, self::FILE_MODE);
            rename($tmp, $path);
        } catch (Throwable $throwable) {
            self::remove($tmp);
            throw new RuntimeException(sprintf('Unable to write "%s".', $path), 0, $throwable);
        }
    }
}
