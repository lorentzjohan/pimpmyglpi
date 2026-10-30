# Changelog

## [1.1.0] - 2026-10-29

### Added
- Drag and drop of the images on the configuration page, with an immediate preview, type and size checks, and a cancel button.
- Logos for light menus (main menu and collapsed menu), used when the main menu color is light.
- Information on the configuration page about the login page logo in use, and a warning when the main menu is light without logos for light menus.

### Changed
- The login page logos are now "light background" and "dark background" logos. When the plugin sets the login page background, its brightness (measured at upload for images) picks the logo instead of the palette. Without a custom background, the palette still decides, as before.
- A missing logo variant falls back to the other one; GLPI's own login logo also follows a custom background.
- Images are grouped by place on the configuration page, with the GLPI image shown when nothing is customized.

## [1.0.0] - 2026-10-29

First release.

### Added
- Application name: browser tab title, two-factor authentication app label, notification footer.
- Favicon (ICO, PNG or SVG).
- Logos: main menu, collapsed menu, login page, login page with a dark palette.
- Logos for a single palette, taking priority over the global logos.
- Main color and main menu background, with automatic text contrast, for light palettes or all palettes.
- Corner style (square or rounded) and font (operating system, Arial, Georgia).
- Login page: background image, background color and frosted glass login box.
- Palette forced for every user and for the login page.
- Custom CSS added after the GLPI styles.
- Example images in the GLPI formats (logos in black, grey and white, favicon, login background) with their SVG sources.
- One-click "Apply the example images" and "Restore the GLPI appearance" actions.
- GLPI 11.x and 12.x compatibility (tested on 11.0.12 and 12.0.1), PHP 8.2 minimum.
- English and French translations.
