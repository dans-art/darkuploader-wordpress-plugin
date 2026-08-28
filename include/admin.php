<?php

namespace DarkUploaderAdmin;

if (! defined('ABSPATH')) exit;

function admin_init()
{
    register_settings();
}

/**
 * Registers the darkup_settings option with the Settings API and lets
 * anyone with DARKUP_CAPABILITY (not just manage_options) save it — by
 * default options.php requires manage_options for every settings group.
 */
function register_settings()
{
    add_filter('option_page_capability_' . DARKUP_SETTINGS_GROUP, function () {
        return DARKUP_CAPABILITY;
    });

    register_setting(DARKUP_SETTINGS_GROUP, DARKUP_SETTINGS_OPTION, [
        'type' => 'array',
        'sanitize_callback' => '\\DarkUploaderAdmin\sanitize_settings',
        'default' => [],
    ]);

    add_settings_section(
        'darkup_general_section',
        __('General', 'darkup'),
        '__return_false',
        DARKUP_SLUG . '-general'
    );

    add_settings_field(
        'endpoints',
        __('Supported endpoints', 'darkup'),
        '\\DarkUploaderAdmin\field_endpoints',
        DARKUP_SLUG . '-general',
        'darkup_general_section'
    );
}

function field_endpoints()
{
    $settings = get_option(DARKUP_SETTINGS_OPTION, []);

    $endpoints_selected = $settings['endpoints'] ?? [];

    $galleries = get_supported_galleries();

    //Add the Media library checkbox
    $media_library_checked = ($endpoints_selected['media_library'] ?? false) === '1';
    printf(
        '<label><input type="checkbox" name="%1$s[endpoints][media-library]" value="1" %2$s /> %3$s</label>',
        esc_attr(DARKUP_SETTINGS_OPTION),
        checked($media_library_checked, true, false),
        esc_html__("WordPress Media Library", 'darkup')
    );
    foreach ($galleries as $key => $gallery) {
        $adapter = $gallery['adapter'];
        $slug = $gallery['slug'];
        if (empty($adapter) || ! method_exists($adapter, 'get_plugin_metadata')) {
            continue;
        }

        $active_plugin = is_plugin_active($slug);


        $gallery_checked = ($endpoints_selected[$key] ?? false) === '1';
        $gallery_infos = $adapter::get_plugin_metadata();
        $name = $gallery_infos['name'] ?? $key;

        $deactivated = ($active_plugin) ? '' : 'deactivated';
        $hint = ($active_plugin) ? '' : sprintf(esc_html__('The plugin %s is not installed or activated. Install the Plugin in order to use it','darkup'), $name);
        printf(
            '<fieldset><label><input class="%6$s" type="checkbox" name="%1$s[endpoints][%4$s]" value="1" %6$s %2$s /> %3$s</label> %5$s</fieldset>',
            esc_attr(DARKUP_SETTINGS_OPTION),
            checked($gallery_checked, true, false),
            esc_html($name),
            esc_attr($key),
            $hint,
            $deactivated
        );
    }
}

function sanitize_settings($input)
{
    return [
        'delete_source' => ! empty($input['delete_source']),
    ];
}

/**
 * Registers the darkup/v1 REST routes (/info and /media).
 */
function register_rest_routes()
{
    register_rest_route('darkup/v1', '/info', array(
        'methods' => 'GET',
        'callback' => '\\DarkUploaderRest\get_info',
        'permission_callback' => function () {
            return current_user_can(DARKUP_CAPABILITY);
        }
    ));
    register_rest_route('darkup/v1', '/media', array(
        'methods' => 'POST',
        'callback' => '\\DarkUploaderRest\upload_media',
        'permission_callback' => function () {
            return current_user_can(DARKUP_CAPABILITY);
        },
        'args' => [
            'target' => [
                'validate_callback' => function ($param, $request, $key) {
                    return (is_string($param) and preg_match('/^[a-z0-9_-]+$/', $param) === 1) ? true : false;
                },
                'required' => true,
            ]
        ]
    ));
}

function register_menu()
{
    add_submenu_page(
        'upload.php',
        __('DarkUploader', 'darkup'),
        __('DarkUploader', 'darkup'),
        DARKUP_CAPABILITY,
        DARKUP_SLUG,
        '\\DarkUploaderAdmin\render_menu'
    );
}

function render_menu()
{
    if (! current_user_can(DARKUP_CAPABILITY)) return;

    $tabs = [
        'general'   => __('General', 'darkup'),
        'stats-history' => __('Statistics & History', 'darkup'),
        'help'  => __('Help', 'darkup'),
    ];

    $active_tab = (isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs))
        ? sanitize_key($_GET['tab'])
        : 'general';

    require DARKUP_PLUGIN_DIR . '/include/views/admin-page.php';
}


/**
 * Maps each supported gallery plugin's slug to its main plugin file (for
 * is_plugin_active() checks) and the adapter class that handles it.
 *
 * @return array<string, array{slug: string, adapter: string}>
 */
function get_supported_galleries(): array
{
    return [
        'nextgen-gallery' => [
            'slug' => 'nextgen-gallery/nggallery.php',
            'adapter' => '\DarkUploaderAdapter\DarkUploader_NextGen_Adapter',
        ],
        'meow-gallery' => [
            'slug' => 'meow-gallery/meow-gallery.php',
            'adapter' => '\DarkUploaderAdapter\DarkUploader_MeowGallery_Adapter',
        ],
        'dummy_gall' => [
            'slug' => 'meow-gallery/meow-gallery.php',
            'adapter' => ''
        ]
    ];
}
