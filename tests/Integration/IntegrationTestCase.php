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

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Pimpmyglpi\Filesystem;
use GlpiPlugin\Pimpmyglpi\ImageStore;
use GlpiPlugin\Pimpmyglpi\Runtime;
use GlpiPlugin\Pimpmyglpi\Settings;
use GlpiPlugin\Pimpmyglpi\ThemeCompiler;

use function Safe\json_encode;

/**
 * Base class of the integration tests: they run inside GLPI, on its test database (each test
 * is rolled back) and its test files directory, with the plugin installed and activated.
 */
abstract class IntegrationTestCase extends DbTestCase
{
    protected const EXAMPLES = __DIR__ . '/../../public/examples/';

    public function tearDown(): void
    {
        // Files are not rolled back with the database.
        ImageStore::deleteAll();
        Filesystem::remove(ThemeCompiler::getCssPath());
        Filesystem::remove(ThemeCompiler::getRuntimePath());
        Runtime::reset();

        parent::tearDown();
    }

    /**
     * Validates and stores example images, as the configuration page does.
     *
     * @param array<string, string> $files Slot => example file name
     *
     * @return array<string, array{ext: string, mime: string, hash: string, width: int, height: int, tone: string}>
     */
    protected function storeExampleImages(array $files): array
    {
        $images = Settings::load()->getImages();
        foreach ($files as $slot => $file) {
            $path          = self::EXAMPLES . $file;
            $images[$slot] = ImageStore::store($slot, $path, ImageStore::validateFile($slot, $path));
        }

        Settings::save([Settings::IMAGES => json_encode($images)]);

        return Settings::load()->getImages();
    }

    protected static function permissions(string $path): int
    {
        clearstatcache(true, $path);

        return fileperms($path) & 0o777;
    }
}
