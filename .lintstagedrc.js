module.exports = {
	"composer.*": () => "vendor/bin/composer-normalize",
	"package.json": [
		"npm run lint:pkg-json"
	],
	"**/*.js": [
		"npm run lint:js"
	],
	"**/!(pwa).php": [
		"npm run lint:php"
	],
	"pwa.php": [
		"vendor/bin/phpcs --runtime-set testVersion 5.2-"
	]
};
