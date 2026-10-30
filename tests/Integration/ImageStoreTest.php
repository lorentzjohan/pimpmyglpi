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
use GlpiPlugin\Pimpmyglpi\ImageStore;
use GlpiPlugin\Pimpmyglpi\ImageValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

use function Safe\file_put_contents;
use function Safe\tempnam;

/**
 * Upload checks and storage of the images, on real files.
 */
final class ImageStoreTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{string, string, string, int, int}>
     */
    public static function exampleImagesProvider(): iterable
    {
        yield 'favicon ICO'        => [ImageSlots::FAVICON, 'favicon.ico', 'ico', 48, 48];
        yield 'favicon SVG'        => [ImageSlots::FAVICON, 'favicon.svg', 'svg', 0, 0];
        yield 'main menu logo'     => [ImageSlots::LOGO_MENU, 'logo-PimpMyGLPI-250-white.png', 'png', 250, 138];
        yield 'collapsed menu'     => [ImageSlots::LOGO_MENU_REDUCED, 'logo-P-100-white.png', 'png', 53, 53];
        yield 'login background'   => [ImageSlots::LOGIN_BACKGROUND, 'login-background.jpg', 'jpg', 1920, 1080];
        yield 'logo for a palette' => [ImageSlots::paletteSlot('darker', ImageSlots::LOGO_LOGIN), 'logo-PimpMyGLPI-250-grey.png', 'png', 250, 138];
    }

    #[DataProvider('exampleImagesProvider')]
    public function testExampleImagesAreAccepted(string $slot, string $file, string $ext, int $width, int $height): void
    {
        $this->assertSame(
            ['ext' => $ext, 'width' => $width, 'height' => $height],
            ImageStore::validateFile($slot, self::EXAMPLES . $file),
        );
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedFilesProvider(): iterable
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">%s</svg>';

        yield 'text renamed as PNG'   => [ImageSlots::FAVICON, 'fake.png', 'not an image'];
        yield 'SVG with a script'     => [ImageSlots::LOGO_MENU, 'script.svg', sprintf($svg, '<script>alert(1)</script>')];
        yield 'SVG with an event'     => [ImageSlots::LOGO_MENU, 'onload.svg', sprintf($svg, '<rect onload="alert(1)"/>')];
        yield 'SVG with external URL' => [ImageSlots::LOGO_MENU, 'external.svg', sprintf($svg, '<image href="https://example.com/x.png"/>')];
        yield 'SVG with an entity'    => [ImageSlots::LOGO_LOGIN, 'entity.svg', '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'];
        yield 'SVG for a background'  => [ImageSlots::LOGIN_BACKGROUND, 'background.svg', sprintf($svg, '<rect/>')];
        yield 'empty file'            => [ImageSlots::LOGO_MENU, 'empty.png', ''];
    }

    #[DataProvider('refusedFilesProvider')]
    public function testUnsafeOrUnsupportedFilesAreRefused(string $slot, string $name, string $content): void
    {
        $path = sys_get_temp_dir() . '/pimpmyglpi-test-' . bin2hex(random_bytes(4)) . '-' . $name;
        file_put_contents($path, $content);

        try {
            $this->expectException(ImageValidationException::class);
            ImageStore::validateFile($slot, $path);
        } finally {
            unlink($path);
        }
    }

    public function testTooLargeImageIsRefused(): void
    {
        $path  = tempnam(sys_get_temp_dir(), 'pimpmyglpi-test-');
        $image = imagecreatetruecolor(5000, 10);
        $this->assertNotFalse($image);
        imagepng($image, $path);

        try {
            $this->expectException(ImageValidationException::class);
            ImageStore::validateFile(ImageSlots::LOGO_MENU, $path);
        } finally {
            unlink($path);
        }
    }

    public function testStoredImagesArePrivateAndReplaced(): void
    {
        $images = $this->storeExampleImages([ImageSlots::FAVICON => 'favicon.ico']);
        $ico    = ImageStore::getPath(ImageSlots::FAVICON, 'ico');
        $this->assertFileExists($ico);
        $this->assertSame('image/x-icon', $images[ImageSlots::FAVICON]['mime']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $images[ImageSlots::FAVICON]['hash']);

        // Only the web server user and its group can read the file.
        $this->assertSame(0o640, self::permissions($ico));

        // A slot holds a single file: the SVG replaces the ICO.
        $this->storeExampleImages([ImageSlots::FAVICON => 'favicon.svg']);
        $this->assertFileExists(ImageStore::getPath(ImageSlots::FAVICON, 'svg'));
        $this->assertFileDoesNotExist($ico);

        ImageStore::delete(ImageSlots::FAVICON);
        $this->assertFileDoesNotExist(ImageStore::getPath(ImageSlots::FAVICON, 'svg'));
    }

    public function testToneOfTheImages(): void
    {
        $images = $this->storeExampleImages([
            ImageSlots::LOGO_MENU        => 'logo-PimpMyGLPI-250-white.png',
            ImageSlots::LOGO_LOGIN       => 'logo-PimpMyGLPI-250-black.png',
            ImageSlots::LOGIN_BACKGROUND => 'login-background.jpg',
        ]);

        $this->assertSame('light', $images[ImageSlots::LOGO_MENU]['tone']);
        $this->assertSame('dark', $images[ImageSlots::LOGO_LOGIN]['tone']);
        $this->assertSame('dark', $images[ImageSlots::LOGIN_BACKGROUND]['tone']);
    }
}
