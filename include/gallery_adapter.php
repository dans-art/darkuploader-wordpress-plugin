<?php

namespace DarkWPAdapter;

if (! defined('ABSPATH')) exit;

interface DarkWP_Gallery_Adapter
{
    public static function get_plugin_metadata(): array;
    public static function upload_image($file, array $gallery, array $metadata): bool|\WP_Error;
}
