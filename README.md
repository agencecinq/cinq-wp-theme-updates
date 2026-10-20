# CINQ Theme Updates

WordPress plugin that installs theme updates from private GitHub releases. Settings screen, no front-end markup.

**Repository:** [`agencecinq/cinq-wp-theme-updates`](https://github.com/agencecinq/cinq-wp-theme-updates)

## Requirements

- WordPress 6.0+
- PHP 8.1+
- A GitHub token with read access to the theme repository

## Lint (WordPress Coding Standards)

```bash
composer install
composer lint          # phpcs
composer lint:fix     # phpcbf (auto-fix)
```

Use `./vendor/bin/phpcs`, not the global `phpcs` binary — the global install does not register the WordPress standards.

## Install

Copy the plugin folder to `wp-content/plugins/cinq-wp-theme-updates` and activate **CINQ Theme Updates** in the WordPress admin. It then appears in the plugin list and stays off until activated.

## Updates

The plugin updates from the latest release of [agencecinq/cinq-wp-theme-updates](https://github.com/agencecinq/cinq-wp-theme-updates/releases), like any other plugin in **Dashboard → Updates**, and installs new releases on its own (auto-updates). The `Update URI` header routes the check to GitHub instead of WordPress.org. Versions before 1.3.0 do not update themselves: install 1.3.0 by hand once, later versions update as usual.

The check reads the `update.json` attached to the latest release, a plain download GitHub does not rate-limit, and falls back to the GitHub API. The release is cached twelve hours; **Check again** on the Updates screen reads it again. The `cinq_wp_theme_updates_auto_update` filter turns auto-updates off:

```php
add_filter( 'cinq_wp_theme_updates_auto_update', '__return_false' );
```

To publish a release:

1. Bump `Version` and `CINQ_WP_THEME_UPDATES_VERSION` in `cinq-wp-theme-updates.php`.
2. Commit, push, then push a tag: `git tag v1.4.0 && git push origin v1.4.0`.

The release workflow checks that the tag matches both versions, builds `cinq-wp-theme-updates.zip` with `git archive` (`.gitattributes` leaves the development files out) and attaches it to the release with `update.json`.

No update is offered when the plugin folder is a Git working copy, since updating replaces the whole folder.

## Settings

Under **Settings → CINQ Theme Updates**:

- **Theme slug** — empty uses the active theme
- **GitHub repository** — `owner/repo`, detected from the theme `Theme URI` when empty
- **GitHub token** — fine-grained token with read access to the repository contents, or a classic token with `repo` scope
- **Tag prefix** — stripped from release tags before version comparison (default: `v`)
- **ZIP filename** — release asset name (default: `{theme-slug}.zip`)

The token is stored in the WordPress options table. An empty token field keeps the saved token.

### Optional filters

```php
// Theme slug to update (default: setting, then active theme).
add_filter( 'cinq_wp_theme_updates_slug', fn () => 'my-theme' );

// GitHub repository (default: setting, then Theme URI).
add_filter( 'cinq_wp_theme_updates_repo', fn () => 'owner/my-theme' );

// GitHub token (default: setting).
add_filter( 'cinq_wp_theme_updates_token', fn () => 'ghp_xxx' );

// Prefix stripped from release tags (default: v).
add_filter( 'cinq_wp_theme_updates_tag_prefix', fn () => 'v' );

// Release asset filename (default: {theme-slug}.zip).
add_filter( 'cinq_wp_theme_updates_zip_filename', fn () => 'my-theme.zip' );

// Verify the GitHub TLS certificate (default: true).
add_filter( 'cinq_wp_theme_updates_sslverify', fn () => false );
```

## Theme release

The theme repository publishes a GitHub release for each tag, with the production ZIP attached as an asset.

1. Bump the version in `style.css`
2. Tag the release (e.g. `v1.2.3`)
3. Attach `{theme-slug}.zip` to the release, by hand or through GitHub Actions

The ZIP contains the theme folder at its root:

```
my-theme.zip
└── my-theme/
    ├── style.css
    └── ...
```

WordPress compares the release version to `style.css` and shows the update under **Appearance → Themes**.

## Scope

- One theme per site, from GitHub releases
- Settings screen under **Settings → CINQ Theme Updates**
- No front-end markup, no shortcode, no front-end assets

## License

MIT © [CINQ](https://agencecinq.com)
