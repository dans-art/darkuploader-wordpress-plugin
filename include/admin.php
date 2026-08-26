<?php

namespace DarkWPAdmin;

if (! defined('ABSPATH')) exit;

/**
 * Reserved for admin-side setup. Currently unused.
 */
function admin_init() {}

/**
 * Registers the darkwp/v1 REST routes (/info and /media).
 */
function register_rest_routes()
{
    register_rest_route('darkwp/v1', '/info', array(
        'methods' => 'GET',
        'callback' => '\\DarkWPRest\get_info',
        'permission_callback' => function () {
            return current_user_can('upload_files');
        }
    ));
    register_rest_route('darkwp/v1', '/media', array(
        'methods' => 'POST',
        'callback' => '\\DarkWPRest\upload_media',
        'permission_callback' => function () {
            return current_user_can('upload_files');
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
 * Maps each supported gallery plugin's slug to its main plugin file (for is_plugin_active() checks).
 *
 * @return array<string, string>
 */
function get_supported_galleries(): array
{
    return [
        'nextgen-gallery' => 'nextgen-gallery/nggallery.php',
        'meow-gallery' => 'meow-gallery/meow-gallery.php',
        'dummy_gall' => 'meow-gallery/meow-gallery.php',
    ];
}
