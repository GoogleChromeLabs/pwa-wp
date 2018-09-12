#!/bin/bash
set -e

if [ ! -z "$(git status --porcelain -uno)" ]; then
	echo "Your working tree is dirty. Please commit or stash changes."
	exit 1
fi

cd $( dirname "$0" )/..
rm -r wp-includes/js/workbox-v*
git add -u wp-includes/js
npx workbox copyLibraries wp-includes/js/
git add wp-includes/js/workbox-v*
workbox_dir=$(ls -d wp-includes/js/workbox-v*)
sed -i.bak "s:wp-includes/js/workbox-v.*/:$workbox_dir/:" wp-includes/class-wp-service-workers.php
rm wp-includes/class-wp-service-workers.php.bak
git add wp-includes/class-wp-service-workers.php

if [ -z "$(git status --porcelain -uno)" ]; then
	echo "Already up to date"
else
	git status
	git commit -m "Upgrade Workbox to $( sed 's/.*-v/v/' <<< $workbox_dir )" --edit
fi


