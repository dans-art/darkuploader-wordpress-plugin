<?php
/**
 * Plugin Name: DarkWP - Image uploader for Darktable
 * Description: Upload images from Darktable directly into the WordPress Media Library or supported Gallery plugins 
 * Version: 0.2.0
 * Requires at least: 5.6
 * Tested up to: 7.1
 * 
 * Requires PHP: 7.4
 * 
 * Author: dansart
 * Author URI: https://dans-art.ch
 * 
 * Text Domain: darkwp
 * Domain Path: /languages
 * License: GPLv3 or later
 * 
 */

namespace DarkWP;
define('DARKWP_PLUGIN_DIR', __DIR__);

require_once(DARKWP_PLUGIN_DIR.'/include/admin.php');
require_once(DARKWP_PLUGIN_DIR.'/include/rest.php');

//Include the adapters
require_once(DARKWP_PLUGIN_DIR.'/include/gallery_adapter.php');
require_once(DARKWP_PLUGIN_DIR.'/include/gallery_adapter_nextgengal.php');
require_once(DARKWP_PLUGIN_DIR.'/include/gallery_adapter_meowgal.php');

add_action('admin_init','\\DarkWPAdmin\admin_init');
add_action('rest_api_init','\\DarkWPAdmin\register_rest_routes');