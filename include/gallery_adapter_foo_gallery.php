<?php

namespace DarkUploaderAdapter;

if (! defined('ABSPATH')) exit;

use DarkUploaderAdapter\DarkUploader_Gallery_Adapter;
use WP_Error;

/**
 * Adapter that routes uploads into FooGallery.
 *
 * It uses the media library adapter to upload the pictures and adds them tho the FooGallery database
 */
class DarkUploader_FooGallery_Adapter implements DarkUploader_Gallery_Adapter
{
    use DarkUploader_Gallery_Adapter_Batch;

    /**
     * Registers the adapter
     *
     * @return void
     */
    public static function register()
    {
        \add_filter('darkuploader_supported_galleries', function ($galleries) {
            $galleries['foo-gallery'] = [
                'slug' => 'foogallery/foogallery.php',
                'adapter' => self::class,
            ];
            return $galleries;
        });
    }

    /**
     * Describes this adapter and the upload-form fields it accepts.
     *
     * @return array
     */
    public static function get_plugin_metadata(): array
    {
        //Basic info
        $info = [
            'slug' => 'foo-gallery',
            'name' => 'FooGallery',
            'meta' => []
        ];
        $mode_selector = [
            [
                'value' => 'create',
                'label' => __('Create gallery', 'darkuploader'),
            ],
            [
                'value' => 'add',
                'label' => __('Add to gallery', 'darkuploader'),
            ],
        ];
        $meta = [
            [
                'id' => 'mode_selector',
                'label' => __('Mode', 'darkuploader'),
                'type' => 'select',
                'options' => $mode_selector,
                'required' => true,
            ],
            [
                'id' => 'gallery_name',
                'label' => __('Gallery Name', 'darkuploader'),
                'type' => 'text',
                'required' => true,
                'hint' => __('Enter the name of the gallery', 'darkuploader'),
                'placeholder' => '$(JOBNAME)',
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'create',
                ]
            ],
            [
                'id' => 'gallery_id',
                'label' => __('Gallery ID', 'darkuploader'),
                'type' => 'text',
                'required' => true,
                'hint' => __('Enter the ID of an existing gallery', 'darkuploader'),
                'placeholder' => '',
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'add',
                ]
            ],
            [
                'id' => 'layout',
                'label' => __('Layout', 'darkuploader'),
                'type' => 'select',
                'options' => self::get_layout_options(),
                'required' => false,
                'hint' => __('Choose the layout for the gallery', 'darkuploader'),
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'create',
                ]
            ],
            [
                'id' => 'order_by',
                'label' => __('Order by', 'darkuploader'),
                'type' => 'select',
                'options' => self::get_sorting_options(),
                'required' => false,
                'hint' => __('Choose the sorting for the gallery', 'darkuploader'),
                'default' => '',
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'create',
                ]
            ],
            [
                'id' => 'title',
                'label' => __('Title', 'darkuploader'),
                'type' => 'text',
                'required' => false,
                'hint' => __('Enter the title for the image', 'darkuploader'),
                'placeholder' => '$(Xmp.dc.title)',
            ],
            [
                'id' => 'alt_text',
                'label' => __('Alt text', 'darkuploader'),
                'type' => 'text',
                'required' => false,
                'hint' => __('Enter the alt text for the image', 'darkuploader'),
                'placeholder' => '$(Xmp.dc.title)',
            ],
            [
                'id' => 'description',
                'label' => __('Description', 'darkuploader'),
                'type' => 'text',
                'required' => false,
                'hint' => __('Write a description for the image', 'darkuploader'),
                'placeholder' => '$(Xmp.dc.description)',
            ],
            [
                'id' => 'caption',
                'label' => __('Caption', 'darkuploader'),
                'type' => 'text',
                'required' => false,
                'hint' => __('Add the caption for the image', 'darkuploader'),
                'placeholder' => '$(Xmp.dc.subject)',
            ],
        ];
        $info['meta'] = $meta;

        return $info;
    }

    /**
     * Lists the layout builders the 'layout' form field can offer, in the
     * ['value' => ..., 'label' => ...] shape every other select field here uses.
     *
     * @return array
     */
    private static function get_layout_options(): array
    {
        if (!function_exists('foogallery_gallery_templates')) {
            return [['value' => 'default', 'label' => __('Responsive', 'darkuploader')]];
        }
        //Contains arrays [template_id] => ['slug' =>, 'name' => ]
        $all_templates = \foogallery_gallery_templates();
        return array_map(function ($key, $template) {
            $label = $template['name'] ?? $key;
            return ['value' => $key, 'label' => $label];
        }, array_keys($all_templates), $all_templates);
    }

    /**
     * Lists the order_by values the 'order_by' form field can offer, in the
     * ['value' => ..., 'label' => ...] shape every other select field here uses.
     * 
     * @return array
     */
    private static function get_sorting_options(): array
    {
        if (!function_exists('foogallery_sorting_options')) {
            return [['value' => '', 'label' => __('Default', 'darkuploader')]];
        }

        $sort = \foogallery_sorting_options();
        return array_map(function ($value, $label) {
            return ['value' => $value, 'label' => $label];
        }, array_keys($sort), $sort);
    }

    /**
     * Uploads the file as a Media Library attachment, then links it into the
     * target FooGallery (creating or looking one up).
     *
     * $batch_id (the client's X-Darkup-Batch header) lets a gallery created for
     * the first image of a multi-image export be reused by the rest of that
     * same export.
     *
     * @param array  $file     A single entry from WP_REST_Request::get_file_params(), e.g. ['tmp_name' => ..., 'name' => ...].
     * @param array  $metadata Raw request params, keyed by the field ids from get_plugin_metadata().
     * @param string $batch_id Client-supplied X-Darkup-Batch header value, or '' if none was sent.
     * @return bool|WP_Error
     */
    public static function upload_image($file, array $metadata, string $batch_id = ''): bool|\WP_Error
    {
        if (!function_exists('foogallery_insert_gallery')) {
            return new WP_Error('no_foo', __('FooGallery is not active', 'darkuploader'));
        }

        //map the metadata, keyed by field id (not the numeric list index)
        $fields_meta = self::get_plugin_metadata()['meta'] ?? false;
        if (!$fields_meta) {
            //This should never happen...
            return new WP_Error('no_meta_found', __('Failed to load the meta fields', 'darkuploader'));
        }
        $values = [];
        foreach ($fields_meta as $field) {
            $raw = $metadata[$field['id']] ?? ($field['default'] ?? '');
            $values[$field['id']] = sanitize_text_field((string) $raw);
        }

        $mode = $values['mode_selector'] ?? '';
        $layout = $values['layout'] ?? '';
        $order_by = $values['order_by'] ?? '';

        $batch_key = self::get_batch_transient_key($batch_id);

        //Check if there is a gallery that got set or created within that batch. If so
        //it will override the create mode to add mode.
        $gallery_id_from_batch = false;
        if ($batch_key) {
            $batch_state = get_transient($batch_key);
            $gallery_id_from_batch = $batch_state['gallery_id'] ?? false;
        }
        if (!empty($gallery_id_from_batch)) {
            $mode = 'add';
            $values['gallery_id'] = $gallery_id_from_batch;
        }

        // Library attachment that a gallery row merely references by ID.
        if (!in_array($mode, ['create', 'add'], true)) {
            return new WP_Error('no_mode_found', __('Mode not found or not supported', 'darkuploader'));
        }

        // Checked before the attachment is created, so a refused upload leaves nothing behind.
        // null checks the permission to create a new gallery.
        $permission_gallery_id = null;
        if ($mode === 'add') {
            $permission_gallery_id = (string) ($values['gallery_id'] ?? '');
        }
        $allowed = self::gallery_permissions($permission_gallery_id);
        if (is_wp_error($allowed)) {
            return $allowed;
        }

        $attachment_id = DarkUploader_WP_Library_Adapter::create_attachment($file, $values);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $gallery_id = 0;
        switch ($mode) {
            case 'create':
                $gallery_id = self::create_gallery($values['gallery_name'] ?? '', $attachment_id, $layout, $order_by);
                if (is_wp_error($gallery_id)) {
                    // Don't leave an orphaned attachment behind in the Media Library.
                    wp_delete_attachment($attachment_id, true);
                    return $gallery_id;
                }
                if ($batch_key) {
                    set_transient($batch_key, ['gallery_id' => $gallery_id], HOUR_IN_SECONDS);
                }
                break;
            case 'add':
                $gallery_id = sanitize_text_field((string) ($values['gallery_id'] ?? ''));
                $added = self::add_image_to_gallery($gallery_id, $attachment_id);
                if (is_wp_error($added)) {
                    wp_delete_attachment($attachment_id, true);
                    return $added;
                }
                break;
        }

        //Add the post to the attachment
        wp_update_post([
            'ID' => $attachment_id,
            'post_parent' => (int) $gallery_id,
        ]);

        //Log the event
        $slug = self::get_plugin_metadata()['slug'] ?? 'undefined';
        /* translators: %s: title or filename of the uploaded image */
        $message = sprintf(__('Image %s uploaded', 'darkuploader'), get_the_title($attachment_id));
        \DarkUploaderLogging\add_log($message, $slug, null, $attachment_id);
        \DarkUploaderLogging\update_statistic($slug);

        return true;
    }

    /**
     * Checks FooGallery's own capabilities, the same way its abilities do:
     * creating needs create_foogalleries and publish_foogalleries (galleries are
     * created published), adding needs edit_post on that gallery.
     *
     * @param string|null $gallery_id The gallery to add images to, or null to create a new gallery.
     * @return true|WP_Error
     */
    public static function gallery_permissions(?string $gallery_id = null): bool|WP_Error
    {
        if ($gallery_id === null) {
            if (!current_user_can('create_foogalleries') || !current_user_can('publish_foogalleries')) {
                return new WP_Error('darkup_forbidden', __('You are not allowed to create FooGallery galleries.', 'darkuploader'), ['status' => 403]);
            }
            return true;
        }

        // FOOGALLERY_CPT_GALLERY is only defined while FooGallery is active.
        $gallery_post_type = defined('FOOGALLERY_CPT_GALLERY') ? constant('FOOGALLERY_CPT_GALLERY') : 'foogallery';
        $gallery_id = absint($gallery_id);
        if (!$gallery_id || get_post_type($gallery_id) !== $gallery_post_type) {
            return new WP_Error('gallery_not_found', __('Gallery not found', 'darkuploader'));
        }
        if (!current_user_can('edit_post', $gallery_id)) {
            return new WP_Error('darkup_forbidden', __('You are not allowed to add images to this gallery.', 'darkuploader'), ['status' => 403]);
        }
        return true;
    }

    /**
     * Creates a new FooGallery post, seeded with a single image.
     *
     * @param string    $gallery_name
     * @param int       $attachment_id The Media Library attachment to seed the gallery with.
     * @param string    $layout The layout to use
     * @param string    $order_by The sorting for the gallery items
     * @return int|WP_Error The new gallery's id.
     */
    public static function create_gallery(string $gallery_name, int $attachment_id, string $layout, string $order_by): int|WP_Error
    {
        if (empty($gallery_name)) {
            return new WP_Error('no_gallery_name_given', __('No gallery name given', 'darkuploader'));
        }
        if (!function_exists('foogallery_insert_gallery')) {
            return new WP_Error('no_foo', __('FooGallery is not active', 'darkuploader'));
        }

        $layout = empty($layout) ? self::get_default_layout() : $layout;

        $gallery_id = \foogallery_insert_gallery(
            [
                'title' => $gallery_name,
                'status' => 'publish',
                'template' => $layout,
                'sort' => $order_by,
                'attachment_ids' => $attachment_id,

            ]
        );

        if (is_wp_error($gallery_id)) {
            return $gallery_id;
        }

        return (int) $gallery_id;
    }


    /**
     * Reads FooGallery's default layout option
     *
     * @return string
     */
    private static function get_default_layout(): string
    {
        if (!function_exists('foogallery_get_default')) {
            return 'default';
        }
        return \foogallery_get_default('gallery_template');
    }

    /**
     * Adding images to an existing gallery
     *
     * @param string $gallery_id
     * @param int    $attachment_id
     * @return int|WP_Error The first attachment ID of the gallery, or FooGallery's error
     *                      (e.g. when the gallery doesn't exist).
     */
    public static function add_image_to_gallery(string $gallery_id, int $attachment_id): int|WP_Error
    {
        if (empty($gallery_id)) {
            return new WP_Error('no_gallery_id_given', __('No gallery ID given', 'darkuploader'));
        }
        if (!function_exists('foogallery_add_gallery_attachments')) {
            return new WP_Error('no_foo', __('FooGallery is not active', 'darkuploader'));
        }

        $attachment_ids = \foogallery_add_gallery_attachments($gallery_id, [$attachment_id]);
        if (is_wp_error($attachment_ids)) {
            return $attachment_ids;
        }

        return is_array($attachment_ids) ? (int) reset($attachment_ids) : (int) $attachment_ids;
    }
}
