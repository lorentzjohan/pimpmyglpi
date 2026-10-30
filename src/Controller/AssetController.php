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

use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Pimpmyglpi\ImageSlots;
use GlpiPlugin\Pimpmyglpi\ImageStore;
use GlpiPlugin\Pimpmyglpi\Runtime;
use GlpiPlugin\Pimpmyglpi\ThemeCompiler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the stylesheet and the images. Public on purpose: the login page uses them.
 * Only files referenced by the compiled runtime data can be served.
 */
final class AssetController extends AbstractController
{
    #[Route('/stylesheet', name: 'stylesheet', methods: ['GET'])]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function stylesheet(Request $request): Response
    {
        $hash = (string) (Runtime::get()['css'] ?? '');
        $path = ThemeCompiler::getCssPath();
        if ($hash === '' || !is_file($path)) {
            return new Response('', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/css; charset=utf-8']);
        }

        $css = str_replace(
            ThemeCompiler::BASE_PATH_PLACEHOLDER,
            $request->getBasePath(),
            (string) file_get_contents($path)
        );

        return $this->cache($request, new Response($css, Response::HTTP_OK), 'text/css; charset=utf-8', $hash);
    }

    #[Route('/asset/{slot}', name: 'asset', requirements: ['slot' => '[a-z0-9_.-]{1,100}'], methods: ['GET'])]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function asset(Request $request, string $slot): Response
    {
        $image = ImageSlots::isValidSlot($slot) ? Runtime::getImage($slot) : null;
        if ($image === null || !isset(ImageStore::MIME_TYPES[$image['ext']])) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        $path = ImageStore::getPath($slot, $image['ext']);
        if (!is_file($path)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return $this->send($request, $path, ImageStore::MIME_TYPES[$image['ext']], $image['hash']);
    }

    private function send(Request $request, string $path, string $content_type, string $hash): Response
    {
        return $this->cache($request, new BinaryFileResponse($path, Response::HTTP_OK, [], true, null, false, false), $content_type, $hash);
    }

    private function cache(Request $request, Response $response, string $content_type, string $hash): Response
    {
        // PHP sessions send "no-store" headers that would prevent any caching of these files.
        if (!headers_sent()) {
            header_remove('Cache-Control');
            header_remove('Pragma');
            header_remove('Expires');
        }

        $response->headers->set('Content-Type', $content_type);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // An image opened on its own (SVG especially) can never run anything.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox");
        $response->setEtag($hash);
        $response->setPublic();

        if ($request->query->getString('h') === $hash) {
            // Versioned URL: the content never changes for this URL.
            $response->setMaxAge(31536000);
            $response->headers->addCacheControlDirective('immutable');
        } else {
            $response->setMaxAge(300);
        }
        $response->isNotModified($request);

        return $response;
    }
}
