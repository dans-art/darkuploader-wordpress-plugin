<?php

namespace DarkUploaderLogging;

/**
 * File to manage the logging functions
 * Todo:
 * Write path — hook into upload_media() in rest.php to call add_log() per successful upload.
 * DataViews UI — enqueue wp-dataviews/wp-components/wp-element/wp-api-fetch, and a JS file (using wp.element.createElement directly, no build step needed) that mounts into the "Statistics & History" tab and fetches from the darkup/v1/logs REST route.
 */

use WP_Error;

if (! defined('ABSPATH')) exit;

/**
 * Returns the fully-prefixed name of the log table, e.g. wp_darkup_logs.
 * Never hardcode the table name elsewhere — always go through this, since
 * $wpdb->prefix varies per site (multisite, custom table prefixes).
 */
function get_log_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'darkup_logs';
}

/**
 * Creates (or upgrades, via dbDelta) the log table. Runs on plugin activation
 */
function create_log_table(): void
{
    global $wpdb;

    $table_name = get_log_table_name();
    $charset_collate = $wpdb->get_charset_collate();


    //Table structure
    $sql = "CREATE TABLE {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message VARCHAR(255) NOT NULL,
        message_type VARCHAR(255) DEFAULT NULL,
        image_id BIGINT DEFAULT NULL,
        gallery VARCHAR(64) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY gallery (gallery),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('darkup_db_version', DARKUP_DB_VERSION);
}

/**
 * Records one upload as a log entry (setter).
 *
 * @param string   $message       Human-readable description, e.g. "Uploaded 10 Pictures to gallery Testgallery".
 * @param string   $gallery       Gallery slug the upload went to (matches get_supported_galleries() keys).
 * @param int|null $user_id       Optional. Defaults to the current user.
 * @param string|null $message_type Optional. The type of message. Can be single | summary
 * @return int|WP_Error Inserted row id, or WP_Error on failure.
 */
function add_log(string $message, string $gallery, ?int $user_id = null, ?int $image_id = null, ?string $message_type = 'single')
{
    global $wpdb;

    if (empty($message) || empty($gallery)) {
        return new WP_Error('darkup_log_missing_fields', __('A message and gallery are required to log an upload.', 'darkup'));
    }

    $inserted = $wpdb->insert(
        get_log_table_name(),
        [
            'message' => sanitize_text_field($message),
            'gallery' => sanitize_key($gallery),
            'user_id' => $user_id ?? get_current_user_id(),
            'image_id' => $image_id ?? null,
            'message_type' => $message_type !== null ? sanitize_key($message_type) : 'single',
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%d', '%s', '%s']
    );

    if ($inserted === false) {
        return new WP_Error('darkup_log_insert_failed', __('Could not write the log entry.', 'darkup'));
    }

    return (int) $wpdb->insert_id;
}

/**
 * Retrieves log entries (getter), with optional search/filtering and pagination.
 * Shaped for the History tab's DataViews UI: a page of rows plus enough
 * paging info to render "Page X of Y".
 *
 * @param array $args {
 *     @type string $search   Optional. Matches against the message.
 *     @type string $gallery  Optional. Restrict to one gallery slug.
 *     @type int    $user_id  Optional. Restrict to one user.
 *     @type string $date     Optional. Restrict to entries logged on this Y-m-d day.
 *     @type int    $page     Optional. 1-based page number. Default 1.
 *     @type int    $per_page Optional. Rows per page. Default 20, max 100.
 * }
 * @return array{items: array, total: int, total_pages: int}
 */
function get_all_logs(array $args = []): array
{
    global $wpdb;
    $table = get_log_table_name();

    $page = max(1, (int) ($args['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($args['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = ['1=1'];
    $params = [];

    if (! empty($args['search'])) {
        $where[] = 'message LIKE %s';
        $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
    }
    if (! empty($args['gallery'])) {
        $where[] = 'gallery = %s';
        $params[] = sanitize_key($args['gallery']);
    }
    if (! empty($args['user_id'])) {
        $where[] = 'user_id = %d';
        $params[] = (int) $args['user_id'];
    }
    if (! empty($args['date'])) {
        $where[] = 'DATE(created_at) = %s';
        $params[] = $args['date'];
    }

    $where_sql = implode(' AND ', $where);

    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params));

    $items_params = array_merge($params, [$per_page, $offset]);
    $items = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d", $items_params),
        ARRAY_A
    );

    return [
        'items' => $items ?: [],
        'total' => $total,
        'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
    ];
}

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
    global $wpdb;
    $wpdb->query('TRUNCATE TABLE ' . get_log_table_name());

    add_settings_error(
        DARKUP_SETTINGS_GROUP,
        'darkup_logs_deleted',
        __('Logs deleted', 'darkup'),
        'info'
    );
}
