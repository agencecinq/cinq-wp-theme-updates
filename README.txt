=== CINQ Theme Updates ===

Tested up to: 7.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Installs theme updates from private GitHub releases. Settings screen, no front-end markup.

== Description ==

CINQ Theme Updates offers the releases of a theme kept in a private GitHub repository as theme updates, under **Appearance > Themes** and **Dashboard > Updates**, and downloads them with a GitHub token.

Repository: [agencecinq/cinq-wp-theme-updates](https://github.com/agencecinq/cinq-wp-theme-updates)

= Requirements =

* WordPress 6.0 or later
* PHP 8.1 or later
* A GitHub token with read access to the theme repository

= Settings =

Under **Settings > CINQ Theme Updates**:

* **Theme slug** – empty uses the active theme
* **GitHub repository** – `owner/repo`, detected from the theme `Theme URI` when empty
* **GitHub token** – fine-grained token with read access to the repository contents, or a classic token with `repo` scope
* **Tag prefix** – stripped from release tags before version comparison (default: `v`)
* **ZIP filename** – release asset name (default: `{theme-slug}.zip`)

The token is stored in the WordPress options table. An empty token field keeps the saved token.

= Filters =

Each one overrides a setting or a default, such as `add_filter( 'cinq_wp_theme_updates_slug', fn () => 'my-theme' );`:

* `cinq_wp_theme_updates_slug` – theme slug to update (default: the setting, then the active theme)
* `cinq_wp_theme_updates_repo` – GitHub repository as `owner/repo` (default: the setting, then the `Theme URI`)
* `cinq_wp_theme_updates_token` – GitHub token (default: the setting)
* `cinq_wp_theme_updates_tag_prefix` – prefix stripped from release tags (default: `v`)
* `cinq_wp_theme_updates_zip_filename` – release asset name (default: `{theme-slug}.zip`)
* `cinq_wp_theme_updates_sslverify` – whether to verify the GitHub TLS certificate (default: true)
* `cinq_wp_theme_updates_auto_update` – whether WordPress installs new releases of the plugin on its own (default: true)

= Theme release =

The theme repository publishes a GitHub release for each tag, with the production ZIP attached as an asset.

1. Bump the version in `style.css`.
2. Tag the release, such as `v1.2.3`.
3. Attach `{theme-slug}.zip` to the release, by hand or through GitHub Actions.

The ZIP holds the theme folder at its root: `my-theme.zip` contains `my-theme/style.css` and the rest of the theme.

WordPress compares the release version to `style.css` and shows the update under **Appearance > Themes**. The release is cached twelve hours; **Check again** on the Updates screen reads it again, so a new tag shows up right away.

= Scope =

* One theme per site, from GitHub releases
* Settings screen under **Settings > CINQ Theme Updates**
* No front-end markup, no shortcode, no front-end assets

== Installation ==

1. Copy the plugin folder to `wp-content/plugins/cinq-wp-theme-updates`.
2. Activate **CINQ Theme Updates** in the **Plugins** screen. It stays off until activated.
3. Fill in **Settings > CINQ Theme Updates**.

= Updates =

The plugin updates from the latest release of [agencecinq/cinq-wp-theme-updates](https://github.com/agencecinq/cinq-wp-theme-updates/releases), like any other plugin in **Dashboard > Updates**, and installs new releases on its own (auto-updates). The `Update URI` header routes the check to GitHub instead of WordPress.org. Versions before 1.3.0 do not update themselves: install 1.3.0 by hand once, later versions update as usual.

The check reads the `update.json` attached to the latest release, a plain download GitHub does not rate-limit, and falls back to the GitHub API. The release is cached twelve hours; **Check again** on the Updates screen reads it again. `add_filter( 'cinq_wp_theme_updates_auto_update', '__return_false' );` turns auto-updates off.

To publish a release:

1. Bump `Version` and `CINQ_WP_THEME_UPDATES_VERSION` in `cinq-wp-theme-updates.php`, and add the version to the changelog below.
2. Commit, push, then push a tag: `git tag v1.4.0 && git push origin v1.4.0`.

The release workflow checks that the tag matches both versions and has a changelog entry, builds `cinq-wp-theme-updates.zip` with `git archive` (`.gitattributes` leaves the development files out) and attaches it to the release with `update.json`. The changelog entry becomes the release notes, and the plugin details show this file as it is in the release.

No update is offered when the plugin folder is a Git working copy, since updating replaces the whole folder.

= Development =

Run `composer install`, then `composer lint` (phpcs) or `composer lint:fix` (phpcbf). Use `./vendor/bin/phpcs`, not the global `phpcs` binary: the global install does not register the WordPress standards.

== Frequently Asked Questions ==

= Why does a new theme release not show up? =

The release is cached twelve hours. **Check again** on the Updates screen reads it again.

= Why does the plugins list show “Visit plugin site” instead of “View details”? =

WordPress links the plugin details once its update check has read the latest release. It does not when the plugin folder is a Git working copy, where no update is offered, or while GitHub cannot be reached.

== Changelog ==

= 1.4.0 =
* The plugin details show this readme: description, installation, FAQ and changelog, with the WordPress version it is tested up to and a banner. The Updates screen shows its icon.
* The plugin reads its releases from the `update.json` attached to each one, a plain download GitHub does not rate-limit, and installs the zip attached to the release instead of the archive GitHub builds from the tag. The GitHub API stays as a fallback.
* Auto-updates are on. The `cinq_wp_theme_updates_auto_update` filter turns them off.
* **Check again** on the Updates screen reads the latest theme release again, as it does for the plugin.

= 1.3.0 =
* The plugin updates from its GitHub releases, like any other plugin. Install 1.3.0 by hand once: earlier versions do not update themselves.
* Licensed under MIT.
* Fixed: a repository whose name starts with the theme repository's, such as `owner/theme-updates` for `owner/theme`, matched it, and its packages were downloaded as the theme release.

= 1.2.0 =
* Breaking: the wp-config constants and the migration of the former settings are removed. Enter the theme slug, repository and token on the settings screen.

= 1.1.0 =
* Breaking: renamed `cinq-wp-theme-updates`, its slug, text domain and filters matching the other CINQ plugins. The settings are copied from the former option.

= 1.0.2 =
* Fixed: release assets of private repositories are downloaded through the GitHub API, their `browser_download_url` answering 404.

= 1.0.1 =
* The settings screen shows the GitHub API errors.
* Classic `ghp_` tokens are sent with the `token` scheme, and saved tokens are no longer altered by sanitization.

= 1.0.0 =
* Theme updates from private GitHub releases, with a settings screen.
