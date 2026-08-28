<?php

namespace DarkUploaderAdmin;

if (! defined('ABSPATH')) exit;

/**
 * Fires on every admin page load; registers the plugin's settings so the
 * Settings API knows about them before options.php or any settings page
 * tries to read/save them.
 */
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
    add_settings_field(
        'max_upload_size',
        __('Max Upload Size', 'darkup'),
        '\\DarkUploaderAdmin\field_max_upload_size',
        DARKUP_SLUG . '-general',
        'darkup_general_section'
    );
    add_settings_field(
        'logs',
        __('Keep logs for', 'darkup'),
        '\\DarkUploaderAdmin\field_logs',
        DARKUP_SLUG . '-general',
        'darkup_general_section'
    );
}

/**
 * Renders the "Supported endpoints" settings field: a checkbox for the
 * WordPress Media Library plus one per registered gallery adapter. Gallery
 * checkboxes are disabled (with an explanatory hint) when the corresponding
 * plugin isn't installed/active, since a disabled checkbox is never submitted
 * and therefore can't be enabled by mistake.
 */
function field_endpoints()
{
    $settings = get_option(DARKUP_SETTINGS_OPTION, []);

    $endpoints_selected = $settings['endpoints'] ?? [];

    $galleries = get_supported_galleries(false);

    foreach ($galleries as $key => $gallery) {
        $adapter = $gallery['adapter'];
        $slug = $gallery['slug'];
        if (empty($adapter) || ! method_exists($adapter, 'get_plugin_metadata')) {
            continue;
        }

        $active_plugin = ($slug === 'media-library') ? true : is_plugin_active($slug);


        $gallery_checked = ($endpoints_selected[$key] ?? false) === '1';
        $gallery_infos = $adapter::get_plugin_metadata();
        $name = $gallery_infos['name'] ?? $key;

        $disabled = ($active_plugin) ? '' : 'disabled';
        $hint = ($active_plugin) ? '' : sprintf(esc_html__('The plugin %s is not installed or activated. Install the Plugin in order to use it', 'darkup'), $name);
        printf(
            '<fieldset><label><input class="%6$s" type="checkbox" name="%1$s[endpoints][%4$s]" value="1" %6$s %2$s /> %3$s</label><p class="description">%5$s</p></fieldset>',
            esc_attr(DARKUP_SETTINGS_OPTION),
            checked($gallery_checked, true, false),
            esc_html($name),
            esc_attr($key),
            $hint,
            $disabled
        );
    }
}

/**
 * Renders the "Max Upload Size" settings field. Value is entered/stored as
 * kilobytes on screen but sanitized and persisted in bytes, since that's
 * what wp_max_upload_size() and PHP's own upload_max_filesize deal in.
 * @Todo: Show a warning when the upload_max_filesize is smaller than the users input.
 * Also, use the filter to adust the wp max upload size according to those settings
 */
function field_max_upload_size()
{
    $settings = get_option(DARKUP_SETTINGS_OPTION, []);
    $setting_name = 'max_upload_size';
    $saved_setting = $settings[$setting_name] ?? wp_max_upload_size();
    $upload_size_in_kb = floor(intval($saved_setting) / 1000);

    printf(
        '<fieldset><input type="text" name="%1$s[%2$s]" value="%3$s" /><p class="description">%4$s</p></fieldset>',
        esc_attr(DARKUP_SETTINGS_OPTION),
        $setting_name,
        $upload_size_in_kb,
        esc_html__("Max upload size in kb", 'darkup')
    );
}

/**
 * Renders the "Keep logs for" settings field: a retention-period dropdown.
 * Selecting "no" (no logging) triggers delete_all_logs() from
 * sanitize_settings() once the form is saved.
 */
function field_logs()
{
    $settings = get_option(DARKUP_SETTINGS_OPTION, []);
    $setting_name = 'logs';
    $saved_setting = $settings[$setting_name] ?? '90days';

    $options = [
        '90days' => __('90 days', 'darkup'),
        '60days' => __('60 days', 'darkup'),
        '30days' => __('30 days', 'darkup'),
        '7days' => __('7 days', 'darkup'),
        'forever' => __('Forever', 'darkup'),
        'no' => __('No logging (Existing logs will be deleted', 'darkup'),
    ];

    $options_html = array_map(function ($key) use ($options, $saved_setting) {
        $selected = $saved_setting === $key ? 'selected' : '';
        return sprintf('<option value="%s" %s >%s</option>', esc_attr($key), $selected, esc_html($options[$key]));
    }, array_keys($options));

    printf(
        '<fieldset>
        <label>
        <select name="%1$s[%2$s]" value="%3$s">%4$s</select>
        </label>
        <p class="description">%5$s</p>
        </fieldset>',
        esc_attr(DARKUP_SETTINGS_OPTION),
        $setting_name,
        $saved_setting,
        implode('', $options_html),
        esc_html__('Choose for how long the logs should be kept. Default: 90 days. If set to no logging, existing logs will be deleted.', 'darkup')
    );
}

/**
 * Sanitize callback for the darkup_settings option (registered via
 * register_setting() in register_settings()). Whitelists every key against
 * known-valid values so nothing arbitrary from $_POST reaches the database
 *
 * @param array $input Raw submitted darkup_settings array.
 * @return array Sanitized settings to store.
 */
function sanitize_settings($input)
{
    $sanitized = [];

    $valid_endpoint_keys = array_merge(['media-library'], array_keys(get_supported_galleries(false)));
    $sanitized['endpoints'] = [];
    $input_endpoints = (array) ($input['endpoints'] ?? []);
    foreach ($input_endpoints as $key => $value) {
        if (in_array($key, $valid_endpoint_keys, true) && $value === '1') {
            $sanitized['endpoints'][$key] = '1';
        }
    }

    $submitted_kb = isset($input['max_upload_size']) ? max((int) $input['max_upload_size'], 0) : 0;
    $sanitized['max_upload_size'] = (int) round($submitted_kb * 1000);

    $valid_log_periods = ['90days', '60days', '30days', '7days', 'forever', 'no'];
    $input_log = $input['logs'] ?? '';
    $sanitized['logs'] = in_array($input_log, $valid_log_periods, true)
        ? $input['logs']
        : '90days';

    //Maybe delete the log
    if ($sanitized['logs'] === 'no') {
        \DarkUploaderLogging\delete_all_logs();
    }

    return $sanitized;
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

/**
 * Registers the DarkUploader settings screen under the Media menu. Nested
 * under upload.php.
 */
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

/**
 * Renders the DarkUploader settings screen: a tab bar plus the active tab's
 * template from include/views/. The active tab is whitelisted against
 * $tabs before being used to build the view's file path.
 */
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
 * Note: $only_active filters by whether the endpoint is checked in
 * DarkUploader's own "Supported endpoints" setting — a separate concept
 * from is_plugin_active(), which checks whether the WP plugin itself is
 * installed/active. A gallery can be WP-active but excluded here if the
 * admin hasn't opted into exposing it, and vice versa. On a fresh install
 * with no saved settings, $only_active=true returns an empty array.
 *
 * @param bool $only_active When true (default), only galleries enabled via
 *                           the endpoints setting are returned. Pass false
 *                           to get every supported gallery regardless of
 *                           that setting (used when rendering the settings
 *                           form itself, so unchecked options still show).
 * @return array<string, array{slug: string, adapter: string}>
 */
function get_supported_galleries(bool $only_active = true): array
{
    $settings = get_option(DARKUP_SETTINGS_OPTION, []);

    $endpoints_selected = $settings['endpoints'] ?? [];

    $all_galleries = [
        'media-library' => [
            'slug' => 'media-library',
            'adapter' => '\DarkUploaderAdapter\DarkUploader_WP_Library_Adapter',
        ],
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

    if (!$only_active) {
        return $all_galleries;
    }

    //Filter the galleries out that are not activated
    foreach ($all_galleries as $key => $gallery) {
        if (($endpoints_selected[$key] ?? null) !== '1') {
            //Remove the gallery when not in options
            unset($all_galleries[$key]);
        }
    }

    return $all_galleries;
}
