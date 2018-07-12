# PWA-WP Contributing Guide

Thanks for taking the time to contribute!

To start, clone this repository into any WordPress install being used for development:

```bash
git clone git@github.com:xwp/pwa-wp.git wp-content/plugins/pwa
cd wp-content/plugins/pwa
npm install
```

Running `npm install` will also automatically run `composer install`; a `pre-commit` hook will also automatically be installed for you via [husky](https://www.npmjs.com/package/husky).

You may then just activate the plugin in the admin or via [WP-CLI](https://wp-cli.org/): `wp plugin activate pwa`.

Your WordPress install must be configured to serve responses over HTTPS. Without this, your browser will refuse to install the service worker. The exception here is if your WordPress install is located at `localhost`, in which case HTTPS is not required. But in general, WordPress development environments are often located at domains `example.test` or `example.local`, and for them:

* On VVV, see [Setting Up HTTPS](https://varyingvagrantvagrants.org/docs/en-US/references/https/).
* On [Local by Flywheel](https://local.getflywheel.com/), installation of SSL certificates is supported in the UI.
* On [Chassis](http://docs.chassis.io/), see [Add and configure OpenSSL](https://github.com/Chassis/Chassis/issues/20).

Pull requests will be checked against [WordPress-Coding-Standards](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) with PHPCS, and for JavaScript linting is done with ESLint. The `pre-commit` hook will runs these tests will automatically prior to pushing.

## Creating a Plugin Build

To create a build of the plugin for installing in WordPress as a ZIP package, do:

```bash
npm run build
```

This will create an `pwa.zip` in the plugin directory which you can install. The contents of this ZIP are also located in the `build` directory which you can `rsync` somewhere as well.

To create a build of the plugin as it will be deployed to WordPress.org, run:

```bash
npm run build-release
```

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
4. Draft blog post about the new release, presumably on Make/Core.
5. [Draft new release](https://github.com/xwp/pwa-wp/releases/new) on GitHub targeting the release branch, with the new plugin version as the tag and release title. Attaching the `pwa.zip` build to the release. Include link to changelog in release tag.
6. Run `npm run deploy` to to commit the plugin to WordPress.org.
7. Confirm the release is available on WordPress.org; try installing it on a WordPress install and confirm it works.
8. Publish GitHub release.
9. Create built release tag: `git fetch --tags && git checkout $(git tag | tail -n1) && ./bin/tag-built.sh` (then add link from release)
10. Merge release tag into `master`.
11. Publish release blog post, including link to GitHub release.
12. Close the GitHub milestone and project.
13. Make announcements.
