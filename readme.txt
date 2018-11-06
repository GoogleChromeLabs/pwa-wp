=== PWA ===
Contributors:      xwp, google, automattic
Tags:              pwa, progressive web apps, service workers, web app manifest, https
Requires at least: 4.9
Tested up to:      4.9
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP:      5.2

WordPress feature plugin to bring Progressive Web App (PWA) capabilities to Core

== Description ==

<blockquote cite="https://developers.google.com/web/progressive-web-apps/">
Progressive Web Apps are user experiences that have the reach of the web, and are:

<ul>
<li><a href="https://developers.google.com/web/progressive-web-apps/#reliable">Reliable</a> - Load instantly and never show the downasaur, even in uncertain network conditions.</li>
<li><a href="https://developers.google.com/web/progressive-web-apps/#fast">Fast</a> - Respond quickly to user interactions with silky smooth animations and no janky scrolling.</li>
<li><a href="https://developers.google.com/web/progressive-web-apps/#engaging">Engaging</a> - Feel like a natural app on the device, with an immersive user experience.</li>
</ul>

This new level of quality allows Progressive Web Apps to earn a place on the user's home screen.
</blockquote>

Continue reading more about [Progressive Web Apps](https://developers.google.com/web/progressive-web-apps/) (PWA) from Google.

In general a PWA depends on the following technologies to be available:

* [Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
* [Web App Manifest](https://developer.mozilla.org/en-US/docs/Web/Manifest)
* HTTPS

This plugin serves as a place to implement support for these in WordPress with the intention of being proposed for core merge, piece by piece.

**Development of this plugin is done [on GitHub](https://github.com/xwp/pwa-wp). Pull requests welcome. Please see [issues](https://github.com/xwp/pwa-wp/issues) reported there before going to the [plugin forum](https://wordpress.org/support/plugin/pwa).**

= Web App Manifest =

As noted in a [Google guide](https://developers.google.com/web/fundamentals/web-app-manifest/):

> The [web app manifest](https://developer.mozilla.org/en-US/docs/Web/Manifest) is a simple JSON file that tells the browser about your web application and how it should behave when 'installed' on the users mobile device or desktop.

The plugin exposes the web app manifest via the REST API at `/wp-json/wp/v2/web-app-manifest`. A response looks like:

<pre lang=json>
{
    "name": "WordPress Develop",
    "short_name": "WordPress",
    "description": "Just another WordPress site",
    "lang": "en-US",
    "dir": "ltr",
    "start_url": "https://example.com",
    "theme_color": "#ffffff",
    "background_color": "#ffffff",
    "display": "minimal-ui",
    "icons": [
        {
            "sizes": "192x192",
            "src": "https://example.com/wp-content/uploads/2018/05/example-192x192.png",
            "type": "image/png"
        },
        {
            "sizes": "512x512",
            "src": "https://example.com/wp-content/uploads/2018/05/example.png",
            "type": "image/png"
        }
    ]
}
</pre>

A `rel=manifest` link to this endpoint is added at `wp_head`.

The manifest is populated with default values including:

* `name`: the site title from `get_option('blogname')`
* `short_name`: truncated site title
* `description`: the site tagline from `get_option('blogdescription')`
* `lang`: the site language from `get_bloginfo( 'language' )`
* `dir`: the site language direction from `is_rtl()`
* `start_url`: the home URL from `get_home_url()`
* `theme_color`: a theme's custom background via `get_background_color()`
* `background_color`: also populated with theme's custom background
* `display`: `minimal-ui` is used as the default.
* `icons`: the site icon via `get_site_icon_url()`

There is a `web_app_manifest` filter which is passed the above array so that plugins and themes can customize the manifest.

See [labeled GitHub issues](https://github.com/xwp/pwa-wp/issues?q=label%3Aweb-app-manifest) and see WordPress core tracking ticket [#43328](https://core.trac.wordpress.org/ticket/43328).

= Service Workers =

As noted in a [Google primer](https://developers.google.com/web/fundamentals/primers/service-workers/):

> Rich offline experiences, periodic background syncs, push notifications—functionality that would normally require a native application—are coming to the web. Service workers provide the technical foundation that all these features rely on.

Only one service worker can be controlling a page at a time. This has prevented themes and plugins from each introducing their own service workers because only one wins. So the first step at adding support for service workers in core is to provide an API for themes and plugins to register scripts and then have them concatenated into a script that is installed as the service worker. There are two such concatenated service worker scripts that are made available: one for the frontend and one for the admin. The frontend service worker is installed under the `home('/')` scope and the admin service worker is installed under the `admin_url('/')` scope.

The API is implemented using the same interface as WordPress uses for registering scripts; in fact `WP_Service_Worker_Scripts` is a subclass of `WP_Scripts`. The instance of this class is accessible via `wp_service_workers()->get_registry()`. Instead of using `wp_register_script()` the service worker scripts are registered using `wp_register_service_worker_script()`. This function accepts two parameters:

* `$handle`: The service worker script handle which can be used to mark the script as a dependency for other scripts.
* `$args`: An array of additional service worker script arguments as `$key => $value` pairs:
	* `$src`: Required. The URL to the service worker _on the local filesystem_ or a callback function which returns the script to include in the service worker.
	* `$deps`: An array of service worker script handles that a script depends on.

Note that there is no `$ver` (version) parameter because browsers do not cache service workers so there is no need to cache bust them.

Service worker scripts should be registered on the `wp_front_service_worker` and/or `wp_admin_service_worker` action hooks, depending on whether they should be active for the frontend service worker, the admin service worker, or both of them. The hooks are passed the `WP_Service_Worker_Scripts` instance, so you can optionally access its `register()` method directly, which `wp_register_service_worker_script()` is a simple wrapper of.

Here are some examples:

<pre lang=php>
function register_foo_service_worker_script( $scripts ) {
	// $scripts->register() is the same as wp_register_service_worker_script().
	$scripts->register(
		'foo', // Handle.
		array(
			'src'  => plugin_dir_url( __FILE__ ) . 'foo.js', // Source.
			'deps' => array( 'app-shell' ), // Dependency.
		)
	);
}
// Register for the frontend service worker.
add_action( 'wp_front_service_worker', 'register_foo_service_worker_script' );

function register_bar_service_worker_script( $scripts ) {
	$scripts->register(
		'bar',
		array(
			// Use a script render callback instead of a source file.
			'src'  => function() {
				return 'console.info( "Hello admin!" );';
			},
			'deps' => array(), // No dependencies (can also be omitted).
		)
	);
}
// Register for the admin service worker.
add_action( 'wp_admin_service_worker', 'register_bar_service_worker_script' );

function register_baz_service_worker_script( $scripts ) {
	$scripts->register( 'baz', array( 'src' => plugin_dir_url( __FILE__ ) . 'baz.js' ) );
}
// Register for both the frontend and admin service worker.
add_action( 'wp_front_service_worker', 'register_baz_service_worker_script' );
add_action( 'wp_admin_service_worker', 'register_baz_service_worker_script' );
</pre>

See [labeled GitHub issues](https://github.com/xwp/pwa-wp/issues?q=label%3Aservice-workers) and see WordPress core tracking ticket [#36995](https://core.trac.wordpress.org/ticket/36995).

= Caching =
Service Workers in the feature plugin are using [Workbox](https://developers.google.com/web/tools/workbox/) to power a higher-level PHP abstraction for themes and plugins to indicate the routes and the caching strategies in a declarative way. Since only one handler can be used per one route then conflicts are also detected and reported in console when using debug mode.

The API abstraction allows registering routes for caching and urls for precaching using the following two functions:
1. `wp_register_service_worker_caching_route()`: accepts the following two parameters:
* `$route`: Route regular expression, without delimiters.
* `$args`: An array of additional route arguments as `$key => $value` pairs:
	* `$strategy`: Required. Strategy, can be `WP_Service_Worker_Caching_Routes::STRATEGY_STALE_WHILE_REVALIDATE`, `WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST`,
                   `WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_FIRST`, `WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_ONLY`,
                   `WP_Service_Worker_Caching_Routes::STRATEGY_NETWORK_ONLY`.
	* `$cache_name`: Name to use for the cache.
	* `$plugins`: Array of plugins with configuration. The key of each plugin in the array must match the plugin's name.
                  See https://developers.google.com/web/tools/workbox/guides/using-plugins#workbox_plugins.

2. `wp_register_service_worker_precaching_route()`: accepts the following two parameters:
 * `$url`: URL to cache.
* `$args`: An array of additional route arguments as `$key => $value` pairs:
	* `$revision`: Revision, optional.

Examples of using the API:
 <pre lang=php>
wp_register_service_worker_caching_route(
    '/wp-content/.*\.(?:png|gif|jpg|jpeg|svg|webp)(\?.*)?$',
        array(
            'strategy'  => WP_Service_Worker_Caching_Routes::STRATEGY_CACHE_FIRST,
            'cacheName' => 'images',
            'plugins'   => array(
                'expiration'        => array(
                    'maxEntries'    => 60,
                    'maxAgeSeconds' => 60 * 60 * 24,
            ),
        ),
    )
);
</pre>
 <pre lang=php>
wp_register_service_worker_precaching_route(
        'https://example.com/wp-content/themes/my-theme/my-theme-image.png',
        array(
            'revision' => get_bloginfo( 'version' ),
        ),
    )
);
</pre>
= Offline / 500 error handling =
The feature plugins offers improved offline experience by displaying a custom template when user is offline instead of the default message in browser. Same goes for 500 errors -- a template is displayed together with error details.

Themes can override the default template by using `error.php`, `offline.php`, and `500.php` in you theme folder. `error.php` is a general template for both offline and 500 error pages and it is overriden by `offline.php` and `500.php` if they exist.

Note that the templates should use `wp_service_worker_error_message_placeholder()` for displaying the offline / error messages. Additionally, on the 500 error template the details of the error can be displayed using the function `wp_service_worker_error_details_template( $output )`.

For development purposes the offline and 500 error templates are visible on the following URLs on your site:
- `https://your-site-name.com/?wp_error_template=offline`;
- `https://your-site-name.com/?wp_error_template=500`

Default value for `$output` is the following:
`<details id="error-details"><summary>' . esc_html__( 'More Details', 'pwa' ) . '</summary>{{{error_details_iframe}}}</details>` where `{{{error_details_iframe}}}` will be replaced by the iframe.

In case of using the `<iframe>` within the template `{{{iframe_src}}}` and `{{{iframe_srcdoc}}}` are available as well.

For example this could be done:
 <pre lang=php>
wp_service_worker_error_details_template(
    '<details id="error-details"><summary>' . esc_html__( 'More Details', 'pwa' ) . '</summary><iframe style="width:100%" src="{{{iframe_src}}}" data-srcdoc="{{{iframe_srcdoc}}}"></iframe></details>'
);
</pre>
= HTTPS =

HTTPS is a prerequisite for progressive web apps. A service worker is only able to be installed on sites that are served as HTTPS. For this reason core's support for HTTPS needs to be further improved, continuing the great progress made over the past few years.

At the moment the plugin provides an API to detection of whether a site supports HTTPS. Building on that it's intended that this can then be used to present a user with an opt-in to switch over to HTTPS, which will also then need to include support for rewriting URLs from HTTP to HTTPS. See [labeled GitHub issues](https://github.com/xwp/pwa-wp/issues?q=label%3Ahttps) and see WordPress core tracking ticket [#28521](https://core.trac.wordpress.org/ticket/28521).

You can optionally add an [HSTS header](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Strict-Transport-Security) (HTTP `Strict-Transport-Security`). This indicates to the browser to only load the site with HTTPS, not HTTP.

<pre lang=php>
/**
 * Adds an HSTS header to the response.
 *
 * @param array $headers The headers to filter.
 * @return array $headers The filtered headers.
 */
add_filter( 'wp_headers', function( $headers ) {
	$headers['Strict-Transport-Security'] = 'max-age=3600'; // Or another max-age.
	return $headers;
} );
</pre>

This can prevent a case where users initially visit the HTTP version of the site, and are redirected to a malicious site before a redirect to the proper HTTPS version.

The [wp_headers](https://developer.wordpress.org/reference/hooks/wp_headers/) filter allows you to add a `Strict-Transport-Security` header for this.

Please see the [documentation](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Strict-Transport-Security#Directives) for the directives, including the `max-age`.

== Changelog ==

= 0.1.0 (2018-07-12) =

* Adds support for web app manifests which can be customized via a `web_app_manifest` filter.
* Adds initial support for service workers via `wp_register_service_worker()`.
* Adds an API for detecting whether HTTPS is available for a given site.

