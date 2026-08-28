<?php
/**
 * Plugin Name: DarkUploader - Image uploader for Darktable
 * Description: Upload images from Darktable directly into the WordPress Media Library or supported Gallery plugins 
 * Version: 0.3.0
 * Requires at least: 5.6
 * Tested up to: 7.1
 * 
 * Requires PHP: 7.4
 * 
 * Author: dansart
 * Author URI: https://dans-art.ch
 * 
 * Text Domain: darkup
 * Domain Path: /languages
 * License: GPLv3 or later
 * 
 */

namespace DarkUploader;
define('DARKUP_PLUGIN_DIR', __DIR__);
define('DARKUP_SLUG', 'darkuploader');
define('DARKUP_CAPABILITY', 'upload_files');
define('DARKUP_SETTINGS_GROUP', 'darkup_settings');
define('DARKUP_SETTINGS_OPTION', 'darkup_settings');

require_once(DARKUP_PLUGIN_DIR.'/include/admin.php');
require_once(DARKUP_PLUGIN_DIR.'/include/rest.php');

//Include the adapters
require_once(DARKUP_PLUGIN_DIR.'/include/gallery_adapter.php');
require_once(DARKUP_PLUGIN_DIR.'/include/gallery_adapter_nextgengal.php');
require_once(DARKUP_PLUGIN_DIR.'/include/gallery_adapter_meowgal.php');

add_action('admin_init','\\DarkUploaderAdmin\admin_init');
add_action('admin_menu','\\DarkUploaderAdmin\register_menu');
add_action('rest_api_init','\\DarkUploaderAdmin\register_rest_routes');