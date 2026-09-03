# Broadcaster Auto Responder for Gravity Forms

A WordPress plugin that connects [Gravity Forms](https://www.gravityforms.com) to **[Broadcaster](https://getbroadcaster.com)**, a paid SaaS platform for business WhatsApp management.

> **This plugin requires an active Broadcaster account.** It is not a free WhatsApp integration and does nothing useful on its own. For WordPress.org-style end-user documentation see [`broadcaster-auto-responder-for-gravity-forms/readme.txt`](broadcaster-auto-responder-for-gravity-forms/readme.txt). This `README.md` is for developers working on the plugin source.

## What it does

For each Gravity Form you opt in to, the plugin posts the submission to Broadcaster's `/api/v1/messages/incoming` endpoint as an inbound contact message, attributed to the form. Each feed can also trigger an optional template auto-response — either a single template that always fires, or two templates (in-hours / out-of-hours) selected by Broadcaster based on the company's configured business hours.

## Repository layout

```
plugin-broadcaster-auto-responder-for-gravity-forms-project/   (this repo)
├── broadcaster-auto-responder-for-gravity-forms/              (the WP plugin slug folder)
│   ├── broadcaster-auto-responder-for-gravity-forms.php       (main plugin file)
│   ├── readme.txt                                             (WP.org-style end-user docs)
│   ├── uninstall.php
│   ├── composer.json                                          (classmap autoload, BroadcasterGF\)
│   ├── images/menu-icon.svg                                   (Broadcaster brand mark for the GF sidebar)
│   └── includes/
│       ├── class-plugin.php                                   (BroadcasterGF\Plugin singleton)
│       ├── admin/class-notices.php                            (GF-missing / API-key-missing notices)
│       ├── api/class-client.php                               (wp_remote_* wrapper)
│       └── gf/
│           ├── class-broadcaster-gf-bootstrap.php             (gform_loaded → register the add-on)
│           └── class-broadcaster-gf-feedaddon.php             (the GFFeedAddOn subclass)
├── tests/                                                     (PHPUnit scaffolding)
├── composer.json                                              (dev tooling: PHPCS + WPCS)
├── package.json                                               (wp-env scripts)
├── phpcs.xml.dist
├── phpcs_sec.xml
├── .wp-env.json
└── .github/workflows/                                         (Quality Checks + Release)
```

The plugin lives in its own sibling repo to keep WordPress.org packaging concerns out of the [`broadcaster-app`](https://github.com/alanef/broadcaster-app) parent project.

## Architecture notes worth knowing

* **Two namespacing styles in one plugin.** Admin / API helpers are PSR-4 under `BroadcasterGF\` (loaded via composer classmap). The Gravity Forms add-on class is global (`Broadcaster_GF_FeedAddOn` extending `\GFFeedAddOn`) because GF stores registered add-ons by class-name string, and `gform_loaded` fires *during* `plugins_loaded` — registration must happen at file scope, before any other plugins_loaded handler runs. This mirrors how the EmailOctopus add-on works.
* **Production locks the Broadcaster URL.** When `wp_get_environment_type() === 'production'` the API URL field is hidden and the plugin always calls `https://getbroadcaster.com`. On dev/staging/local the URL is editable but pre-filled with the same default so a fresh install is functional immediately.
* **Settings live under Gravity Forms.** *Forms → Settings → Broadcaster*, not a standalone WP options page. The API key field uses GF's `feedback_callback` for the inline ✓/✗ indicator and a labelled status line below the field; both share a per-request connection-test cache that's invalidated on save.
* **Failures never block Gravity Forms.** `process_feed()` catches Broadcaster failures, logs them via `log_error()`, and returns the entry untouched. Form confirmations and notification emails proceed regardless.
* **Username support is gated behind a feature flag.** The WhatsApp Recipient field's username surface — helper text mentioning the `@username` option, and acceptance of `@`-prefixed input — is only active when the `BROADCASTERGF_ENABLE_USERNAMES` PHP constant is `true`. Default is `false` because Meta has not yet enabled WhatsApp usernames generally. To exercise the full username path on a staging site, add `define( 'BROADCASTERGF_ENABLE_USERNAMES', true );` to `wp-config.php` before the plugin loads. The underlying code path (discriminator, validator, dispatcher) is built end-to-end in v1; the constant only gates the public-facing surface. Architectural rationale lives in `broadcaster-web/specification/BRO-902/design.md` (D12).

## Local development

Requires PHP 7.4+ (development uses PHP 8.0 via wp-env), Node 18+, and Composer 2+.

```bash
# Install dev tooling (PHPCS, WPCS, PHPCompatibility, PHPUnit)
composer install

# Install the plugin's own composer deps so its autoloader is generated
( cd broadcaster-auto-responder-for-gravity-forms && composer install )

# Spin up wp-env (Docker WP at http://localhost:8888 — admin / password)
npm install
npm run env:start
```

Gravity Forms is not bundled (it's commercial); install it manually inside the wp-env WordPress to test the GF integration.

### After pulling code that adds new classes

The plugin loads its `BroadcasterGF\` classes via a Composer **classmap** autoloader (`broadcaster-auto-responder-for-gravity-forms/composer.json`). The classmap is generated as a static file at install time and does **not** auto-discover new files. After `git pull` brings in a commit that adds a new class under `includes/`, regenerate the classmap or new code will fatal at runtime with `Class "BroadcasterGF\\Foo\\Bar" not found`:

```bash
composer plugin:dump
```

The CI release workflow runs `composer install --no-dev --optimize-autoloader` inside the plugin folder before zipping, so released artifacts always carry a fresh classmap. This step is only needed in local dev when you've just pulled code that added classes.

## Quality checks

The CI `quality-checks` workflow runs the same checks that block PR merges; run them locally before committing:

```bash
# WordPress Coding Standards (must pass with 0 errors AND 0 warnings)
composer run-script lint

# PHP-Compatibility (against PHP 7.4+)
./vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- --extensions=php broadcaster-auto-responder-for-gravity-forms

# Auto-fix what can be auto-fixed
./vendor/bin/phpcbf broadcaster-auto-responder-for-gravity-forms
```

CI also runs WordPress Plugin Check on the built dist zip, which catches WP.org compliance issues PHPCS does not (trademark in name/slug, outdated `Tested up to`, missing `composer.json` next to `vendor/`, and so on).

## Build

```bash
# Generates dist/broadcaster-auto-responder-for-gravity-forms.zip
composer run-script build
```

The zip excludes everything in `broadcaster-auto-responder-for-gravity-forms/.distignore` (development files, lock files, the `tests/` and `bin/` directories, etc.) and includes the plugin's `vendor/` directory generated by `composer install --no-dev`.

## Release

Tag a SemVer release on `main` and push:

```bash
git tag v1.0.0
git push origin v1.0.0
```

The `release.yml` GitHub Actions workflow runs the full quality-checks job, builds the zip, and creates a GitHub Release with the artifact attached. The `Version:` plugin header and the `BROADCASTERGF_VERSION` constant must agree before a tag will pass the `Check version consistency` step.

## License

GPL v2 or later (see [`LICENSE`](LICENSE)).

<!-- tooling:start (managed by wordpress-plugin-boilerplate/tooling - do not edit by hand) -->
## Development

This repository uses the standard Fullworks free-plugin tooling, documented in
[wordpress-plugin-boilerplate](https://github.com/alanef/wordpress-plugin-boilerplate/blob/main/CLAUDE.md).

[![Plugin Check](https://github.com/alanef/plugin-broadcaster-auto-responder-for-gravity-forms-project/actions/workflows/checks.yml/badge.svg)](https://github.com/alanef/plugin-broadcaster-auto-responder-for-gravity-forms-project/actions/workflows/checks.yml)

```
plugin-broadcaster-auto-responder-for-gravity-forms-project/                     # repository root: development tooling
├── .github/workflows/             # checks.yml on push/PR, release.yml on tag
├── tests/                         # PHPUnit suite, run inside wp-env
├── .wp-env.json                   # dev :8770, tests :8771
├── composer.json                  # dev dependencies and quality scripts
├── package.json                   # wp-env and test scripts
├── phpunit.xml.dist / run-tests.sh
└── broadcaster-auto-responder-for-gravity-forms/                # the plugin (shipped as-is via .distignore)
```

```bash
composer install && npm install        # dev tools
npm run start                          # http://localhost:8770  (admin / password)
composer run check                     # PHPCompatibility + security sniffs
npm test                               # PHPUnit in the wp-env tests container
composer run build                     # zipped/broadcaster-auto-responder-for-gravity-forms-free.zip
```

Releases: set the version in the plugin header and `readme.txt`, update `CHANGELOG.md`,
tag `vX.Y.Z` and push. CI builds the zip, creates the GitHub release and deploys to
WordPress.org.
<!-- tooling:end -->
