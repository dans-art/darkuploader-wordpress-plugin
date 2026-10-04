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
        $adapter = $gallery['adapter'] ?? '';
        if (empty($adapter)) {
            continue;
        }
        if ($slug !== 'media-library' && ! \is_plugin_active($gallery['slug'] ?? '')) {
            continue;
        }
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
    $target = (string) $request->get_param('target');
    $files = $request->get_file_params();
    $file = $files['file'] ?? null;

    if (!$file) {
        return new WP_Error('no_file', __('No file uploaded.', 'darkuploader'), ['status' => 400]);
    }

    $max_upload_size = DarkUploaderAdmin\get_max_upload_size();
    if ((int) ($file['size'] ?? 0) > $max_upload_size) {
        $message = sprintf(
            /* translators: %s: maximum upload size in MB */
            __('The uploaded file exceeds the maximum allowed size of %s MB.', 'darkuploader'),
            round($max_upload_size / (1024 * 1024), 2)
        );
        \DarkUploaderLogging\add_error_log($message, $target);
        return new WP_Error(
            'file_too_large',
            $message,
            ['status' => 413]
        );
    }

    $batch_id = (string) $request->get_header('X-Darkup-Batch');

    $galls = DarkUploaderAdmin\get_supported_galleries();
    $gallery = $galls[$target] ?? null;

    if (! is_target_available($target, $gallery)) {
        $message_iv_target = __('Target gallery not found or not supported.', 'darkuploader');
        \DarkUploaderLogging\add_error_log($message_iv_target, $target);
        return new WP_Error('invalid_target', $message_iv_target, ['status' => 400]);
    }

    $adapter = $gallery['adapter'];
    $upload_image_response = $adapter::upload_image($file, $request->get_params(), $batch_id);
    if (is_wp_error($upload_image_response)) {
        \DarkUploaderLogging\add_error_log($upload_image_response->get_error_message(), $target);
        return $upload_image_response;
    }
    return new WP_REST_Response(__('Image uploaded to gallery', 'darkuploader'), 200);
}

/**
 * Checks whether images can be uploaded to the given target.
 *
 * @param string     $target  Target slug from the request, e.g. 'media-library' or 'nextgen-gallery'.
 * @param array|null $gallery The target's entry from get_supported_galleries(), or null if it isn't in there.
 * @return bool
 */
function is_target_available(string $target, ?array $gallery): bool
{
    // Unknown target, or disabled in the "Supported endpoints" setting — this
    // includes the Media Library (get_supported_galleries() only returns the enabled ones).
    if (empty($gallery['adapter'])) {
        return false;
    }

    // The Media Library is enabled (checked above) and part of WordPress,
    // so there is no gallery plugin to check.
    if ($target === 'media-library') {
        return true;
    }

    // The gallery plugin itself must be active.
    return \is_plugin_active($gallery['slug'] ?? '');
}

/**
 * Returns a page of upload log entries for the History tab's DataViews UI.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function get_logs(WP_REST_Request $request)
{
    // Only users who can manage the plugin see everyone's uploads; everyone else
    // is limited to their own, whatever user_id they ask for.
    $user_id = current_user_can(DARKUP_SETTINGS_CAPABILITY) ? $request->get_param('user_id') : get_current_user_id();

    $result = \DarkUploaderLogging\get_all_logs([
        'search' => $request->get_param('search'),
        'gallery' => $request->get_param('gallery'),
        'user_id' => $user_id,
        'date' => $request->get_param('date'),
        'page' => $request->get_param('page'),
        'per_page' => $request->get_param('per_page'),
        'orderby' => $request->get_param('orderby'),
        'order' => $request->get_param('order'),
    ]);

    // Resolve user_id -> a display name here rather than in logging.php, since
    // that's a presentation concern for this REST response, not a storage one.
    $items = array_map(function ($row) {
        $user = get_userdata((int) $row['user_id']);
        return [
            'id' => (int) $row['id'],
            'message' => $row['message'],
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
