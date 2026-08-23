<?php

namespace DarkWPRest;

use WP_REST_Response;
use DarkWPAdmin;
use DarkWPAdapter\DarkWP_NextGen_Adapter;
use DarkWPAdapter\DarkWP_MeowGallery_Adapter;
use WP_Error;

if (! defined('ABSPATH')) exit;


function get_info(\WP_REST_Request $request)
{
    //Add capabilities check / auth
    if (!current_user_can('upload_files')) {
        $info = new WP_Error('no_permission', __('You have no permissions to upload media', 'darkwp'));
        $response = new WP_REST_Response($info);
        $response->set_status(400);
        return $response;
    }

    $galls = DarkWPAdmin\get_supported_galleries();
    $info = [];
    foreach ($galls as $slug => $path) {
        if (\is_plugin_active($path)) {
            switch ($slug) {
                case 'nextgen-gallery':
                    $gallery_info = DarkWP_NextGen_Adapter::get_plugin_metadata();
                    break;
                case 'meow-gallery':
                    $gallery_info = DarkWP_MeowGallery_Adapter::get_plugin_metadata();
                    break;

                default:
                    $gallery_info = ['slug' => 'invalid', 'name' => __('Invalid Type','darkwp')];
                    break;
            }
            $info[$slug] = $gallery_info;
        }
    }
    $response = new WP_REST_Response($info);
    $response->set_status(200);
    return $response;
}
