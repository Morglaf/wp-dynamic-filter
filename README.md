# WP Dynamic Filter

WordPress plugin that filters a **Gutenberg Query Loop** by taxonomy over AJAX (no full page reload), with URL persistence (`history.pushState`).

Compatible with complex loop templates (grids, Dynamic Metafield bindings, etc.): the server returns already-rendered Query Loop HTML.

**Author:** [Morglaf](https://github.com/Morglaf)  
**Repository:** https://github.com/Morglaf/wp-dynamic-filter  
**License:** GPL-2.0-or-later  
**Requires:** WordPress 6.7+, PHP 8.1+

## Installation

1. Copy this folder to `wp-content/plugins/wp-dynamic-filter`
2. Build editor assets if needed:

```bash
npm install
npm run build
```

3. Activate the plugin in WordPress admin

Local zip (gitignored): `.\build-zip.local.ps1`

## Usage

1. Insert **Dynamic Filter Query**, or select a Query Loop → **Transform** → **Dynamic Filter Query**
2. Keep / place a Query Loop inside the container
3. In the **Filter** sidebar: taxonomy, buttons/dropdown, “All” label, multiple selection, counts
4. Publish and test on the front end

Legacy content using `dynamic-filter/query` still renders.

## Structure

```
wp-dynamic-filter.php
includes/          # PHP
blocks/query/      # block.json
src/               # editor source (shipped for WordPress.org guideline 4)
build/             # compiled editor assets
assets/front/      # front vanilla JS/CSS
languages/         # en source + fr_FR / es_ES / de_DE
.wordpress-org/    # Plugin Directory icons/banners (SVN /assets only)
```

## REST

`POST /wp-json/wp-dynamic-filter/v1/query`  
Legacy alias: `dynamic-filter/v1/query`

Requires `X-WP-Nonce` (`wp_rest`).

## Translations

Source strings are English. Bundled locales: French, Spanish, German (`languages/`).

## Licence

GPL-2.0-or-later — see [LICENSE](LICENSE).
