#!/bin/bash
set -e

if [ ! -z "$(git status --porcelain -uno)" ]; then
	echo "Your working tree is dirty. Please commit or stash changes."
	exit 1
fi

cd $( dirname "$0" )/..
rm -r wp-includes/js/workbox*
git add -u wp-includes/js
npm install # Get the latest.
git add package.json package-lock.json
npx workbox copyLibraries wp-includes/js/
mv wp-includes/js/workbox-v* wp-includes/js/workbox
git add wp-includes/js/workbox

if [ -z "$(git status --porcelain -uno)" ]; then
	echo "Already up to date"
else
	git status
	git commit -m "Upgrade Workbox to v$(npm list workbox-cli --depth=0 | grep workbox-cli | sed 's/.*@//')" --edit
fi


