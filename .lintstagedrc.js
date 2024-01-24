module.exports = {
	"composer.*": () => "vendor/bin/composer-normalize",
	"package.json": [
		"npm run lint:pkg-json"
	],
	"**/*.js": [
		"npm run lint:js"
	],
	"**/*.php": [
		"npm run lint:php"
	]
};
