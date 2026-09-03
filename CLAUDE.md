<!-- tooling:start (managed by wordpress-plugin-boilerplate/tooling - do not edit by hand) -->
# Broadcaster Auto Responder for Gravity Forms - Development Guide

Tooling in this repository is standardised across the Fullworks free plugins. The master
description lives in
[wordpress-plugin-boilerplate/CLAUDE.md](https://github.com/alanef/wordpress-plugin-boilerplate/blob/main/CLAUDE.md).
**Fix tooling problems there first, then roll out** with its `bin/sync-tooling.sh`; never
hand-edit the managed files listed there.

## This repository

| | |
|---|---|
| Plugin directory | `broadcaster-auto-responder-for-gravity-forms/` |
| Main file | `broadcaster-auto-responder-for-gravity-forms/broadcaster-auto-responder-for-gravity-forms.php` |
| Default branch | `main` |
| WordPress.org slug | `broadcaster-auto-responder-for-gravity-forms` |
| wp-env ports | dev `8770`, tests `8771` |
| Version locations | plugin header `Version:`, `readme.txt` `Stable tag:` and `BROADCASTERGF_VERSION` in the main file |

CI fails when the version locations disagree.

## Commands

```bash
composer install && npm install   # first time
composer run check                # PHPCompatibility + WordPress security sniffs
npm run start                     # wp-env (dev :8770, tests :8771, admin/password)
npm test                          # PHPUnit inside the wp-env tests container
npm test -- --filter Foo          # pass PHPUnit args through
composer run build                # zipped/broadcaster-auto-responder-for-gravity-forms-free.zip via wp dist-archive
```

## Release

1. Update `CHANGELOG.md` (move Unreleased to the version and date).
2. Set the version in every location above (no prerelease suffix).
3. `composer run check && npm test`.
4. Commit, tag `vX.Y.Z`, push branch and tag.
5. The `Build Release` workflow re-runs the checks, creates the GitHub release with the zip
   attached and deploys trunk + tag to WordPress.org SVN (needs `SVN_USERNAME` and
   `SVN_PASSWORD` repository secrets).
<!-- tooling:end -->

# Claude Code Instructions for WordPress Plugin Development

When working on WordPress plugin development in this repository, please follow these guidelines:

## Required Reading

Before starting any WordPress plugin development work:

1. **Read the README.md** - Contains the overall project structure, setup instructions, and deployment strategies
2. **Read AI-WORDPRESS-PLUGIN-PROMPT.md** - Contains comprehensive WordPress.org compliance requirements including:
   - Security best practices (input sanitization, output escaping, nonces)
   - Proper namespacing and unique prefixes (minimum 4 characters)
   - WordPress coding standards
   - Common review failures to avoid
   - Proper enqueueing of scripts and styles
   - Translation and internationalization requirements

## Key Development Principles

### Security First
- ALWAYS sanitize all inputs
- ALWAYS escape all outputs at the last possible moment
- ALWAYS use nonces for forms and AJAX requests
- ALWAYS check user capabilities

### WordPress Standards
- Use WordPress functions instead of PHP natives (e.g., `wp_remote_get()` not `curl`)
- Enqueue scripts/styles properly - never include directly
- Use WordPress bundled libraries (jQuery, etc.) - don't download your own
- Follow WordPress naming conventions and coding standards

### Unique Prefixes
- All global functions, constants, and classes must have unique prefixes of at least 4 characters
- Example: Use `MYAWESOMEPLUGIN_` not `MY_` for constants
- Namespaces should also be unique: `MyAwesomePlugin\` not `MyPlugin\`

### No Trademark Violations
- Never use "WordPress" in plugin names (use "WP" instead)
- Avoid using trademarked names unless creating official integrations

### Clean Code
- Use Composer autoloading with classmap
- Organize code into logical directories (admin/, public/, includes/)
- Include proper PHPDoc comments
- Add translator comments for translatable strings with placeholders

## Running Quality Checks

Before committing any code:

```bash
# Check PHP coding standards
npm run lint:php

# Fix PHP coding standards automatically
npm run lint:php:fix

# Run tests
npm run test
```

## Development Workflow

1. Start the development environment: `npm run env:start`
2. Make your changes following the guidelines
3. Test thoroughly in the local environment
4. Run PHPCS to ensure code quality
5. Commit with clear, descriptive messages

## Important Files

- `phpcs.xml.dist` - WordPress coding standards configuration
- `.distignore` - Files to exclude from distribution builds
- `.wp-env.json` - Local development environment configuration

Remember: The goal is to create secure, efficient plugins that will pass WordPress.org review on first submission.