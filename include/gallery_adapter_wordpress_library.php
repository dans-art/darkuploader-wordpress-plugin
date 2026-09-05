<?php

namespace DarkUploaderAdapter;

if (! defined('ABSPATH')) exit;

use DarkUploaderAdapter\DarkUploader_Gallery_Adapter;
use WP_Error;


/**
 * Adapter that routes uploads into the native WordPress Media Library,
 * the same way core's wp/v2/media REST endpoint does.
 */
class DarkUploader_WP_Library_Adapter implements DarkUploader_Gallery_Adapter
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
            'slug' => 'media-library',
            'name' => 'WordPress Media Library',
            'meta' => []
        ];
        $meta = [
            [
                'id' => 'title',
                'label' => esc_html(__('Title', 'darkuploader')),
                'type' => 'text',
                'required' => false,
                'hint' => esc_html(__('Enter the title for the image', 'darkuploader')),
                'placeholder' => '$(Xmp.dc.title)',
            ],
            [
                'id' => 'alt_text',
                'label' => esc_html(__('Alt text', 'darkuploader')),
                'type' => 'text',
                'required' => false,
                'hint' => esc_html(__('Enter the alt text for the image', 'darkuploader')),
                'placeholder' => '$(Xmp.dc.title)',
            ],
            [
                'id' => 'description',
                'label' => esc_html(__('Description', 'darkuploader')),
                'type' => 'text',
                'required' => false,
                'hint' => esc_html(__('Write a description for the image', 'darkuploader')),
                'placeholder' => '$(Xmp.dc.description)',
            ],
            [
                'id' => 'caption',
                'label' => esc_html(__('Caption', 'darkuploader')),
                'type' => 'text',
                'required' => false,
                'hint' => esc_html(__('Add the caption for the image', 'darkuploader')),
                'placeholder' => '$(Xmp.dc.subject)',
            ],
        ];
        $info['meta'] = $meta;

        return $info;
    }
    /**
     * Uploads the file into the Media Library as a standalone attachment,
     * using the same core pipeline (wp_handle_upload() + wp_insert_attachment()
     * + wp_generate_attachment_metadata()) that wp/v2/media relies on. There's
     * no gallery grouping concept here, so $batch_id is unused — every image
     * becomes its own attachment regardless of which export it came from.
     *
     * @param array  $file     A single entry from WP_REST_Request::get_file_params(), e.g. ['tmp_name' => ..., 'name' => ...].
     * @param array  $metadata Raw request params, keyed by the field ids from get_plugin_metadata().
     * @param string $batch_id Client-supplied X-Darkup-Batch header value, or '' if none was sent. Unused by this adapter.
     * @return bool|WP_Error
     */
    public static function upload_image($file, array $metadata, string $batch_id = ''): bool|\WP_Error
    {
        //map the metadata, keyed by field id
        $fields_meta = self::get_plugin_metadata()['meta'] ?? false;
        if (!$fields_meta) {
            //This should never happen...
            return new WP_Error('no_meta_found', esc_html(__('Failed to load the meta fields', 'darkuploader')));
        }
        $values = [];
        foreach ($fields_meta as $field) {
            $raw = $metadata[$field['id']] ?? ($field['default'] ?? '');
            $values[$field['id']] = sanitize_text_field((string) $raw);
        }

        $attachment_id = self::create_attachment($file, $values);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        //Log the event. add_log() captures $_POST and the allowlisted request
        //headers (see LOGGED_HEADERS in logging.php) into postmeta on its own.
        \DarkUploaderLogging\add_log(
            /* translators: %s: title of the uploaded image */
            sprintf(esc_html__('Image %s uploaded', 'darkuploader'), get_the_title($attachment_id)),
            self::get_plugin_metadata()['slug'] ?? 'undefined',
            null,
            $attachment_id
        );

        \DarkUploaderLogging\update_statistic(self::get_plugin_metadata()['slug'] ?? 'undefined');


        return true;
    }

    /**
     * Uploads a file into the Media Library as a standalone attachment, using the same
     * core pipeline (wp_handle_upload() + wp_insert_attachment() + wp_generate_attachment_metadata())
     * that wp/v2/media relies on. Shared with other adapters (e.g. Meow Gallery) that need
     * a plain attachment created before linking it into their own gallery storage.
     *
     * @param array $file   A single entry from WP_REST_Request::get_file_params(), e.g. ['tmp_name' => ..., 'name' => ...].
     * @param array $values Sanitized values, optionally keyed by 'title', 'description', 'caption', 'alt_text'.
     * @return int|WP_Error The new attachment's ID.
     */
    public static function create_attachment(array $file, array $values): int|WP_Error
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', esc_html(__('Invalid uploaded file', 'darkuploader')));
        }

        // wp_handle_upload()/wp_generate_attachment_metadata() live in wp-admin and
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // test_form is only meaningful for classic wp-admin upload forms that post an
        // 'action' field alongside the file; skip that check for a REST-originated upload.
        // Avoids a Invalid form submission error
        $upload = wp_handle_upload($file, ['test_form' => false]);
        if (isset($upload['error'])) {
            return new WP_Error('wp_upload_error', esc_html($upload['error']));
        }

        $title = ($values['title'] ?? '') !== '' ? $values['title'] : preg_replace('/\.[^.]+$/', '', basename($upload['file']));

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $upload['type'],
            'post_title' => $title,
            'post_content' => $values['description'] ?? '',
            'post_excerpt' => $values['caption'] ?? '',
            'post_status' => 'inherit',
        ], $upload['file'], 0, true);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Mirrors what wp/v2/media does after inserting the attachment: generate the
        // intermediate image sizes and store the resulting metadata on the attachment.
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        if (!empty($values['alt_text'])) {
            // Matches the alt_text field wp/v2/media exposes; alt text has no post column
            // of its own and is always stored as attachment postmeta.
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $values['alt_text']);
        }

        return $attachment_id;
    }
}
