<?php

use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Utils\Utils;

/**
 * Registers a new "wpmlLanguage" input field on ACF options pages queries, and
 * uses translated options page content based on the language code supplied
 * @throws Exception
 */
function wpgraphqlwpml_action_add_options_pages_language_filter(TypeRegistry $type_registry)
{
    foreach (acf_get_options_pages() as $options_page) {
        // Check if the option page should be shown in GraphQL schema
        if (!isset($options_page['show_in_graphql']) || false === (bool)$options_page['show_in_graphql']) {
            continue;
        }
        $type_name = Utils::format_type_name($options_page['graphql_field_name'] ?? $options_page['menu_slug']);
        // Register new options page field with the wpmlLanguage argument
        $options_page['type'] = 'options_page';
        $type_registry->register_field(
            'RootQuery',
            Utils::format_field_name($type_name),
            [
                'type' => $type_name,
                'args' => [
                    'wpmlLanguage' => [
                        'type' => 'String',
                        'description' => 'Filter by WPML language code',
                    ],
                ],
                'description' => sprintf(__('%s options.', 'wp-graphql-acf'), $options_page['page_title']),
                'resolve' => function ($unused, $args) use ($options_page) {
                    // If the wpmlLanguage argument exists in the arguments
                    if (isset($args['wpmlLanguage'])) {
                        $lang = $args['wpmlLanguage'];
                        global $sitepress;
                        // If WPML is installed
                        if ($sitepress) {
                            // Switch the current locale WPML
                            $sitepress->switch_lang($lang);
                            // Override ACF language explicitly, otherwise the output language doesn't change
                            acf_update_setting('current_language', $lang);
                        }
                    }
                    return !empty($options_page) ? $options_page : null;
                }
            ]
        );
    }
}

function wpgraphqlwpml_init_acf()
{
    add_action(
        'graphql_register_types',
        'wpgraphqlwpml_action_add_options_pages_language_filter',
        10,
        1
    );
}
