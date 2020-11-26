# WPGraphQL WPML Extension

* **Contributors:** rburgst
* **Stable tag:** 0.0.1
* **Tested up to:** 5.3.2
* **Requires at least:** 4.9
* **Requires PHP:** 7.0
* **Requires WPGraphQL:** 0.8.0+
* **Requires WPML:** 4.4.5+
* **Tags:** GraphQL, WPML, WPGraphQL
* **License:** GPL-3
* **License URI:** https://www.gnu.org/licenses/gpl-3.0.html

## Description

Adds WPML functionality to wpgraphql so that translations can be queried
and WPML default filters which disable the listing of all content
are disabled.

Thanks to https://github.com/shawnhooper/wpml-rest-api
and https://github.com/valu-digital/wp-graphql-polylang

## Caveats

In case you are getting issues querying data (i.e. http 302, or errors), please
first try to see whether you can successfully query data without this plugin.

If it does not work without this plugin, then enabling this plugin wont
magically heal your broken ACF config (see also #5).
This plugin will only help in getting "more" content from your
wordpress installation (which was previously disabled by WPML interfering with queries).
