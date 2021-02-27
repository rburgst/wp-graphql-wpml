<?php

use WPGraphQL\Data\Connection\AbstractConnectionResolver;

/**
 * Plugin Name: WPGraphQL WPML
 * Plugin URI: https://github.com/rburgst/wp-graphql-wpml
 * Description: Adds WPML functionality to WPGraphQL schema.
 * Version: 0.0.1
 * Author: rburgst
 * Author URI: https://github.com/rburgst/
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * WC requires at least: 3.0.0
 * WC tested up to: 4.0.0
 * WPGraphQL requires at least: 0.8.0+
 *
 * @package     WPGraphQL\WPML
 * @author      rburgst
 * @license     GPL-3
 */


function wpgraphqlwpml_is_graphql_request()
{
    // Detect WPGraphQL activation by checking if the main class is defined
    if (!class_exists('WPGraphQL')) {
        return false;
    }

    return is_graphql_http_request();
}


function wpgraphqlwpml_disable_wpml($query_args, $source, $args, $context, $info)
{
    $query_args['suppress_wpml_where_and_join_filter'] = true;
    return $query_args;
}

add_filter('graphql_post_object_connection_query_args', 'wpgraphqlwpml_disable_wpml', 100, 5);

function wpgraphqlwpml_add_post_type_fields(\WP_Post_Type $post_type_object)
{
    $type = ucfirst($post_type_object->graphql_single_name);
    register_graphql_field(
        $post_type_object->graphql_single_name,
        'locale',
        [
            'type' => 'Locale',
            'description' => __('WPML translation link', 'wp-graphql-wpml'),
            'resolve' => function (
                \WPGraphQL\Model\Post $post,
                $args,
                $context,
                $info
            ) {
                $fields = $info->getFieldSelection();
                $language = [
                    'id' => null,
                    'locale' => null,
                ];

                $langInfo = wpml_get_language_information($post->ID);
                $locale = $langInfo['locale'];

                if (!$locale) {
                    return null;
                }

                $language['id'] = $locale;

                if (isset($fields['locale'])) {
                    $language['locale'] = $locale;
                }

                return $language;
            },
        ]
    );
    register_graphql_field(
        $post_type_object->graphql_single_name,
        'localizedWpmlUrl',
        [
            'type' => 'String',
            'description' => __('WPML localized url of the page/post', 'wp-graphql-wpml'),
            'resolve' => function (
                \WPGraphQL\Model\Post $post,
                $args,
                $context,
                $info
            ) {
                global $sitepress;

                $post_id = $post->ID;
                $langInfo = wpml_get_language_information($post_id);
                $languages = $sitepress->get_active_languages();
                $post_language = [];
                foreach ($languages as $language) {
                    if ($language['code'] === $langInfo['language_code']) {
                        $post_language = $language;
                        break;
                    }
                }

                list($thisPost, $translationUrl) = graphql_wpml_get_translation_url($post_id, $post_language);

                return $translationUrl;
            },
        ]
    );
    register_graphql_field(
        $post_type_object->graphql_single_name,
        'translations',
        [
            'type' => ['list_of' => 'Translation'],
            'description' => __('WPML translations', 'wpnext'),
            'resolve' => function (
                \WPGraphQL\Model\Post $post,
                $args,
                $context,
                $info
            ) {
                global $sitepress;
                $translations = [];

                $languages = $sitepress->get_active_languages();
                $orig_post_id = $post->ID;

                foreach ($languages as $language) {
                    $lang_code = array_key_exists('language_code', $languages) ? $language['language_code'] : $language['code'];
                    $post_id = wpml_object_id_filter($orig_post_id, 'post', false, $lang_code);
                    if ($post_id === null || $post_id == $orig_post_id) continue;

                    list($thisPost, $translationUrl) = graphql_wpml_get_translation_url($post_id, $language);

                    $translations[] = array('locale' => $language['default_locale'], 'id' => $post_id, 'post_title' => $thisPost->post_title, 'href' => $translationUrl);
                }

                return $translations;
            },
        ]
    );
    register_graphql_field(
        $post_type_object->graphql_single_name,
        'translated',
        [
            'type' => ['list_of' => $type],
            'description' => __('WPML translated versions of the same post', 'wpnext'),
            'resolve' => function (
                \WPGraphQL\Model\Post $post,
                $args,
                $context,
                $info
            ) {
                global $sitepress;
                $fields = $info->getFieldSelection();
                $translations = [];

                $languages = $sitepress->get_active_languages();

                foreach ($languages as $language) {
                    $orig_post_id = $post->ID;
                    $lang_code = array_key_exists('language_code', $languages) ? $language['language_code'] : $language['code'];
                    $post_id = wpml_object_id_filter($orig_post_id, 'post', false, $lang_code);
                    if ($post_id === null || $post_id == $orig_post_id) continue;

                    $translation = new \WPGraphQL\Model\Post(
                        \WP_Post::get_instance($post_id)
                    );

                    array_push($translations, $translation);
                }

                return $translations;
            },
        ]
    );
}

/**
 * @param int $post_id
 * @param $language
 * @return array
 */
function graphql_wpml_get_translation_url(int $post_id, $language): array
{
    $thisPost = get_post($post_id);
    $baseUrl = apply_filters('WPML_filter_link', $language['url'], $language);
    // for posts it can be that the $language['url'] already contains the translated url of the post
    if (isset($baseUrl) && strpos($baseUrl, $thisPost->post_name) > 0) {
        $translationUrl = $baseUrl;
    } else {
        // note that this requires wpml 4.5.3 to work
        $href = get_permalink($thisPost->ID);
        return array($thisPost, $href);
    }
    return array($thisPost, $translationUrl);
}

function wpgraphqlwpml_action_graphql_register_types()
{
    register_graphql_object_type('Locale', [
        'description' => __('Locale (WPML)', 'wp-graphql-wpml'),
        'fields' => [
            'id' => [
                'type' => [
                    'non_null' => 'ID',
                ],
                'description' => __(
                    'Language ID (WPML)',
                    'wp-graphql-wpml'
                ),
            ],
            'locale' => [
                'type' => 'String',
                'description' => __(
                    'Language locale (WPML)',
                    'wp-graphql-wpml'
                ),
            ],
        ],
    ]);
    register_graphql_object_type('Translation', [
        'description' => __('Translation (WPML)', 'wp-graphql-wpml'),
        'fields' => [
            'id' => [
                'type' => [
                    'non_null' => 'ID',
                ],
                'description' => __(
                    'the id of the referenced translation (WPML)',
                    'wp-graphql-wpml'
                ),
            ],
            'href' => [
                'type' => 'String',
                'description' => __(
                    'the relative link to the translated content (WPML)',
                    'wp-graphql-wpml'
                ),
            ],
            'locale' => [
                'type' => 'String',
                'description' => __(
                    'Language code (WPML)',
                    'wp-graphql-wpml'
                ),
            ],
            'post_title' => [
                'type' => 'String',
                'description' => __(
                    'the title of the translated page (WPML)',
                    'wp-graphql-wpml'
                ),
            ],
        ],
    ]);

    register_graphql_fields('RootQueryToMenuItemConnectionWhereArgs', [
        'language' => [
            'type' => 'String',
            'description' => 'filters the menu items by language',
        ],
    ]);
    register_graphql_fields('RootQueryToMenuConnectionWhereArgs', [
        'language' => [
            'type' => 'String',
            'description' => 'filters the menus by language',
        ],
    ]);
    register_graphql_fields('Menu', [
        'language' => [
            'type' => 'String',
            'description' => 'the language of the menu',
            'resolve' => function (
                \WPGraphQL\Model\Menu $menu,
                $args,
                $context,
                $info
            ) {
                $menuId = $menu->fields['databaseId'];
                $args = array('element_id' => $menuId, 'element_type' => 'nav_menu' );
                $lang_details = apply_filters( 'wpml_element_language_details', $menu, $args );
                if (!isset($lang_details)) {
                    // we have to do it the hard way, reload the term and find out the term taxonomy id
                    global $icl_adjust_id_url_filter_off;
                    $icl_adjust_id_url_filter_off = true;
                    $term = get_term($menuId);
                    $term_taxonomy_id = $term->term_taxonomy_id;
                    $args['element_id'] = $term_taxonomy_id;
                    $lang_details = apply_filters( 'wpml_element_language_details', null, $args );
                }
                if (isset($lang_details)) {
                    return $lang_details->language_code;
                }
                return null;
            }
        ],
    ]);
    foreach (\WPGraphQL::get_allowed_post_types() as $post_type) {
        wpgraphqlwpml_add_post_type_fields(get_post_type_object($post_type));
    }
}

function wpgraphqlwpml__theme_mod_nav_menu_locations(array $args)
{
    foreach ($args as $menu_name => $menu_term_id) {
        $translated = get_term($menu_term_id);
        $args[$menu_name] = $translated->term_id;
    }
    return $args;
}

function wpgraphqlwpml__filter_graphql_connection_query_args(array $args = null)
{
    global $sitepress;

    if (!isset($args['taxonomy'])) {
        return $args;
    }
    if ($args['taxonomy'] !== 'nav_menu') {
        return $args;
    }

    // we have a taxonomy query, remove the includes filter to avoid restricting to localized
    // menu locations
    if ($args['include']) {
        unset($args['include']);
    }

    if (!isset($args['language'])) {
        return $args;
    }
    $target_lang = $args['language'];


    $curLang = $sitepress->get_current_language();
    // Required only when using other than the default language because the
    // menu location for the default language is the original location
    if ($curLang !== $target_lang) {
        $sitepress->switch_lang($target_lang);

        if (!has_filter('theme_mod_nav_menu_locations', 'wpgraphqlwpml__theme_mod_nav_menu_locations')) {
            add_filter('theme_mod_nav_menu_locations', 'wpgraphqlwpml__theme_mod_nav_menu_locations');
        }
//        $args['where']['location'] = wpgraphqlwpml__translate_menu_location(
//            $args['where']['location'],
//            $target_lang
//        );
    }

    unset($target_lang);

    return $args;
}

$wpgraphqlwpml_prev_language = null;

function wpgraphqlwpml__filter_graphql_connection_should_execute(bool $should_execute, AbstractConnectionResolver $resolver)
{
    global $sitepress;
    global $wpgraphqlwpml_prev_language;


    $fieldName = $resolver->getInfo()->fieldName;
    if ($fieldName === 'menuItems') {
        $args = $resolver->getArgs();
        if (!isset($args['where']) || !isset($args['where']['language'])) {
            return $should_execute;
        }
        $new_lang = $args['where']['language'];
        $current_language = $sitepress->get_current_language();
        if ($new_lang !== $current_language) {
            $sitepress->switch_lang($new_lang);
            $wpgraphqlwpml_prev_language = $current_language;
        }
        unset($args['where']['language']);
    } else if ($fieldName === 'menus') {
        $args = $resolver->getArgs();
        if (!isset($args['where']) || !isset($args['where']['language'])) {
            return $should_execute;
        }
        $new_lang = $args['where']['language'];
        $current_language = $sitepress->get_current_language();
        if ($new_lang !== $current_language) {
            $sitepress->switch_lang($new_lang);
            $wpgraphqlwpml_prev_language = $current_language;
        }
        unset($args['where']['language']);
    }
    return $should_execute;
}

function wpgraphqlpwml__filter_graphql_return_field_from_model($field, $key, $model_name, $data, $visibility, $owner, $current_user)
{
    global $sitepress;

    if ($model_name === 'MenuObject' && $key === 'locations' && $field === null) {
        // this is a case where we have a menu item in a different language and no location
        // matches since locations point to the default menu id, therefore, we need to re-load
        // the same menu (term) with the default translation to figure out to which
        // locations this menu maps to
        global $icl_adjust_id_url_filter_off;
        // turn translation of terms / menus back on
        $icl_adjust_id_url_filter_off = false;

        $term_in_current_language = get_term($data->term_id);
        $cur_language_locations = get_nav_menu_locations();
        $target_locations = null;
        foreach ( $cur_language_locations as $location => $id ) {
            $loc = get_term($id);
            if ( isset($loc) && absint( $loc->term_id ) === ( $term_in_current_language->term_id ) ) {
                $target_locations[] = $location;
            }
        }
        $icl_adjust_id_url_filter_off = true;
        $icl_adjust_id_url_filter_off = true;
        return $target_locations;
    }
    return $field;
}

function wpgraphqlwpml__filter_graphql_connection_query(mixed $query, AbstractConnectionResolver $resolver)
{
    return $query;
}

function wpgraphqlwpml__filter_graphql_pre_model_data_is_private($unused, $model_name, $data, $visibility, $owner, $current_user)
{
    if ($model_name === 'MenuObject') {
        return false;
    }
    return null;
}

function wpgraphqlwpml__filter_graphql_connection_ids(array $ids, AbstractConnectionResolver $resolver)
{
    global $wpgraphqlwpml_prev_language;

    if ($resolver->getInfo()->fieldName === 'menus') {
        global $icl_adjust_id_url_filter_off;
        $icl_adjust_id_url_filter_off = true;
        $args = $resolver->getArgs();
        if (!isset($args['where']) && !isset($wpgraphqlwpml_prev_language)) {
            return $ids;
        }
        if (true) {
            return $ids;
        }
//        $result = array();
//        foreach ($ids as $orig_id) {
//            $translated = get_term($orig_id);
//            array_push($result, $translated->term_id);
//        }
//        $sitepress->switch_lang($wpgraphqlwpml_prev_language);
//        unset($wpgraphqlwpml_prev_language);
        return $result;
    }

    return $ids;
}

/**
 * WPGraphQL filter hook function to allow querying in all languages.
 * This function is used to determine when the underlying WPGraphQL
 * query requires that the language be set to 'all'.
 *
 * Hooks into the WPGraphQL filter: `graphql_connection_query_args`
 *
 * Notes:
 *
 * WPML does not provide a hook or other convenient method to access
 * the logic applied when retrieving taxonomies - most of that logic
 * relies on the current language.
 *
 * WPML _does_ have a method to temporarily switch a language. The
 * `switch_lang` method allows for setting toe current language to
 * 'all' - once set this way, taxonomies are not filtered by language.
 */
function wpgraphqlwpml__switch_language_to_all_for_query(array $args)
{
    global $sitepress;

    // Set lang to 'all' when querying for built-in taxonomies
    if (
        isset($args['taxonomy']) &&
        ($args['taxonomy'] == 'post_tag' || $args['taxonomy'] == 'category')
    ) {
        $sitepress->switch_lang('all');
    }

    return $args;
}

function wpgraphqlwpml_action_init()
{
    if (!wpgraphqlwpml_is_graphql_request()) {
        return;
    }

    // prevent wpml to interfere (redirect to translated pages) on every graphql query
    define('WP_ADMIN', TRUE);

    add_action(
        'graphql_register_types',
        'wpgraphqlwpml_action_graphql_register_types',
        10,
        0
    );

    add_filter(
        'graphql_connection_should_execute',
        'wpgraphqlwpml__filter_graphql_connection_should_execute',
        10,
        2
    );

    add_filter(
        'graphql_connection_ids',
        'wpgraphqlwpml__filter_graphql_connection_ids',
        10,
        2
    );

    add_filter(
        'graphql_pre_model_data_is_private',
        'wpgraphqlwpml__filter_graphql_pre_model_data_is_private',
        10,
        6
    );
    add_filter(
        'graphql_return_field_from_model',
        'wpgraphqlpwml__filter_graphql_return_field_from_model',
        10,
        7
    );

    add_filter(
        'graphql_connection_query_args',
        'wpgraphqlwpml__filter_graphql_connection_query_args',
        10,
        2
    );

    // Add filter to adjust WPML language for certain queries
    add_filter(
        'graphql_connection_query_args',
        'wpgraphqlwpml__switch_language_to_all_for_query',
        10,
        2
    );
}


add_action('graphql_init', 'wpgraphqlwpml_action_init');
// END RBA 20200828
