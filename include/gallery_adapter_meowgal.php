<?php

namespace DarkUploaderAdapter;

if (! defined('ABSPATH')) exit;

use DarkUploaderAdapter\DarkUploader_Gallery_Adapter;
use WP_Error;

/**
 * Adapter that routes uploads into Meow Gallery.
 *
 * It uses the media library adapter to upload the pictures and adds them tho the Meow database
 */
class DarkUploader_MeowGallery_Adapter implements DarkUploader_Gallery_Adapter
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
            $galleries['meow-gallery'] = [
                'slug' => 'meow-gallery/meow-gallery.php',
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
            'slug' => 'meow-gallery',
            'name' => 'Meow Gallery',
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
        $layouts = [
            'tiles' => esc_html(__('Tiles', 'darkuploader')),
            'masonry' => esc_html(__('Masonry', 'darkuploader')),
            'justified' => esc_html(__('Justified', 'darkuploader')),
            'square' => esc_html(__('Square', 'darkuploader')),
            'cascade' => esc_html(__('Cascade', 'darkuploader')),
            'horizontal' => esc_html(__('Horizontal', 'darkuploader')),
        ];
        return array_map(function ($value, $label) {
            return ['value' => $value, 'label' => $label];
        }, array_keys($layouts), $layouts);
    }

    /**
     * Lists the order_by values the 'order_by' form field can offer, in the
     * ['value' => ..., 'label' => ...] shape every other select field here uses.
     * Values match what Meow Gallery's own admin UI writes to that column (see
     * app/admin.js) and what Meow_MGL_OrderBy::run() knows how to interpret.
     *
     * @return array
     */
    private static function get_sorting_options(): array
    {
        // Matches the order_by values Meow Gallery's own admin UI uses (app/admin.js).
        $sort = [
            'none' => esc_html(__('None', 'darkuploader')),
            'random' => esc_html(__('Random', 'darkuploader')),
            'ids-asc' => esc_html(__('IDs Ascending', 'darkuploader')),
            'ids-desc' => esc_html(__('IDs Descending', 'darkuploader')),
            'title-asc' => esc_html(__('Title (Filename) Ascending', 'darkuploader')),
            'title-desc' => esc_html(__('Title (Filename) Descending', 'darkuploader')),
            'date-asc' => esc_html(__('Date Ascending', 'darkuploader')),
            'date-desc' => esc_html(__('Date Descending', 'darkuploader')),
            'modified-asc' => esc_html(__('Updated Date Ascending', 'darkuploader')),
            'modified-desc' => esc_html(__('Updated Date Descending', 'darkuploader')),
            'menu-asc' => esc_html(__('Menu Order Ascending', 'darkuploader')),
            'menu-desc' => esc_html(__('Menu Order Descending', 'darkuploader')),
        ];
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
        if (!class_exists('\Meow_MGL_Core')) {
            return new WP_Error('no_mgl', esc_html(__('Meow Gallery is not active', 'darkuploader')));
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

        // Meow Gallery has no storage of its own — every image is a plain Media
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
        /* translators: %s: title of the uploaded image */
        \DarkUploaderLogging\add_log(sprintf(esc_html__('Image %s uploaded', 'darkuploader'), get_the_title($attachment_id)), self::get_plugin_metadata()['slug'] ?? 'undefined', null, $attachment_id);
        \DarkUploaderLogging\update_statistic(self::get_plugin_metadata()['slug'] ?? 'undefined');

        return true;
    }

    /**
     * Creates a new Meow Gallery shortcode row, seeded with a single image.
     *
     * @param string    $gallery_name
     * @param int       $attachment_id The Media Library attachment to seed the gallery with.
     * @param string    $layout The layout to use
     * @param string    $order_by The sorting for the gallery items
     * @return string|WP_Error The new gallery's id.
     */
    public static function create_gallery(string $gallery_name, int $attachment_id, string $layout, string $order_by): string|WP_Error
    {
        if (empty($gallery_name)) {
            return new WP_Error('no_gallery_name_given', esc_html(__('No gallery name given', 'darkuploader')));
        }
        if (!class_exists('\Meow_MGL_Migrations')) {
            return new WP_Error('no_mgl', esc_html(__('Meow Gallery is not active', 'darkuploader')));
        }

        global $wpdb;
        \Meow_MGL_Migrations::check_db();
        $shortcodes_table = $wpdb->prefix . 'mgl_gallery_shortcodes';

        if (empty($layout)) {
            $layout = self::get_default_layout();
        }

        if (empty($order_by)) {
            $order_by = 'none';
        }

        $entry = self::build_thumbnail_entry($attachment_id);

        $gallery_id = self::generate_gallery_id();
        $inserted = $wpdb->insert($shortcodes_table, [
            'id' => $gallery_id,
            'name' => $gallery_name,
            'layout' => $layout,
            'order_by' => $order_by,
            'medias' => serialize([
                'thumbnail_ids' => [$entry['id']],
                'thumbnail_urls' => [$entry['url']],
                'thumbnails' => [$entry],
            ]),
            'is_post_mode' => 0,
            'is_hero_mode' => 0,
            'pref_rank' => 0,
        ]);

        if (!$inserted) {
            return new WP_Error('mgl_add_gal_error', esc_html(__('Gallery could not get created', 'darkuploader')));
        }
        return $gallery_id;
    }

    /**
     * Creates the thumbnail entries for the meow database
     * 
     * @param int $attachment_id
     * @return array{id: string, url: string, zoom_url: string, mime: string}
     */
    private static function build_thumbnail_entry(int $attachment_id): array
    {
        $fallback_url = (string) wp_get_attachment_url($attachment_id);
        return [
            'id' => (string) $attachment_id,
            'url' => wp_get_attachment_image_url($attachment_id, 'thumbnail') ?: $fallback_url,
            'zoom_url' => wp_get_attachment_image_url($attachment_id, 'large') ?: $fallback_url,
            'mime' => (string) get_post_mime_type($attachment_id),
        ];
    }

    /**
     * Reads Meow Gallery's own site-wide default layout setting (the 'layout' key
     * in its mgl_options, see Meow_MGL_Core::list_options()) the same way its own
     * shortcode-rendering code falls back for a gallery with no explicit layout
     * (Meow_MGL_Core::gallery(), core.php:412) — falling back to 'tiles' (that
     * option's own registered default) if the plugin's live instance isn't reachable.
     *
     * @return string
     */
    private static function get_default_layout(): string
    {
        global $wpmgl;
        if ($wpmgl instanceof \Meow_MGL_Core) {
            return (string) $wpmgl->get_option('layout', 'tiles');
        }
        return 'tiles';
    }

    /**
     * Links an already-uploaded attachment into an existing Meow Gallery, by
     * appending it to that gallery's `medias.thumbnail_ids`/`thumbnail_urls`/`thumbnails`
     * arrays (kept parallel, matching the shape Meow's own admin UI writes — see
     * build_thumbnail_entry()). Only the `medias` column is touched, so the
     * gallery's other settings (layout, tags, description, ...) are left untouched.
     *
     * @param string $gallery_id
     * @param int    $attachment_id
     * @return true|WP_Error
     */
    public static function add_image_to_gallery(string $gallery_id, int $attachment_id): bool|WP_Error
    {
        if (empty($gallery_id)) {
            return new WP_Error('no_gallery_id_given', esc_html(__('No gallery ID given', 'darkuploader')));
        }
        if (!class_exists('\Meow_MGL_Migrations')) {
            return new WP_Error('no_mgl', esc_html(__('Meow Gallery is not active', 'darkuploader')));
        }

        global $wpdb;
        \Meow_MGL_Migrations::check_db();
        $shortcodes_table = $wpdb->prefix . 'mgl_gallery_shortcodes';


        $row = $wpdb->get_row($wpdb->prepare("SELECT medias FROM $shortcodes_table WHERE id = %s", $gallery_id), ARRAY_A);
        if ($row === null) {
            return new WP_Error('gallery_not_found', esc_html(__('Gallery not found', 'darkuploader')));
        }

        $medias = maybe_unserialize($row['medias']);
        if (!is_array($medias)) {
            $medias = [];
        }
        foreach (['thumbnail_ids', 'thumbnail_urls', 'thumbnails'] as $key) {
            if (empty($medias[$key]) || !is_array($medias[$key])) {
                $medias[$key] = [];
            }
        }

        $entry = self::build_thumbnail_entry($attachment_id);
        $medias['thumbnail_ids'][] = $entry['id'];
        $medias['thumbnail_urls'][] = $entry['url'];
        $medias['thumbnails'][] = $entry;

        $updated = $wpdb->update(
            $shortcodes_table,
            ['medias' => serialize($medias)],
            ['id' => $gallery_id]
        );

        if ($updated === false) {
            return new WP_Error('mgl_add_image_error', esc_html(__('Failed to add the image to the gallery', 'darkuploader')));
        }
        return true;
    }

    /**
     * Generates a gallery id in the same shape as Meow_MGL_Core::generate_uniqid(),
     * without needing a live instance of that class (its constructor has side
     * effects like registering hooks and enqueuing admin assets that this adapter
     * has no business triggering).
     *
     * @return string
     */
    private static function generate_gallery_id(): string
    {
        return substr(wp_unique_id(uniqid()), 0, 20);
    }
}
