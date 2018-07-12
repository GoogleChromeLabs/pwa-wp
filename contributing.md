# PWA-WP Contributing Guide

Thanks for taking the time to contribute!

To start, clone this repository into your WordPress install being used for development:

```bash
cd wp-content/plugins && git clone git@github.com:xwp/pwa-wp.git -wa
```

Lastly, to get the plugin running in your WordPress install, run `composer install` and then activate the plugin via the WordPress dashboard or `wp plugin activate pwa`.

To install the `pre-commit` hook, do `bash dev-lib/install-pre-commit-hook.sh`.

Note that pull requests will be checked against [WordPress-Coding-Standards](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) with PHPCS, and for JavaScript linting is done with ESLint and (for now) JSCS and JSHint.

## Creating a Plugin Build

To create a build of the plugin for installing in WordPress as a ZIP package, do:

```bash
composer install # (if you haven't done so yet)
npm install # (if you haven't done so yet)
npm run build
```

This will create an `pwa.zip` in the plugin directory which you can install. The contents of this ZIP are also located in the `build` directory which you can `rsync` somewhere as well.

To create a build of the plugin as it will be deployed to WordPress.org, run:

```bash
npm run build-release
```

Note that this will currently take much longer than a regular build because it generates the files required for translation. You also must have WP-CLI installed with the [`i18n-command` package](https://github.com/wp-cli/i18n-command).

## PHPUnit Testing

Please run these tests in an environment with WordPress unit tests installed, like [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV).

Run tests:

``` bash
$ phpunit
```

Run tests with an HTML coverage report:

``` bash
$ phpunit --coverage-html /tmp/report
```

When you push a commit to your PR, Travis CI will run the PHPUnit tests and sniffs against the WordPress Coding Standards.

## Creating a Release

Contributors who want to make a new release, follow these steps:

1. Do `npm run build-release` and install the `pwa.zip` onto a normal WordPress install running a stable release build; do smoke test to ensure it works.
2. Bump plugin versions in `package.json` (×1), `package-lock.json` (×1, just do `npm install` first), `composer.json` (×1), and in `pwa.php` (×2: the metadata block in the header and also the `PWA_VERSION` constant).
3. Add changelog entry to readme.
4. Draft blog post about the new release.
5. [Draft new release](https://github.com/xwp/pwa-wp/releases/new) on GitHub targeting the release branch, with the new plugin version as the tag and release title. Attaching the `pwa.zip` build to the release. Include link to changelog in release tag.
6. Run `npm run deploy` to to commit the plugin to WordPress.org.
7. Confirm the release is available on WordPress.org; try installing it on a WordPress install and confirm it works.
8. Publish GitHub release.
9. Create built release tag: `git fetch --tags && git checkout $(git tag | tail -n1) && ./bin/tag-built.sh` (then add link from release)
10. Merge release tag into `master`.
11. Publish release blog post, including link to GitHub release.
12. Close the GitHub milestone and project.
13. Make announcements.
