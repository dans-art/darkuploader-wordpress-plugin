<?php

namespace DarkUploaderRest;

use WP_REST_Response;
use DarkUploaderAdmin;
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
    $galls = DarkUploaderAdmin\get_supported_galleries();
    $info = [];
    foreach ($galls as $slug => $gallery) {
        if($slug !== 'media-library'){
            if (empty($gallery['adapter']) || ! \is_plugin_active($gallery['slug'])) {
                continue;
            }
        }
        $adapter = $gallery['adapter'];
        $info[$slug] = $adapter::get_plugin_metadata();
    }
    return new WP_REST_Response($info, 200);
}

/**
 * Handles a POST'd image upload and routes it to the requested gallery adapter.
 *
 * The optional X-Darkup-Batch header lets a client tag several uploads as
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
        return new WP_Error('no_file', esc_html(__('No file uploaded.', 'darkup')), ['status' => 400]);
    }

    $batch_id = (string) $request->get_header('X-Darkup-Batch');

    $galls = DarkUploaderAdmin\get_supported_galleries();
    $gallery = $galls[$target] ?? null;

    if($target !== 'media-library'){
        if (! $gallery || empty($gallery['adapter']) || !\is_plugin_active($gallery['slug'])) {
            return new WP_Error('invalid_target', esc_html(__('Target gallery not found or not supported.', 'darkup')), ['status' => 400]);
        }
    }

    $adapter = $gallery['adapter'];
    $upload_image_response = $adapter::upload_image($file, $request->get_params(), $batch_id);
    if (is_wp_error($upload_image_response)) {
        return $upload_image_response;
    }
    return new WP_REST_Response(esc_html(__('Image uploaded to gallery', 'darkup')), 200);
}

/**
 * Returns a page of upload log entries for the History tab's DataViews UI.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function get_logs(WP_REST_Request $request)
{
    $result = \DarkUploaderLogging\get_all_logs([
        'search' => $request->get_param('search'),
        'gallery' => $request->get_param('gallery'),
        'user_id' => $request->get_param('user_id'),
        'date' => $request->get_param('date'),
        'page' => $request->get_param('page'),
        'per_page' => $request->get_param('per_page'),
    ]);

    // Resolve user_id -> a display name here rather than in logging.php, since
    // that's a presentation concern for this REST response, not a storage one.
    $items = array_map(function ($row) {
        $user = get_userdata((int) $row['user_id']);
        return [
            'id' => (int) $row['id'],
            'label' => $row['message'],
            'gallery' => $row['gallery'],
            'user' => $user ? $user->display_name : '',
            'image_id' => $row['image_id'],
            'message_type' => $row['message_type'],
            'date' => $row['created_at'],
        ];
    }, $result['items']);

    return new WP_REST_Response([
        'items' => $items,
        'total' => $result['total'],
        'total_pages' => $result['total_pages'],
    ], 200);
}
