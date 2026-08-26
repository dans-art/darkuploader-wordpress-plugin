<?php

namespace DarkWPAdapter;

if (! defined('ABSPATH')) exit;

/**
 * Contract implemented by each supported gallery plugin's upload adapter.
 */
interface DarkWP_Gallery_Adapter
{
    /**
     * Describes the adapter and the upload-form fields it accepts.
     *
     * @return array
     */
    public static function get_plugin_metadata(): array;

    /**
     * Uploads a file into the target gallery plugin.
     *
     * @param array  $file     A single entry from WP_REST_Request::get_file_params().
     * @param array  $metadata Raw request params, keyed by the field ids from get_plugin_metadata().
     * @param string $batch_id Client-supplied X-Darkwp-Batch header value, or '' if none was sent.
     *                         Lets several uploads from the same export be correlated, e.g. to
     *                         reuse a gallery created for the first image of the batch.
     * @return bool|\WP_Error
     */
    public static function upload_image($file, array $metadata, string $batch_id = ''): bool|\WP_Error;
}

/**
 * Shared helper for adapters that want to correlate uploads sharing the same
 * X-Darkwp-Batch header — e.g. to reuse a gallery created for the first image
 * of a batch instead of creating a new one per image.
 */
trait DarkWP_Gallery_Adapter_Batch
{
    /**
     * Builds a transient key scoped to this batch id, the current user, and the
     * adapter class using it — so concurrent uploads from different batches,
     * users, or gallery adapters can never read or overwrite each other's state.
     *
     * @param string $batch_id Client-supplied X-Darkwp-Batch header value, or '' if none was sent.
     * @return string Empty string when there's no batch id to scope by.
     */
    protected static function get_batch_transient_key(string $batch_id): string
    {
        if (empty($batch_id)) {
            return '';
        }
        return 'darkwp_batch_' . md5(static::class . '|' . $batch_id . '|' . get_current_user_id());
    }
}
