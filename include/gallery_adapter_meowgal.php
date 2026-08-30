<?php

namespace DarkUploaderAdapter;

if (! defined('ABSPATH')) exit;

use DarkUploaderAdapter\DarkUploader_Gallery_Adapter;

/**
 * Adapter for Meow Gallery. Not implemented yet — upload_image() is a stub.
 */
class DarkUploader_MeowGallery_Adapter implements DarkUploader_Gallery_Adapter
{

    /**
     * Describes this adapter and the upload-form fields it accepts.
     *
     * @return array
     */
    public static function get_plugin_metadata(): array
    {
        //Basic info
        $info = [
            'slug' => 'meow-gallery',
            'name' => 'Meow Gallery',
            'meta' => []
        ];
        return $info;
    }

    /**
     * @param array  $file     A single entry from WP_REST_Request::get_file_params().
     * @param array  $metadata Raw request params, keyed by the field ids from get_plugin_metadata().
     * @param string $batch_id Client-supplied X-Darkup-Batch header value, or '' if none was sent.
     * @return bool|\WP_Error
     * @todo Not implemented — always reports success without uploading anything.
     */
    public static function upload_image($file, array $metadata, string $batch_id = ''): bool|\WP_Error
    {
        return true;
    }
}
