=== DarkUploader - Image uploader for Darktable ===
Contributors: dansart
Tags: darktable, gallery, nextgen-gallery, media, uploader
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Send photos from Darktable straight into the WordPress Media Library or your favorite gallery plugin, in one click.

== Description ==

DarkUploader closes the gap between Darktable and WordPress. Edit your photos in Darktable, select your target gallery / library, export them, and they land in WordPress ready to publish.

**How it works**

1. Edit your photos in Darktable as usual.
2. Export them with the [DarkWP companion script](https://github.com/dans-art/darkwp/releases) for Darktable.
3. Pick a target gallery and export. DarkUploader receives the images over a REST endpoint on your site and puts them in the default folders.

DarkUploader requires the free DarkWP script to be installed in Darktable to upload the photos. DarkUploader is the WordPress side that receives it.

= Supported galleries =

* WordPress Media Library
* NextGEN Gallery
* Meow Gallery
* FooGallery

Every upload can carry a title, alt text, description, and caption, though which of those fields are used depends on what the target gallery plugin itself supports.

= Features =

* One-click export from Darktable straight into WordPress
* Upload into an existing gallery, or create a new one on the fly
* Per-image title, alt text, caption, description, and tags with placeholder support
* Batch uploads
* Configurable maximum upload size
* Upload history and statistics, with configurable log retention
* Extensible adapter system for adding support for other gallery plugins

= For developers =

DarkUploader ships with an adapter system so you can register support for a gallery plugin it doesn't cover out of the box:

* `darkuploader_supported_galleries` — filter to register your own gallery adapter.

== Installation ==

1. Install and activate DarkUploader like any other WordPress plugin (Plugins > Add New, or by uploading the zip file).
2. Go to Media > DarkUploader and choose which gallery plugin(s) DarkUploader is allowed to upload to. By default, no target is active. Make sure to select at least one target before exporting.
3. Install the [DarkWP script](https://github.com/dans-art/darkwp/releases) in Darktable — follow the installation instructions in that project's readme.
4. Enter your login and application password in the "darkwp accounts" module. The selected user must have the upload_files capability.
5. Select the export target in the export module.
6. Export photos

== Frequently Asked Questions ==

= Do I need another plugin to use DarkUploader? =

Yes. DarkUploader only handles the WordPress side of the upload. You also need the free [DarkWP script](https://github.com/dans-art/darkwp) installed in Darktable, which performs the actual export from Darktable to your site.

= Which gallery plugins are supported? =

The WordPress Media Library, NextGEN Gallery, Meow Gallery, and FooGallery are supported out of the box. Developers can add support for other galleries with the `darkuploader_supported_galleries` filter.

= Can I upload several photos to the same gallery in one export? =

Yes. There is no limit from this plugin. Some galleries might restrict the amount of images per gallery.

= Where can I see a log of past uploads? =

Go to Media > DarkUploader > Statistics & History for a log of uploads (including any errors) and overall statistics. You can control how long logs are kept, or turn logging off entirely, from the General settings.

= Something isn't working. Where can I get help? =

Open an issue on the [DarkUploader support forum on GitHub](https://github.com/dans-art/darkuploader-wordpress-plugin), or email info@dans-art.ch.

== Changelog ==

= 0.5.1 - 2026-09-05 =
* Added translator comment
* Fixed various bugs
* Fixed: Errors and warnings from the Plugin Check scan

= 0.5.0 - 2026-09-05 =
* Added Meow Gallery support
* Refactored wordpress library adapter
* Added filter to dynamically add new adapters
* Added support for FooGallery

= 0.4.0 - 2026-08-30 =
* All features for the first release version are implemented!
* Added WP cron hook
* Added function to create log entries for testing (DEBUG only)
* Added plugin deactivation function
* Improved max upload size field
* Added upload size check
* Added error logging for failed uploads
* Added a Help tab with installation and support info
* Added a "Message type" column to the History table

= 0.3.5 - 2026-08-29 =
* Added logging database table
* Added new rest route to get the logs
* Added backend style
* Created Statistics and History settings page
* Added DataTable to display the logs
* Added statistics get and update functions
* Updated rest routes to get the History


= 0.3.0 - 2026-08-26 =
* Renamed the plugin to DarkUploader with the textdomain darkup
* Added menu under Media
* Refactored code

= 0.2.0 - 2026-08-26 =
* Added the `/darkup/v1/media` REST endpoint for uploading an image to a target gallery.
* Added a `permission_callback` (requires the `upload_files` capability) to both the `/info` and `/media` REST routes.
* Implemented the NextGEN Gallery adapter: create a new gallery, or add to an existing one, uploading through NextGEN's own `import_image_file()` API so EXIF extraction, rotation correction, and thumbnail generation are handled the same way NextGEN's own uploader handles them.
* Added alt text, description, tags, and published/hidden (`exclude`) handling for uploaded NextGEN images.
* Added batch support (`X-Darkup-Batch` header) so multiple images from one Darktable export can share a single newly created gallery.
* Fixed the `target` field's validation callback, which previously accepted any input.
