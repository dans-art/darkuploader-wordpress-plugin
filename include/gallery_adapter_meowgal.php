<?php

namespace DarkWPAdapter;

use DarkWPAdapter\DarkWP_Gallery_Adapter;

if (! defined('ABSPATH')) exit;

class DarkWP_MeowGallery_Adapter implements DarkWP_Gallery_Adapter
{

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

    public static function upload_image($file, array $gallery, array $metadata): bool|\WP_Error
    {
        return true;
    }
}
