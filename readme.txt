=== DarkUploader - Image uploader for Darktable ===
Contributors: dansart
Tags: darktable, gallery, nextgen-gallery, media, uploader
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Upload images from Darktable directly into the WordPress Media Library or supported gallery plugins.

== Description ==

DarkUploader exposes a small REST API (`darkup/v1`) that lets Darktable, or any
compatible client, upload exported images directly into WordPress and route
them into a supported gallery plugin.

`GET /darkup/v1/info` lists the gallery plugins that are installed and
active, along with the upload-form fields each one accepts.

`POST /darkup/v1/media` uploads a single image to the gallery named in the
`target` field. An recommended `X-Darkup-Batch` request header lets several
uploads from the same export share one newly created gallery instead of each
one creating its own.

Currently supported gallery plugin:

* WordPress Media Library - Upload imaged directly to the media library. With title, alt
  text, description and caption.
* NextGEN Gallery — create a new gallery or add to an existing one, with alt
  text, description and tags.
* Meow Gallery — create a new gallery or add to an existing one, with alt
  text, description and tags.
* FooGallery — create a new gallery or add to an existing one, with alt
  text, description and tags.

Developer support:
* Filter: darkuploader_supported_galleries - Allows you to register your own Adapter and therefore support for a custom gallery (plugin)

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
