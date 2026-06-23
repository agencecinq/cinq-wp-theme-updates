# CINQ Theme Update Checker

WordPress plugin to check for theme updates from private GitHub releases and install them from the admin.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- A GitHub personal access token with read access to the theme repository

## Installation

1. Copy the plugin folder to `wp-content/plugins/cinq-theme-update-checker`
2. Activate **CINQ Theme Update Checker** in the WordPress admin
3. Configure the plugin under **Settings → CINQ Theme Updates**

## Configuration

### Admin settings

- **Theme slug** — leave empty to use the active theme
- **GitHub repository** — `owner/repo` format (auto-detected from the theme `Theme URI` when possible)
- **GitHub token** — required for private repositories
- **Tag prefix** — prefix stripped from release tags before version comparison (default: `v`)
- **ZIP filename** — release asset name (default: `{theme-slug}.zip`)

### wp-config.php (recommended for production)

```php
define( 'CINQ_THEME_UPDATE_GITHUB_TOKEN', 'ghp_your_token' );
```

Optional overrides:

```php
define( 'CINQ_THEME_UPDATE_REPO', 'agencecinq/nexiode' );
define( 'CINQ_THEME_UPDATE_SLUG', 'nexiode' );
```

### Filters

```php
add_filter( 'cinq_theme_update_checker_repo', fn() => 'agencecinq/my-theme' );
add_filter( 'cinq_theme_update_checker_slug', fn() => 'my-theme' );
add_filter( 'cinq_theme_update_checker_token', fn() => 'ghp_xxx' );
add_filter( 'cinq_theme_update_checker_tag_prefix', fn() => 'v' );
add_filter( 'cinq_theme_update_checker_zip_filename', fn() => 'my-theme.zip' );
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

## License

GPL v2 or later
