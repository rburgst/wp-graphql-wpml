<?php
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
                $post_id = $post->ID;
                $langInfo = wpml_get_language_information($post_id);
                $languages = apply_filters('wpml_active_languages', NULL);
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
                $translations = [];

                $languages = apply_filters('wpml_active_languages', null);
                $orig_post_id = $post->ID;

                foreach ($languages as $language) {
                    $post_id = wpml_object_id_filter($orig_post_id, 'post', false, $language['language_code']);
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

                $languages = apply_filters('wpml_active_languages', null);

                foreach ($languages as $language) {
                    $orig_post_id = $post->ID;
                    $post_id = wpml_object_id_filter($orig_post_id, 'post', false, $language['language_code']);
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

//                    $translationUrl = apply_filters('wpml_permalink', $orig_url, $language['language_code'], true);

    $baseUrl = apply_filters('WPML_filter_link', $language['url'], $language);
//                    // for posts it can be that the $language['url'] already contains the translated url of the post
    if (strpos($baseUrl, $thisPost->post_name) > 0) {
        $translationUrl = $baseUrl;
    } else {
        $href = get_permalink($thisPost->ID);
        global $sitepress;
        $root_url = $sitepress->language_url($language['code']);
        $siteUrl = get_site_url();
        $hrefPath = calculate_rel_path($href, $thisPost);
        $relativePath = str_replace($siteUrl, "", $hrefPath);
        $translationUrl = $root_url . $relativePath;
        if (strlen($relativePath) > 0 && substr($relativePath, -1) !== "/") {
            $translationUrl .= "/";
        }
        $translationUrl .= $thisPost->post_name . "/";
    }
    return array($thisPost, $translationUrl);
}

/**
 * @param string $href
 * @param WP_Post $thisPost
 * @return string
 */
function calculate_rel_path(string $href, WP_Post $thisPost): string
{
    $hrefPath = dirname($href);
    if ($thisPost->post_parent > 0) {
        $cur_post = get_post($thisPost->post_parent);
        $rel_path = calculate_rel_path($hrefPath, $cur_post);
        return $rel_path . "/" . $cur_post->post_name;
    }
    return $hrefPath;
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
    foreach (\WPGraphQL::get_allowed_post_types() as $post_type) {
        wpgraphqlwpml_add_post_type_fields(get_post_type_object($post_type));
    }
}

function wpgraphqlwpml_action_init()
{
    if (!wpgraphqlwpml_is_graphql_request()) {
        return;
    }
    add_action(
        'graphql_register_types',
        'wpgraphqlwpml_action_graphql_register_types',
        10,
        0
    );
}


add_action('graphql_init', 'wpgraphqlwpml_action_init');
// END RBA 20200828
