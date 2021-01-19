=== PWA ===
Contributors:      google, xwp, westonruter, albertomedina
Tags:              pwa, progressive web apps, service workers, web app manifest, https
Requires at least: 5.5
Tested up to:      5.6
Stable tag:        0.6.0
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP:      5.6

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

Continue reading more about [Progressive Web Apps](https://web.dev/progressive-web-apps/) (PWA) from Google.

In general a PWA depends on the following technologies to be available:

* [Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
* [Web App Manifest](https://developer.mozilla.org/en-US/docs/Web/Manifest)
* [HTTPS](https://en.wikipedia.org/wiki/HTTPS)

This plugin serves as a place to implement support for these in WordPress with the intention of being proposed for core merge, piece by piece.

This feature plugin is _not_ intended to obsolete the other plugins and themes which turn WordPress sites into PWAs. Rather, this plugin is intended to provide the PWA building blocks and coordination mechanism for these themes and plugins to not reinvent the wheel and also to not conflict with each other. For example, a theme that implements the app shell model should be able to extend the core service worker while a plugin that provides push notifications should be able to do the same. Themes and plugins no longer should have to each create a service worker on their own, something which is inherently problematic because only one service worker can be active at a time: only one service worker can win. If you are developing a plugin or theme that includes a service worker, consider relying on this PWA plugin, or at least only use the built-in implementation as a fallback for when the PWA plugin is not available.

In versions prior to 0.6, no caching strategies were added by default. The only service worker behavior was to serve an offline template when the client's connection is down or the site is down, and also to serve an error page when the server returns with 500 Internal Server Error. As of 0.6, there is a new “Offline browsing” toggle on the Reading Settings screen in the admin. It is disabled by default, but when enabled a [network-first](https://web.dev/offline-cookbook/#network-falling-back-to-cache) caching strategy is registered for navigations so that the offline page won't be shown when accessing previously-accessed pages. The network-first strategy is also used for assets from themes, plugins, and WordPress core. In addition, uploaded images get served with a [stale-while-revalidate](https://web.dev/offline-cookbook/#stale-while-revalidate) strategy. For all the details on these changes, see the [pull request](https://github.com/GoogleChromeLabs/pwa-wp/pull/338).

Documentation for the plugin can be found on the [GitHub project Wiki](https://github.com/GoogleChromeLabs/pwa-wp/wiki).

**Development of this plugin is done [on GitHub](https://github.com/GoogleChromeLabs/pwa-wp). Pull requests welcome. Please see [issues](https://github.com/GoogleChromeLabs/pwa-wp/issues) reported there before going to the [plugin forum](https://wordpress.org/support/plugin/pwa).**

== Frequently Asked Questions ==

Please see the [frequently asked questions](https://github.com/GoogleChromeLabs/pwa-wp/wiki/FAQ) on the GitHub project wiki. Don't see an answer to your question? Please [search the support forum](https://wordpress.org/support/plugin/pwa/) to see if someone has asked your question. Otherwise, please [open a new support topic](https://wordpress.org/support/plugin/pwa/#new-post).

== Changelog ==

For the plugin’s changelog, please see [the Releases page on GitHub](https://github.com/GoogleChromeLabs/pwa-wp/releases).
