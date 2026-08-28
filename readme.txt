=== DarkUploader - Image uploader for Darktable ===
Contributors: dansart
Tags: darktable, gallery, nextgen-gallery, media, uploader
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0
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

* NextGEN Gallery — create a new gallery or add to an existing one, with alt
  text, description, tags, and published/hidden state.


== Changelog ==

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
