#!/bin/bash

set -e

WP_VERSION=$1
WP_TESTS_DIR=$2

if [[ -z $WP_VERSION ]]; then
	echo "usage: $0 <wp-version>"
	exit 1
fi

case "$WP_VERSION" in
	"4.9")
		gb_version="4.9.0"
		;;
	"5.0" | "5.1")
		gb_version="6.9.0"
		;;
	"5.2")
		gb_version="7.6.1"
		;;
	*)
		# WP 5.3 onwards can use the latest version of Gutenberg.
		gb_version="trunk"
		;;
esac

if [[ "$gb_version" != "" ]]; then
	echo -n "Installing Gutenberg ${gb_version}..."

	url_path=$([ $gb_version == "trunk" ] && echo "trunk" || echo "tags/${gb_version}")
	gutenberg_plugin_svn_url="https://plugins.svn.wordpress.org/gutenberg/${url_path}/"
	svn export -q "$gutenberg_plugin_svn_url" "$WP_CORE_DIR/src/wp-content/plugins/gutenberg"
	echo "done"
fi

if [[ $(php -r "echo PHP_VERSION;") == 8.0* ]]; then
	echo "Installing compatible PHPUnit for use with PHP 8..."

	DIFF=$(
		cat <<-EOF
diff --git a/composer.json b/composer.json
index 562c54a..0c6a247 100644
--- a/composer.json
+++ b/composer.json
@@ -31,6 +31,20 @@
       }
     }
   },
+  "autoload-dev": {
+     "files": [
+       "${WP_TESTS_DIR}/includes/phpunit7/MockObject/Builder/NamespaceMatch.php",
+       "${WP_TESTS_DIR}/includes/phpunit7/MockObject/Builder/ParametersMatch.php",
+       "${WP_TESTS_DIR}/includes/phpunit7/MockObject/InvocationMocker.php",
+       "${WP_TESTS_DIR}/includes/phpunit7/MockObject/MockMethod.php"
+     ],
+     "exclude-from-classmap": [
+       "vendor/phpunit/phpunit/src/Framework/MockObject/Builder/NamespaceMatch.php",
+       "vendor/phpunit/phpunit/src/Framework/MockObject/Builder/ParametersMatch.php",
+       "vendor/phpunit/phpunit/src/Framework/MockObject/InvocationMocker.php",
+       "vendor/phpunit/phpunit/src/Framework/MockObject/MockMethod.php"
+     ]
+  },
   "minimum-stability": "dev",
   "prefer-stable": true,
   "scripts": {
		EOF
	)

	echo "${DIFF}" | git apply -
	composer require --dev --ignore-platform-reqs --update-with-dependencies phpunit/phpunit:^7.5

	PATH="$(composer config bin-dir --absolute):$PATH"
	echo "done"
fi
