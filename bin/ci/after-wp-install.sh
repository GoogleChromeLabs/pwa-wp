#!/bin/bash

set -e

WP_VERSION=$1
WP_TESTS_DIR=$3

if [[ -z $WP_VERSION ]]; then
	echo "usage: $0 <wp-version>"
	exit 1
fi

if [[ $(php -r "echo PHP_VERSION;") == 8.0* ]]; then
	echo "Installing compatible PHPUnit for use with PHP 8..."

	DIFF=$(
		cat <<-EOF
diff --git a/composer.json b/composer.json
index 5a150f830..f670267f8 100644
--- a/composer.json
+++ b/composer.json
@@ -86,7 +86,17 @@
     ],
     "files": [
       "tests/php/register-wp-cli-commands.php",
-      "docs/includes/register-wp-cli-commands.php"
+      "docs/includes/register-wp-cli-commands.php",
+      "${WP_TESTS_DIR}/includes/phpunit7/MockObject/Builder/NamespaceMatch.php",
+      "${WP_TESTS_DIR}/includes/phpunit7/MockObject/Builder/ParametersMatch.php",
+      "${WP_TESTS_DIR}/includes/phpunit7/MockObject/InvocationMocker.php",
+      "${WP_TESTS_DIR}/includes/phpunit7/MockObject/MockMethod.php"
+    ],
+    "exclude-from-classmap": [
+      "vendor/phpunit/phpunit/src/Framework/MockObject/Builder/NamespaceMatch.php",
+      "vendor/phpunit/phpunit/src/Framework/MockObject/Builder/ParametersMatch.php",
+      "vendor/phpunit/phpunit/src/Framework/MockObject/InvocationMocker.php",
+      "vendor/phpunit/phpunit/src/Framework/MockObject/MockMethod.php"
     ]
   },
   "minimum-stability": "dev",
		EOF
	)

	echo "${DIFF}" | git apply -
	composer require --dev --ignore-platform-reqs --update-with-dependencies phpunit/phpunit:^7.5

	PATH="$(composer config bin-dir --absolute):$PATH"
	echo "done"
fi
