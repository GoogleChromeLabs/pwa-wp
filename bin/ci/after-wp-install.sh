#!/bin/bash

set -e

WP_VERSION=$1
WP_TESTS_DIR=$2

if [[ -z $WP_VERSION ]]; then
	echo "usage: $0 <wp-version>"
	exit 1
fi
