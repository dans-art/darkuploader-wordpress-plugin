<?php
/**
 * Plugin Name: DarkUploader - Image uploader for Darktable
 * Description: Upload images from Darktable directly into the WordPress Media Library or supported Gallery plugins 
 * Version: 0.5.0
 * Requires at least: 6.6
 * Tested up to: 7.1
 * 
 * Requires PHP: 7.4
 * 
 * Author: dansart
 * Author URI: https://dans-art.ch
 * 
 * Text Domain: darkuploader
 * Domain Path: /languages
 * License: GPLv3 or later
 * 
 */

namespace DarkUploader;

//Define constants
define('DARKUP_PLUGIN_VERSION', '0.5.0');
define('DARKUP_PLUGIN_DIR', __DIR__);
define('DARKUP_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ));

define('DARKUP_SLUG', 'darkuploader');
define('DARKUP_CAPABILITY', 'upload_files');

define('DARKUP_SETTINGS_GROUP', 'darkup_settings');
define('DARKUP_SETTINGS_OPTION', 'darkup_settings');
define('DARKUP_STATISTICS_OPTION', 'darkup_stats');

define('DARKUP_DAILY_CRON_HOOK', 'darkup_daily_cron');

define('DARKUP_DB_VERSION', '1.0');

//Include the scripts
require_once(DARKUP_PLUGIN_DIR.'/include/admin.php');
require_once(DARKUP_PLUGIN_DIR.'/include/rest.php');
require_once(DARKUP_PLUGIN_DIR.'/include/logging.php');

//Include the adapters
require_once(DARKUP_PLUGIN_DIR.'/include/gallery_adapter.php');
require_once(DARKUP_PLUGIN_DIR.'/include/gallery_adapter_wordpress_library.php');
require_once(DARKUP_PLUGIN_DIR.'/include/gallery_adapter_nextgengal.php');
require_once(DARKUP_PLUGIN_DIR.'/include/gallery_adapter_meowgal.php');

register_activation_hook(__FILE__, '\\DarkUploaderAdmin\\on_plugin_activation');
register_deactivation_hook(__FILE__, '\\DarkUploaderAdmin\\on_plugin_deactivation');
//@Todo: Add deactivation hook with options to delete all data

add_action('admin_init','\\DarkUploaderAdmin\admin_init');
add_action('admin_menu','\\DarkUploaderAdmin\register_menu');
add_action('rest_api_init','\\DarkUploaderAdmin\register_rest_routes');

//Cron
add_action(DARKUP_DAILY_CRON_HOOK, '\\DarkUploaderLogging\daily_cron');