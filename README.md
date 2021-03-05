# WPGraphQL WPML Extension

* **Contributors:** rburgst
* **Stable tag:** 1.0.1
* **Tested up to:** 5.6.1
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

Note that the primary use case for this plugin is to be used together with Gatsby
where you can perform all filtering of your content within the Gatsby GraphQL schema.

Since Gatsby pulls the whole content out of the wordpress database, this plugin
will ensure that all translations of the translated content is being returned
(instead of only content in the default language).

If you dont intend to use this plugin together with Gatsby be aware that you 
will typically have to be creative with filtering, see #8, #9.

Thanks to https://github.com/shawnhooper/wpml-rest-api
and https://github.com/valu-digital/wp-graphql-polylang

## Caveats

In case you are getting issues querying data (i.e. http 302, or errors), please
first try to see whether you can successfully query data without this plugin.

If it does not work without this plugin, then enabling this plugin wont
magically heal your broken ACF config (see also #5).
This plugin will only help in getting "more" content from your
wordpress installation (which was previously disabled by WPML interfering with queries).

## Limitations

Due to the main use case of using this plugin together with gatsby some often required 
filtering options are not yet available, such as

* filtering menus by locale/language (see [#3](https://github.com/rburgst/wp-graphql-wpml/issues/3))

