<div align="center">

# PimpMyGLPI — GLPI 11 / 12 plugin

[![Continuous integration](https://github.com/lorentzjohan/pimpmyglpi/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/lorentzjohan/pimpmyglpi/actions/workflows/continuous-integration.yml)
[![Latest release](https://img.shields.io/github/v/release/lorentzjohan/pimpmyglpi?sort=semver&label=release)](https://github.com/lorentzjohan/pimpmyglpi/releases/latest)
[![License: GPL v3+](https://img.shields.io/github/license/lorentzjohan/pimpmyglpi)](LICENSE)
[![GLPI 11 | 12](https://img.shields.io/badge/GLPI-11.x%20%7C%2012.x-blue)](https://glpi-project.org)
[![PHP 8.2 to 8.5](https://img.shields.io/badge/PHP-8.2%20to%208.5-777BB4?logo=php&logoColor=white)](https://www.php.net/supported-versions.php)
[![PHPStan: level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon)
[![Psalm: taint analysis](https://img.shields.io/badge/Psalm-taint%20analysis-brightgreen)](psalm.xml)
[![Code style: PHP-CS-Fixer](https://img.shields.io/badge/code%20style-PHP--CS--Fixer-brightgreen)](.php-cs-fixer.php)
[![Languages: EN | FR](https://img.shields.io/badge/languages-EN%20%7C%20FR-informational)](locales)

Give GLPI your own look: application name, favicon, logos (also per palette), colors, login page and custom CSS.

</div>

- Version: 1.1.1
- Author: [lorentzjohan (LORENTZ Johan)](https://github.com/lorentzjohan)
- License: GPL-3.0-or-later
- Compatibility: GLPI 11.x and 12.x (tested on 11.0.12 and 12.0.1), PHP 8.2 to 8.5 (8.3 minimum with GLPI 12)
- Languages: English, French (follows the language of the GLPI user)

## Features

| Area | Settings |
|------|----------|
| Identity | Application name (browser tab title, two-factor authentication app label, notification footer), favicon |
| Logos | Main menu, collapsed menu and login page, each with a variant for dark and for light backgrounds, picked automatically |
| Logos by palette | Replace the logos for a single palette (they take priority over the global logos) |
| Colors and style | Main color, main menu background (text and default logo adapt to stay readable), for light palettes only or for all palettes; square or rounded corners; font |
| Login page | Background image, background color, frosted glass login box |
| Palette | Force a palette for every user and for the login page |
| Custom CSS | Added after all the GLPI styles, on every page |
| Upload | Drag and drop with an immediate preview and checks, or classic file selection |
| Tools | Apply the example images in one click, restore the GLPI appearance in one click |

## How it works

- **No GLPI file is modified.** Logos are replaced through the CSS variables GLPI already uses (`--glpi-logo`, `--glpi-logo-reduced`, `--glpi-logo-dark-login`, `--glpi-logo-light-login`), the same in GLPI 11 and 12. A GLPI update never undoes the customization.
- **Images stay outside the web root**, in the GLPI data directory (`GLPI_PLUGIN_DOC_DIR/pimpmyglpi/images/`), and are served by the plugin with long, versioned caching.
- **No database query on page load.** When the settings are saved (and on the first page after an install or an update), they are compiled into two small files, `theme.css` and `runtime.json`, in `GLPI_PLUGIN_DOC_DIR/pimpmyglpi/`. Every page only reads `runtime.json`.

## Installation

1. Copy the `pimpmyglpi` folder into the GLPI `plugins/` directory. The web server only needs to **read** it: keep it owned by `root` (directories `0755`, files `0644`), like the rest of the GLPI code.
2. In **Setup > Plugins**, install then enable "PimpMyGLPI".

From the command line:

```bash
sudo -u www-data php bin/console plugin:install pimpmyglpi -u glpi
sudo -u www-data php bin/console plugin:activate pimpmyglpi
```

The plugin never writes in its own folder nor in the GLPI code. Settings are stored in the database, images and compiled files in `GLPI_PLUGIN_DOC_DIR/pimpmyglpi/` (`files/_plugins/pimpmyglpi/` by default, `/var/lib/glpi/_plugins/pimpmyglpi/` when `GLPI_VAR_DIR` is `/var/lib/glpi`). Only this data directory must be writable by the web server, as it already is for GLPI. The files are created with private permissions (`0640`, directories `0750`): as recommended by GLPI, run the console commands as the web server user (for example `sudo -u www-data php bin/console ...`). The theme is compiled on the first page after an install or an update.

## Update

1. Replace the `plugins/pimpmyglpi` folder.
2. In **Setup > Plugins**, click **Update**: the settings and the images are kept.
3. Clear the cache: `sudo -u www-data php bin/console cache:clear`.

From 1.1.0 or older, apply the private permissions to the files already stored (adapt the path to your `GLPI_PLUGIN_DOC_DIR`):

```bash
find /var/lib/glpi/_plugins/pimpmyglpi -type f -exec chmod 0640 {} +
find /var/lib/glpi/_plugins/pimpmyglpi -type d -exec chmod 0750 {} +
```

## Configuration

Open **Setup > Plugins > PimpMyGLPI > Configure** (URL: `/plugins/pimpmyglpi/config`). The page requires the update right on the general setup. On GLPI 12, it also requires a recent re-authentication, like the other setup pages.

### Images

Drop an image on its zone, or click the zone to browse. The preview, the type and the size are checked at once; the server checks everything again when the page is saved.

The recommended sizes are the ones of the GLPI logos. Larger images (2× or 3×) stay sharp on high-density screens, and any size is fitted in GLPI's boxes without being distorted.

| Image | Variants | GLPI format | Accepted files | Max size |
|-------|----------|-------------|----------------|----------|
| Favicon | — | 32 × 32 px or more, square | ICO, PNG, SVG | 512 KB |
| Main menu logo | Dark menu (light logo), light menu (dark logo) | 100 × 55 px | PNG, WebP, SVG, JPG | 2 MB |
| Collapsed menu logo | Dark menu, light menu | 53 × 53 px, square | PNG, WebP, SVG, JPG | 2 MB |
| Login page logo | Light background (dark logo), dark background (light logo) | 250 × 138 px | PNG, WebP, SVG, JPG | 2 MB |
| Login page background | — | 1920 × 1080 px | JPG, PNG, WebP | 8 MB |

### Which variant is used

- **Menus:** every GLPI palette has a dark main menu, so the "dark menu" logos are used. When the main menu color set in the plugin is light, the "light menu" logos are used instead (GLPI's dark logos if none is set, and the page warns you).
- **Login page:** with a background image, its brightness (measured at upload) picks the logo; with a background color, its brightness does. With GLPI's own background, light palettes use the "light background" logo and dark palettes the "dark background" one.
- A missing variant falls back to the other one. Logos set for a single palette always take priority.

Images larger than 4096 px per side (10000 px for the background) are refused. The server upload limit (`upload_max_filesize`) also applies.

### Example images

The plugin ships example images in the GLPI formats, in `public/examples/`, with their SVG sources in `public/examples/sources/`:

| File | Size | Use |
|------|------|-----|
| `logo-PimpMyGLPI-250-{black,grey,white}.png` | 250 × 138 | Login page, sharp main menu (white: dark backgrounds, black: light backgrounds) |
| `logo-PimpMyGLPI-100-{black,grey,white}.png` | 100 × 55 | Main menu |
| `logo-P-100-{black,grey,white}.png` | 53 × 53 | Collapsed menu |
| `favicon.ico` (16, 32, 48 px), `favicon.svg`, `apple-touch-icon.png` (180 px) | — | Favicon |
| `login-background.jpg` | 1920 × 1080 | Login page background |

The configuration page shows them, lets you download them, and applies them in one click.

## Security

- The configuration page requires the `config` update right, plus a re-authentication on GLPI 12. CSRF protection is handled by GLPI (form token on GLPI 11, request headers on GLPI 12).
- Stored images and compiled files are only readable by the web server user and its group (files `0640`, directories `0750`).
- Uploads are checked by their content, never by their name: real file type, size and dimensions. Everything is validated before anything is written.
- SVG files containing scripts, event handlers, external links, `javascript:` URLs, DTD or entities are refused. Images are also served with `X-Content-Type-Options: nosniff` and a sandboxing `Content-Security-Policy`, so an image opened on its own can never run code.
- Only the images registered by the plugin can be served: file names are built from a fixed list of slots, never from the request.
- The custom CSS is served as a stylesheet, never inlined in HTML.
- The plugin does not hide the GLPI copyright notice.

## Good to know

- The application name also changes the label of new two-factor authentication enrollments. Existing enrollments in authenticator apps keep their label.
- When a palette is forced, the palette chosen in personal preferences is kept in the database but ignored.

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
src/Filesystem.php                   File operations (atomic writes, private permissions)
src/Scalar.php                       Typed reading of settings and runtime data
src/Controller/ConfigController.php  Configuration page
src/Controller/AssetController.php   Serves the stylesheet and the images
templates/config.html.twig           Configuration page view
public/js/favicon.js                 Keeps the custom favicon only
public/js/config.js                  Drag and drop and confirmations on the configuration page
public/css/config.css                Configuration page styles
public/examples/                     Example images and their SVG sources
locales/                             Translations (en_GB, fr_FR) and pimpmyglpi.pot template
tests/Units/                         Unit tests (PHPUnit)
tests/Integration/                   Integration tests inside GLPI, on its test database (PHPUnit)
.github/workflows/                   Continuous integration on GLPI 11 and 12
```

## Support the project

[![Buy me a beer](https://img.buymeacoffee.com/button-api/?text=Buy%20me%20a%20beer&emoji=%F0%9F%8D%BA&slug=lorentzjohan&button_colour=5F7FFF&font_colour=ffffff&font_family=Bree&outline_colour=000000&coffee_colour=FFDD00)](https://www.buymeacoffee.com/lorentzjohan)
