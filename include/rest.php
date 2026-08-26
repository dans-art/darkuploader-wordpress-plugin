<?php

namespace DarkWPRest;

use WP_REST_Response;
use DarkWPAdmin;
use DarkWPAdapter\DarkWP_NextGen_Adapter;
use DarkWPAdapter\DarkWP_MeowGallery_Adapter;
use WP_Error;
use WP_REST_Request;

if (! defined('ABSPATH')) exit;

/**
 * Lists the installed/active gallery plugins and their upload-form metadata.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function get_info(\WP_REST_Request $request)
{
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
                    $gallery_info = ['slug' => 'invalid', 'name' => __('Invalid Type', 'darkwp')];
                    break;
            }
            $info[$slug] = $gallery_info;
        }
    }
    return new WP_REST_Response($info, 200);
}

/**
 * Handles a POST'd image upload and routes it to the requested gallery adapter.
 *
 * The optional X-Darkwp-Batch header lets a client tag several uploads as
 * belonging to the same export, so a gallery created for the first image can
 * be reused for the rest. The batch id is passed through to the adapter,
 * which is responsible for scoping any state it keeps per-batch.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function upload_media(WP_REST_Request $request)
{
    $target = $request->get_param('target');
    $files = $request->get_file_params();
    $file = $files['file'] ?? null;

    if (!$file) {
        return new WP_Error('no_file', esc_html(__('No file uploaded.', 'darkwp')), ['status' => 400]);
    }

    $batch_id = (string) $request->get_header('X-Darkwp-Batch');

    switch ($target) {
        case 'nextgen-gallery':
            $upload_image_response = DarkWP_NextGen_Adapter::upload_image($file, $request->get_params(), $batch_id);
            if (is_wp_error($upload_image_response)) {
                return $upload_image_response;
            }
            return new WP_REST_Response(esc_html(__('Image uploaded to gallery', 'darkwp')), 200);

        default:
            return new WP_Error('invalid_target', esc_html(__('Target gallery not found or not supported.', 'darkwp')), ['status' => 400]);
    }
}
