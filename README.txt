=== CINQ Theme Updates ===

Tested up to: 7.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Installs theme updates from private GitHub releases. Settings screen, no front-end markup.

== Description ==

CINQ Theme Updates offers the releases of a theme kept in a private GitHub repository as theme updates, under **Appearance > Themes** and **Dashboard > Updates**, and downloads them with a GitHub token.

Repository: [agencecinq/cinq-wp-theme-updates](https://github.com/agencecinq/cinq-wp-theme-updates)

= Settings =

Under **Settings > CINQ Theme Updates**:

* **Theme slug** – empty uses the active theme
* **GitHub repository** – `owner/repo`, detected from the theme `Theme URI` when empty
* **GitHub token** – fine-grained token with read access to the repository contents, or a classic token with `repo` scope
* **Tag prefix** – stripped from release tags before version comparison (default: `v`)
* **ZIP filename** – release asset name (default: `{theme-slug}.zip`)

The token is stored in the WordPress options table. An empty token field keeps the saved token. Deleting the plugin removes its settings, token included.

= Filters =

Each one overrides a setting or a default, such as `add_filter( 'cinq_wp_theme_updates_slug', fn () => 'my-theme' );`:

* `cinq_wp_theme_updates_slug` – theme slug to update (default: the setting, then the active theme)
* `cinq_wp_theme_updates_repo` – GitHub repository as `owner/repo` (default: the setting, then the `Theme URI`)
* `cinq_wp_theme_updates_token` – GitHub token (default: the setting)
* `cinq_wp_theme_updates_tag_prefix` – prefix stripped from release tags (default: `v`)
* `cinq_wp_theme_updates_zip_filename` – release asset name (default: `{theme-slug}.zip`)
* `cinq_wp_theme_updates_sslverify` – whether to verify the GitHub TLS certificate (default: true)

= Theme release =

The theme repository publishes a GitHub release for each tag, with the production ZIP attached as an asset.

1. Bump the version in `style.css`.
2. Tag the release, such as `v1.2.3`.
3. Attach `{theme-slug}.zip` to the release, by hand or through GitHub Actions. A release without a ZIP is not offered as an update.

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

The plugin updates from the latest release of [agencecinq/cinq-wp-theme-updates](https://github.com/agencecinq/cinq-wp-theme-updates/releases), like any other plugin in **Dashboard > Updates**. Auto-updates are off by default: **Enable auto-updates** in the Plugins screen turns them on for the site. The `Update URI` header routes the check to GitHub instead of WordPress.org.

The check reads the `update.json` attached to the latest release, a plain download GitHub does not rate-limit. The release is cached twelve hours; **Check again** on the Updates screen reads it again.

To publish a release:

1. Bump `Version` in `cinq-wp-theme-updates.php`, and add the version to the changelog below.
2. Commit, push, then push a tag. A minor or major version is named after an aviator or an aviatrix (see the FAQ), with an annotated tag: `git tag -a v2.2.0 -m Daurat && git push origin v2.2.0`. A patch version keeps a plain tag: `git tag v2.2.1 && git push origin v2.2.1`.

The release workflow checks that the tag matches the version and has a changelog entry, builds `cinq-wp-theme-updates.zip` with `git archive` (`.gitattributes` leaves the development files out) and attaches it to the release with `update.json`. The release is titled by the tag, followed by the message of an annotated tag: `v2.2.0 · Daurat`. The changelog entry becomes the release notes, and the plugin details show this file as it is in the release.

No update is offered when the plugin folder is a Git working copy, since updating replaces the whole folder.

= Development =

Run `composer install`, then `composer lint` (phpcs) or `composer lint:fix` (phpcbf). Use `./vendor/bin/phpcs`, not the global `phpcs` binary: the global install does not register the WordPress standards.

`composer lint` also checks the code against PHP 8.1 and later with PHPCompatibilityWP: what a newer PHP added, which would fail on a PHP 8.1 site, and what later versions deprecate or remove.

The interface is in English, translated into French in `languages/`. After changing a string, run `composer i18n` (WP-CLI 2.7 or later): it extracts the strings to the POT file and merges them into the PO file. Translate the new or changed ones in the PO file, then run `composer i18n` again to compile the MO file WordPress reads.

== Frequently Asked Questions ==

= Why does a new theme release not show up? =

The release is cached twelve hours. **Check again** on the Updates screen reads it again.

= Why are the versions named after aviators and aviatrices? =

The plugin carries the theme from GitHub to the sites, as the pioneers of aviation carried the mail and opened the routes: Mermoz, Guillaumet and Saint-Exupéry on the lines of the Aéropostale, from Toulouse to Dakar and over the Andes; Adrienne Bolland, the first woman to fly across the Andes, in 1921; Maryse Bastié over the South Atlantic; Hélène Boucher and her speed records. Each minor or major version takes one of their names, as macOS versions took the names of big cats and WordPress versions take those of jazz musicians.

2.2.0, which follows the Plugin Handbook, is Daurat: the director who held the Aéropostale to its rules, and became Rivière in Night Flight, the novel Saint-Exupéry drew from the line.

== Changelog ==

= 2.2.0 =
* The theme ZIP is written to disk as it downloads, as WordPress does for its own packages, instead of being held in memory first.
* Deactivating the plugin forgets the cached releases and the theme update offered from them. Deleting it removes its settings, GitHub token included.
* The last GitHub error is no longer stored: nothing read it, the settings screen shows the error of its own request.
* The settings page saves through the Settings API: `options.php` checks the nonce and the capability, then returns to the page with WordPress's own "Settings saved." notice, shown once. Reloading the page no longer submits the form again.
* Changing the settings forgets the cached theme release, whoever changes them. Saving them unchanged no longer does: **Check again** on the Updates screen reads GitHub again.
* The token field no longer lets browsers fill in a saved password, which could replace the token with the WordPress password.
* Each field is tied to its description for screen readers. An empty tag prefix uses `v`, shown as its placeholder, as the other fields show their defaults.
* The interface is translated into French. The plugin loads its own translations: WordPress only finds those of plugins hosted on WordPress.org.
* The status under the settings is a table, like the settings above it.

= 2.1.0 =
* The settings page shows the latest theme release by its GitHub name, such as `v1.2.3 · Ampère`, linked to the release. A release without a name shows its tag, as on GitHub.

= 2.0.1 =
* The version is the plugin header alone: the `CINQ_WP_THEME_UPDATES_VERSION` constant is removed, and so is the clearing of the update caches on the first page load after an update.
* What earlier releases needed is removed: the GitHub API fallback for plugin releases without `update.json`, the `token` scheme for classic tokens (every token is sent as `Bearer`), and the cleanup of tokens saved before 1.0.1.
* A theme release without a ZIP is no longer offered as an update: its download could only fail.

= 2.0.0 =
* Breaking: auto-updates are no longer forced on, and the `cinq_wp_theme_updates_auto_update` filter is removed. They are WordPress's own, off by default, as for any plugin. Migration: turn them on per site with **Enable auto-updates** in the Plugins screen, or `wp plugin auto-updates enable cinq-wp-theme-updates`; a site that returned false from the filter can drop it.

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
