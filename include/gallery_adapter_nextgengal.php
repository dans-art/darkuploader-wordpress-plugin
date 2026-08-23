<?php

namespace DarkWPAdapter;

use DarkWPAdapter\DarkWP_Gallery_Adapter;

if (! defined('ABSPATH')) exit;

class DarkWP_NextGen_Adapter implements DarkWP_Gallery_Adapter
{

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

    public static function upload_image($file, array $gallery, array $metadata): bool|\WP_Error
    {
        return true;
    }
}
