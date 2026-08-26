<?php

namespace DarkWPAdapter;

use DarkWPAdapter\DarkWP_Gallery_Adapter;
use WP_Error;

if (! defined('ABSPATH')) exit;

/**
 * Adapter that routes uploads into NextGEN Gallery via its own DataMapper/DataStorage API.
 */
class DarkWP_NextGen_Adapter implements DarkWP_Gallery_Adapter
{
    use DarkWP_Gallery_Adapter_Batch;


    /**
     * Describes this adapter and the upload-form fields it accepts.
     *
     * @return array
     */
    public static function get_plugin_metadata(): array
    {
        //Basic info
        $info = [
            'slug' => 'nextgen-gallery',
            'name' => 'NextGen Gallery',
            'meta' => []
        ];
        $mode_selector = [
            [
                'value' => 'create',
                'label' => esc_html(__('Create gallery', 'darkwp')),
            ],
            [
                'value' => 'add',
                'label' => esc_html(__('Add to gallery', 'darkwp')),
            ],
        ];
        $meta = [
            [
                'id' => 'mode_selector',
                'label' => esc_html(__('Mode', 'darkwp')),
                'type' => 'select',
                'options' => $mode_selector,
                'required' => true,
            ],
            [
                'id' => 'gallery_name',
                'label' => esc_html(__('Gallery Name', 'darkwp')),
                'type' => 'text',
                'required' => true,
                'hint' => esc_html(__('Enter the name of the gallery', 'darkwp')),
                'placeholder' => '$(JOBNAME)',
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'create',
                ]
            ],
            [
                'id' => 'gallery_id',
                'label' => esc_html(__('Gallery ID', 'darkwp')),
                'type' => 'text',
                'required' => true,
                'hint' => esc_html(__('Enter the ID of an existing gallery', 'darkwp')),
                'placeholder' => '0',
                'show_when' => [
                    'field' => 'mode_selector',
                    'compare' => '=',
                    'value' => 'add',
                ]
            ],
            [
                'id' => 'alt_text',
                'label' => esc_html(__('Alt text', 'darkwp')),
                'type' => 'text',
                'required' => false,
                'hint' => esc_html(__('Enter the alt text for the image', 'darkwp')),
                'placeholder' => '$(Xmp.dc.title)',
            ],
            [
                'id' => 'published',
                'label' => esc_html(__('Publish photos', 'darkwp')),
                'type' => 'checkbox',
                'required' => false,
                'hint' => esc_html(__('If checked, the photos will be marked as published', 'darkwp')),
                'default' => true,
            ],
            [
                'id' => 'description',
                'label' => esc_html(__('Description', 'darkwp')),
                'type' => 'text',
                'required' => false,
                'hint' => esc_html(__('Write a description for the image', 'darkwp')),
                'placeholder' => '$(Xmp.dc.description)',
            ],
            [
                'id' => 'tags',
                'label' => esc_html(__('Tags (comma-separated)', 'darkwp')),
                'type' => 'text',
                'required' => false,
                'hint' => esc_html(__('Add the tags for the image, comma-separated', 'darkwp')),
                'placeholder' => '$(Xmp.dc.subject)',
            ],
        ];
        $info['meta'] = $meta;

        return $info;
    }

    /**
     * Resolves the target gallery (creating or looking one up) and uploads the file into it.
     *
     * $batch_id (the client's X-Darkwp-Batch header) lets a gallery created for the first
     * image of a multi-image export be reused by the rest of that same export.
     *
     * @param array  $file     A single entry from WP_REST_Request::get_file_params(), e.g. ['tmp_name' => ..., 'name' => ...].
     * @param array  $metadata Raw request params, keyed by the field ids from get_plugin_metadata().
     * @param string $batch_id Client-supplied X-Darkwp-Batch header value, or '' if none was sent.
     * @return bool|WP_Error
     */
    public static function upload_image($file, array $metadata, string $batch_id = ''): bool|\WP_Error
    {
        if (!class_exists('\Imagely\NGG\DataStorage\Manager')) {
            return new WP_Error('no_ngg', esc_html(__('NextGen Gallery is not active', 'darkwp')));
        }

        //map the metadata, keyed by field id (not the numeric list index)
        $fields_meta = self::get_plugin_metadata()['meta'] ?? false;
        if (!$fields_meta) {
            //This should never happen...
            return new WP_Error('no_meta_found', esc_html(__('Failed to load the meta fields', 'darkwp')));
        }
        $values = [];
        foreach ($fields_meta as $field) {
            $raw = $metadata[$field['id']] ?? ($field['default'] ?? '');
            // Checkboxes need real boolean coercion — sanitize_text_field() would leave a
            // literal "false" string, which is non-empty and therefore truthy in PHP.
            $values[$field['id']] = ($field['type'] === 'checkbox')
                ? filter_var($raw, FILTER_VALIDATE_BOOLEAN)
                : sanitize_text_field((string) $raw);
        }

        $mode = $values['mode_selector'] ?? '';

        // Batch state is scoped to this specific batch id (and the current user), not a
        // single shared transient key — otherwise concurrent uploads from different
        // batches/users would stomp on each other's gallery id.
        $batch_key = self::get_batch_transient_key($batch_id);

        //Check if there is a gallery that got set or created within that batch. If so
        //it will override the create mode to add mode.
        $gallery_id_from_batch = $batch_key ? (get_transient($batch_key)['gallery_id'] ?? false) : false;
        if ($gallery_id_from_batch > 0) {
            $mode = 'add';
            $values['gallery_id'] = $gallery_id_from_batch;
        }

        switch ($mode) {
            case 'create':
                $gallery_id = self::create_gallery($values['gallery_name'] ?? '');
                if (is_wp_error($gallery_id)) {
                    return $gallery_id;
                }
                if ($batch_key) {
                    set_transient($batch_key, ['gallery_id' => $gallery_id], HOUR_IN_SECONDS);
                }
                break;
            case 'add':
                $gallery_id = absint($values['gallery_id'] ?? 0);
                if (!$gallery_id || !\Imagely\NGG\DataMappers\Gallery::get_instance()->find($gallery_id)) {
                    return new WP_Error('gallery_not_found', esc_html(__('Gallery not found', 'darkwp')));
                }
                break;

            default:
                return new WP_Error('no_mode_found', esc_html(__('Mode not found or not supported', 'darkwp')));
        }

        $image_id = self::add_image_to_gallery($gallery_id, $file, $values);
        if (is_wp_error($image_id)) {
            return $image_id;
        }
        return true;
    }

    /**
     * Creates a new NextGEN gallery.
     *
     * @param string $gallery_name
     * @param string $description
     * @return int|WP_Error The new gallery's ID.
     */
    public static function create_gallery(string $gallery_name, string $description = ""): int|WP_Error
    {
        if (empty($gallery_name)) {
            return new WP_Error('no_gallery_name_given', esc_html(__('No gallery name given', 'darkwp')));
        }
        if (!class_exists('\Imagely\NGG\DataMappers\Gallery')) {
            return new WP_Error('no_ngg', esc_html(__('NextGen Gallery is not active', 'darkwp')));
        }

        $gallery_mapper = \Imagely\NGG\DataMappers\Gallery::get_instance();
        $gallery = $gallery_mapper->create(['title' => $gallery_name, 'description' => $description]);
        if (!$gallery->save()) {
            return new WP_Error('ngg_add_gal_error', esc_html(__('Gallery could not get created', 'darkwp')));
        }
        return $gallery->id();
    }

    /**
     * Imports an uploaded file into a NextGEN gallery and applies alt text/description/tags/published state.
     *
     * @param int   $gallery_id
     * @param array $file   A single entry from WP_REST_Request::get_file_params().
     * @param array $values Sanitized metadata values, keyed by field id.
     * @return int|WP_Error The new (or updated) image's ID.
     */
    public static function add_image_to_gallery(int $gallery_id, array $file, array $values): int|WP_Error
    {
        if (empty($gallery_id)) {
            return new WP_Error('no_gallery_id_given', esc_html(__('No gallery ID given', 'darkwp')));
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', esc_html(__('Invalid uploaded file', 'darkwp')));
        }
        if (!class_exists('\Imagely\NGG\DataStorage\Manager')) {
            return new WP_Error('no_ngg', esc_html(__('NextGen Gallery is not active', 'darkwp')));
        }

        $storage = \Imagely\NGG\DataStorage\Manager::get_instance();
        $image_mapper = \Imagely\NGG\DataMappers\Image::get_instance();

        // ngg_pictures has a UNIQUE (galleryid, filename) key. import_image_file() always
        // inserts a new row unless we hand it the existing entity to update instead — otherwise
        // re-uploading a same-named file hits that constraint, NGG doesn't surface it as a
        // validation error, and it ends up operating on a null $image
        $filename = $storage->sanitize_filename_for_db($file['name']);
        // find_all()'s where-clause parser only understands one "column operator value" per
        // condition entry — a single "A AND B" string gets mis-parsed (both '=' signs get
        // stripped, leaving the whole tail as one garbled value), silently matching every row
        // in the gallery. Pass galleryid/filename as separate entries so they're ANDed correctly.
        $existing = $image_mapper->find_all([
            ['galleryid = %d', $gallery_id],
            ['filename = %s', $filename],
        ]);
        $existing_image = !empty($existing) ? reset($existing) : false;

        try {
            // import_image_file() moves the tmp upload into the gallery's storage,
            // extracts EXIF/rotation and generates the thumbnail + resized image itself.
            //Overrides existing images with the same name @todo: add this as option for the user to choose.
            $image_id = $storage->import_image_file($gallery_id, $file['tmp_name'], $filename, $existing_image, true, true);
        } catch (\Throwable $e) {
            return new WP_Error('ngg_add_image_error', esc_html($e->getMessage()));
        }

        if (!$image_id) {
            return new WP_Error('ngg_add_image_error', esc_html(__('Failed to upload the image to the gallery', 'darkwp')));
        }

        // import_image_file() only sets alttext from the filename; apply the rest of our fields.
        $image_mapper = \Imagely\NGG\DataMappers\Image::get_instance();
        $image = $image_mapper->find($image_id);
        if ($image) {
            if (!empty($values['alt_text'])) {
                $image->alttext = $values['alt_text'];
            }
            $image->description = $values['description'] ?? '';
            $image->exclude = empty($values['published']) ? 1 : 0;
            $image_mapper->save($image);

            if (!empty($values['tags'])) {
                $tags_raw = array_map('trim', explode(',', $values['tags'])); //String to array
                $tags = array_values(array_unique(array_filter($tags_raw))); //Cleanup and returns the values only
                wp_set_object_terms($image->pid, $tags, 'ngg_tag');
            }
        }

        return $image_id;
    }
}
