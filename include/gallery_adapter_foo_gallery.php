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
                'label' => esc_html(__('Create gallery', 'darkuploader')),
            ],
            [
                'value' => 'add',
                'label' => esc_html(__('Add to gallery', 'darkuploader')),
            ],
        ];
        $meta = [
            [
                'id' => 'mode_selector',
                'label' => esc_html(__('Mode', 'darkuploader')),
                'type' => 'select',
                'options' => $mode_selector,
                'required' => true,
            ],
            [
                'id' => 'gallery_name',
                'label' => esc_html(__('Gallery Name', 'darkuploader')),
                'type' => 'text',
                'required' => true,
                'hint' => esc_html(__('Enter the name of the gallery', 'darkuploader')),
                'placeholder' => '$(JOBNAME)',
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'create',
                ]
            ],
            [
                'id' => 'gallery_id',
                'label' => esc_html(__('Gallery ID', 'darkuploader')),
                'type' => 'text',
                'required' => true,
                'hint' => esc_html(__('Enter the ID of an existing gallery', 'darkuploader')),
                'placeholder' => '',
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'add',
                ]
            ],
            [
                'id' => 'layout',
                'label' => esc_html(__('Layout', 'darkuploader')),
                'type' => 'select',
                'options' => self::get_layout_options(),
                'required' => false,
                'hint' => esc_html(__('Choose the layout for the gallery', 'darkuploader')),
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'create',
                ]
            ],
            [
                'id' => 'order_by',
                'label' => esc_html(__('Order by', 'darkuploader')),
                'type' => 'select',
                'options' => self::get_sorting_options(),
                'required' => false,
                'hint' => esc_html(__('Choose the sorting for the gallery', 'darkuploader')),
                'default' => 'none',
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'create',
                ]
            ],
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
     * Lists the layout builders the 'layout' form field can offer, in the
     * ['value' => ..., 'label' => ...] shape every other select field here uses.
     *
     * @return array
     */
    private static function get_layout_options(): array
    {
        if (!function_exists('foogallery_gallery_templates')) {
            return [['value' => 'default', 'label' => __('Responsive', 'foogallery')]];
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
            return [['value' => '', 'label' => __('Default', 'foogallery')]];
        }

        $sort = \foogallery_sorting_options();
        return array_map(function ($value, $label) {
            return ['value' => $value, 'label' => $label];
        }, array_keys($sort), $sort);
    }

    /**
     * Uploads the file as a Media Library attachment, then links it into the
     * target Meow Gallery (creating or looking one up).
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
            return new WP_Error('no_foo', esc_html(__('FooGallery is not active', 'darkuploader')));
        }

        //map the metadata, keyed by field id (not the numeric list index)
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

        $mode = $values['mode_selector'] ?? '';
        $layout = $values['layout'] ?? '';
        $order_by = $values['order_by'] ?? '';

        $batch_key = self::get_batch_transient_key($batch_id);

        //Check if there is a gallery that got set or created within that batch. If so
        //it will override the create mode to add mode.
        $gallery_id_from_batch = $batch_key ? (get_transient($batch_key)['gallery_id'] ?? false) : false;
        if (!empty($gallery_id_from_batch)) {
            $mode = 'add';
            $values['gallery_id'] = $gallery_id_from_batch;
        }

        // Library attachment that a gallery row merely references by ID.
        $attachment_id = DarkUploader_WP_Library_Adapter::create_attachment($file, $values);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        switch ($mode) {
            case 'create':
                $gallery_id = self::create_gallery($values['gallery_name'] ?? '', $attachment_id, $layout, $order_by);
                if (is_wp_error($gallery_id)) {
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
                    return $added;
                }
                break;

            default:
                return new WP_Error('no_mode_found', esc_html(__('Mode not found or not supported', 'darkuploader')));
        }

        //Log the event
        \DarkUploaderLogging\add_log(sprintf(esc_html__('Image %s uploaded', 'darkuploader'), get_the_title($attachment_id)), self::get_plugin_metadata()['slug'] ?? 'undefined', null, $attachment_id);
        \DarkUploaderLogging\update_statistic(self::get_plugin_metadata()['slug'] ?? 'undefined');

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
            return new WP_Error('no_gallery_name_given', esc_html(__('No gallery name given', 'darkuploader')));
        }
        if (!function_exists('foogallery_insert_gallery')) {
            return new WP_Error('no_foo', esc_html(__('FooGallery is not active', 'darkuploader')));
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

        return $gallery_id; //Can be a gallery ID or a WP_Error
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
     * @return array|WP_Error
     */
    public static function add_image_to_gallery(string $gallery_id, int $attachment_id): array|WP_Error
    {
        if (empty($gallery_id)) {
            return new WP_Error('no_gallery_id_given', esc_html(__('No gallery ID given', 'darkuploader')));
        }
        if (!function_exists('foogallery_add_gallery_attachments')) {
            return new WP_Error('no_foo', esc_html(__('FooGallery is not active', 'darkuploader')));
        }

        $gallery_ids = \foogallery_add_gallery_attachments($gallery_id, [$attachment_id]);


        return $gallery_ids;
    }
}
