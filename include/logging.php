<?php

namespace DarkUploaderLogging;

/**
 * File to manage the logging functions
 * Todo:
 * Storage — a custom $wpdb table (activation hook + dbDelta) to actually record each upload (label, date, gallery, user), since nothing writes log entries today.
 * Write path — hook into upload_media() in rest.php to insert a row per successful upload.
 * REST read path — a new darkup/v1 endpoint returning paginated/filterable/searchable log entries, shaped for DataViews to consume.
 * DataViews UI — enqueue wp-dataviews/wp-components/wp-element/wp-api-fetch, and a JS file (using wp.element.createElement directly, no build step needed) that mounts into the "Statistics & History" tab and fetches from that endpoint.
 * 
 */

if (! defined('ABSPATH')) exit;

/**
 * Deletes every stored log entry and queues an admin notice confirming it.
 * Called from sanitize_settings() when the "logs" retention setting is
 * saved as "no" (no logging). Uses add_settings_error() rather than
 * wp_admin_notice() because this runs inside the options.php save request,
 * which redirects immediately afterward — add_settings_error() persists the
 * message across that redirect via a transient; wp_admin_notice() would not.
 */
function delete_all_logs()
{

    //Todo: Check if there are any logs, if so, delete them and show the message

    add_settings_error(
        DARKUP_SETTINGS_GROUP,
        'darkup_logs_deleted',
        __('Logs deleted', 'darkup'),
        'info'
    );
}
