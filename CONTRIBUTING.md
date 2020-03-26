# How to Contribute

We'd love to accept your patches and contributions to this project. There are
just a few small guidelines you need to follow.

## Getting Started

To start, clone this repository into any WordPress install being used for development:

```bash
git clone git@github.com:GoogleChromeLabs/pwa-wp.git wp-content/plugins/pwa
cd wp-content/plugins/pwa
npm install
composer install
npm run build
```

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

1. Do `npm run build` and install the `pwa.zip` onto a normal WordPress install running a stable release build; do smoke test to ensure it works.
2. Bump plugin versions in `pwa.php` (×2: the metadata block in the header and also the `PWA_VERSION` constant), and the `Stable tag` in `readme.txt`.
3. Add changelog entry to readme.
4. Draft blog post about the new release, presumably on Make/Core.
5. [Draft new release](https://github.com/GoogleChromeLabs/pwa-wp/releases/new) on GitHub targeting the release branch, with the new plugin version as the tag and release title. Attaching the `pwa.zip` build to the release. Include link to changelog in release tag.
6. Run `npm run deploy` to to commit the plugin to WordPress.org.
7. Confirm the release is available on WordPress.org; try installing it on a WordPress install and confirm it works.
8. Publish GitHub release.
9. Create built release tag: `git fetch --tags && git checkout $(git tag | tail -n1) && ./bin/tag-built.sh` (then add link from release)
10. Merge release tag into `master`.
11. Publish release blog post, including link to GitHub release.
12. Close the GitHub milestone and project.
13. Make announcements.

## Contributor License Agreement

Contributions to this project must be accompanied by a Contributor License
Agreement. You (or your employer) retain the copyright to your contribution;
this simply gives us permission to use and redistribute your contributions as
part of the project. Head over to <https://cla.developers.google.com/> to see
your current agreements on file or to sign a new one.

You generally only need to submit a CLA once, so if you've already submitted one
(even if it was for a different project), you probably don't need to do it
again.

## Code reviews

All submissions, including submissions by project members, require review. We
use GitHub pull requests for this purpose. Consult
[GitHub Help](https://help.github.com/articles/about-pull-requests/) for more
information on using pull requests.

## Community Guidelines

This project follows [Google's Open Source Community
Guidelines](https://opensource.google.com/conduct/).
