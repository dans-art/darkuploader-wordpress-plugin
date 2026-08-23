<?php

namespace DarkWPAdmin;

if (! defined('ABSPATH')) exit;

function admin_init() {}

function register_rest_routes()
{
    register_rest_route('darkwp/v1', '/info', array(
        'methods' => 'GET',
        'callback' => '\\DarkWPRest\get_info',
    ));
}

function get_supported_galleries(): array
{
    return [
        'nextgen-gallery' => 'nextgen-gallery/nggallery.php',
        'meow-gallery' => 'meow-gallery/meow-gallery.php',
        'dummy_gall' => 'meow-gallery/meow-gallery.php',
    ];
}
