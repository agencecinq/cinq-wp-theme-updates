# CINQ Theme Updates

WordPress plugin that installs theme updates from private GitHub releases. Settings screen, no front-end markup.

**Repository:** [`agencecinq/cinq-wp-theme-updates`](https://github.com/agencecinq/cinq-wp-theme-updates)

## Requirements

- WordPress 6.0+
- PHP 8.1+
- A GitHub personal access token with read access to the theme repository

## Lint (WordPress Coding Standards)

```bash
composer install
composer lint          # phpcs
composer lint:fix     # phpcbf (auto-fix)
```

Use `./vendor/bin/phpcs`, not the global `phpcs` binary — the global install does not register the WordPress standards.

## Install

Copy the plugin folder to `wp-content/plugins/cinq-wp-theme-updates` and activate **CINQ Theme Updates** in the WordPress admin. It then appears in the plugin list and stays off until activated.

Replace that folder when the plugin changes.

Configure it under **Settings → CINQ Theme Updates**.

## Configuration

### Admin settings

- **Theme slug** — leave empty to use the active theme
- **GitHub repository** — `owner/repo` format (auto-detected from the theme `Theme URI` when possible)
- **GitHub token** — required for private repositories
- **Tag prefix** — prefix stripped from release tags before version comparison (default: `v`)
- **ZIP filename** — release asset name (default: `{theme-slug}.zip`)

### wp-config.php

```php
define( 'CINQ_THEME_UPDATE_GITHUB_TOKEN', 'ghp_your_token' );
define( 'CINQ_THEME_UPDATE_REPO', 'agencecinq/my-theme' );
define( 'CINQ_THEME_UPDATE_SLUG', 'my-theme' );
```

`THEME_UPDATE_GITHUB_TOKEN`, `THEME_UPDATE_REPO` and `THEME_UPDATE_SLUG` are accepted as aliases. When a token constant is set, the settings screen does not store a token.

### Optional filters

```php
add_filter( 'cinq_wp_theme_updates_repo', fn () => 'agencecinq/my-theme' );
add_filter( 'cinq_wp_theme_updates_slug', fn () => 'my-theme' );
add_filter( 'cinq_wp_theme_updates_token', fn () => 'ghp_xxx' );
add_filter( 'cinq_wp_theme_updates_tag_prefix', fn () => 'v' );
add_filter( 'cinq_wp_theme_updates_zip_filename', fn () => 'my-theme.zip' );
```

## Theme release workflow

Each theme repository should publish a GitHub release on tag push with a production ZIP asset:

1. Bump the version in `style.css` before tagging
2. Tag the release (e.g. `v1.2.3`)
3. Let GitHub Actions build assets and attach `{theme-slug}.zip` to the release

The ZIP must contain the theme folder at its root:

```
nexiode.zip
└── nexiode/
    ├── style.css
    └── ...
```

## Scope

- Updates one theme from GitHub releases
- Settings screen under **Settings → CINQ Theme Updates**
- No front-end markup, no shortcode
