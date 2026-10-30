# PimpMyGLPI — GLPI 11 / 12 plugin

Give GLPI your own look: application name, favicon, logos (also per palette), colors, login page and custom CSS.

- Version: 1.0.0
- Author: [lorentzjohan (LORENTZ Johan)](https://github.com/lorentzjohan)
- License: GPL-3.0-or-later
- Compatibility: GLPI 11.x and 12.x (tested on 11.0.12 and 12.0.1), PHP 8.2 to 8.5 (8.3 minimum with GLPI 12)
- Languages: English, French (follows the language of the GLPI user)

## Features

| Area | Settings |
|------|----------|
| Identity | Application name (browser tab title, two-factor authentication app label, notification footer), favicon |
| Logos | Main menu, collapsed menu, login page, login page with a dark palette |
| Logos by palette | Replace the logos for a single palette (they take priority over the global logos) |
| Colors and style | Main color, main menu background (text and default logo adapt to stay readable), for light palettes only or for all palettes; square or rounded corners; font |
| Login page | Background image, background color, frosted glass login box |
| Palette | Force a palette for every user and for the login page |
| Custom CSS | Added after all the GLPI styles, on every page |
| Tools | Apply the example images in one click, restore the GLPI appearance in one click |

## How it works

- **No GLPI file is modified.** Logos are replaced through the CSS variables GLPI already uses (`--glpi-logo`, `--glpi-logo-reduced`, `--glpi-logo-dark-login`, `--glpi-logo-light-login`), the same in GLPI 11 and 12. A GLPI update never undoes the customization.
- **Images stay outside the web root**, in `files/_plugins/pimpmyglpi/images/`, and are served by the plugin with long, versioned caching.
- **No database query on page load.** When the settings are saved, they are compiled into two small files (`theme.css` and `runtime.json` in `files/_plugins/pimpmyglpi/`). Every page only reads `runtime.json`.

## Installation

1. Copy the `pimpmyglpi` folder into the GLPI `plugins/` directory.
2. Give the web server access to it, for example `chown -R www-data:www-data plugins/pimpmyglpi`.
3. In **Setup > Plugins**, install then enable "PimpMyGLPI".

From the command line:

```bash
php bin/console plugin:install pimpmyglpi -u glpi
php bin/console plugin:activate pimpmyglpi
```

GLPI must be able to write in its `files/_plugins/` directory, as for any plugin.

## Configuration

Open **Setup > Plugins > PimpMyGLPI > Configure** (URL: `/plugins/pimpmyglpi/config`). The page requires the update right on the general setup. On GLPI 12, it also requires a recent re-authentication, like the other setup pages.

### Image formats

The recommended sizes are the ones of the GLPI logos. Other sizes are accepted: images are fitted in GLPI's boxes without being distorted.

| Image | GLPI format | Accepted files | Max size |
|-------|-------------|----------------|----------|
| Favicon | 32 × 32 px or more, square | ICO, PNG, SVG | 512 KB |
| Main menu logo | 100 × 55 px (250 × 138 px for sharp screens), light logo | PNG, WebP, SVG, JPG | 2 MB |
| Collapsed menu logo | 53 × 53 px, square, light logo | PNG, WebP, SVG, JPG | 2 MB |
| Login page logo | 250 × 138 px, dark logo for light palettes | PNG, WebP, SVG, JPG | 2 MB |
| Login page logo (dark palettes) | 250 × 138 px, light logo | PNG, WebP, SVG, JPG | 2 MB |
| Login page background | 1920 × 1080 px | JPG, PNG, WebP | 8 MB |

Images larger than 4096 px per side (10000 px for the background) are refused. The server upload limit (`upload_max_filesize`) also applies.

### Example images

The plugin ships example images in the GLPI formats, in `public/examples/`, with their SVG sources in `public/examples/sources/`:

| File | Size | Use |
|------|------|-----|
| `logo-PimpMyGLPI-250-{black,grey,white}.png` | 250 × 138 | Login page, sharp main menu |
| `logo-PimpMyGLPI-100-{black,grey,white}.png` | 100 × 55 | Main menu |
| `logo-P-100-{black,grey,white}.png` | 53 × 53 | Collapsed menu |
| `favicon.ico` (16, 32, 48 px), `favicon.svg`, `apple-touch-icon.png` (180 px) | — | Favicon |
| `login-background.jpg` | 1920 × 1080 | Login page background |

The configuration page shows them, lets you download them, and applies them in one click.

## Security

- The configuration page requires the `config` update right, plus a re-authentication on GLPI 12. CSRF protection is handled by GLPI (form token on GLPI 11, request headers on GLPI 12).
- Uploads are checked by their content, never by their name: real file type, size and dimensions. Everything is validated before anything is written.
- SVG files containing scripts, event handlers, external links, `javascript:` URLs, DTD or entities are refused. Images are also served with `X-Content-Type-Options: nosniff` and a sandboxing `Content-Security-Policy`, so an image opened on its own can never run code.
- Only the images registered by the plugin can be served: file names are built from a fixed list of slots, never from the request.
- The custom CSS is served as a stylesheet, never inlined in HTML.
- The plugin does not hide the GLPI copyright notice.

## Good to know

- The application name also changes the label of new two-factor authentication enrollments. Existing enrollments in authenticator apps keep their label.
- When a palette is forced, the palette chosen in personal preferences is kept in the database but ignored.
- On a server where the plugin files are replaced, run `php bin/console cache:clear` after an update.

## Uninstallation

**Setup > Plugins > Uninstall** deletes the settings, the images and the compiled files. Reinstalling without uninstalling first keeps everything.

## Structure

```
setup.php                            Declaration, prerequisites, init
hook.php                             Install / uninstall
src/Settings.php                     Settings (glpi_configs table) and validation
src/ImageSlots.php                   Replaceable images and their constraints
src/ImageStore.php                   Upload checks (type, size, safe SVG) and storage
src/ThemeCompiler.php                Compiles theme.css and runtime.json
src/Runtime.php                      Applies the customization on every request
src/GlpiCompat.php                   GLPI 11 / 12 differences (CSRF, re-authentication)
src/Controller/ConfigController.php  Configuration page
src/Controller/AssetController.php   Serves the stylesheet and the images
templates/config.html.twig           Configuration page view
public/js/favicon.js                 Keeps the custom favicon only
public/examples/                     Example images and their SVG sources
locales/                             Translations (en_GB, fr_FR) and pimpmyglpi.pot template
tests/Units/                         Unit tests (PHPUnit, GLPI development environment)
```

## Support the project

[![Buy me a beer](https://img.buymeacoffee.com/button-api/?text=Buy%20me%20a%20beer&emoji=%F0%9F%8D%BA&slug=lorentzjohan&button_colour=5F7FFF&font_colour=ffffff&font_family=Bree&outline_colour=000000&coffee_colour=FFDD00)](https://www.buymeacoffee.com/lorentzjohan)
