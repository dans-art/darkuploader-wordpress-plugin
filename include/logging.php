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
        image_id BIGINT UNSIGNED DEFAULT NULL,
        gallery VARCHAR(64) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        postmeta TEXT DEFAULT NULL,
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
 * Header names captured into postmeta alongside $_POST.
 */
const LOGGED_HEADERS = ['X-Darkup-Batch'];

/**
 * Records one upload as a log entry (setter). postmeta is captured
 * automatically from the current request's $_POST fields and the
 * allowlisted headers in LOGGED_HEADERS — callers don't pass it in.
 *
 * @param string      $message      Human-readable description, e.g. "Uploaded 10 Pictures to gallery Testgallery".
 * @param string      $gallery      Gallery slug the upload went to (matches get_supported_galleries() keys).
 * @param int|null    $user_id      Optional. Defaults to the current user.
 * @param int|null    $image_id     Optional. Attachment/image id the log entry is about.
 * @param string|null $message_type Optional. The type of message. Can be single | summary
 * @return int|WP_Error Inserted row id, or WP_Error on failure.
 */
function add_log(string $message, string $gallery, ?int $user_id = null, ?int $image_id = null, ?string $message_type = 'single')
{
    global $wpdb;

    if (empty($message) || empty($gallery)) {
        return new WP_Error('darkup_log_missing_fields', __('A message and gallery are required to log an upload.', 'darkuploader'));
    }

    $headers = [];
    foreach (LOGGED_HEADERS as $header_name) {
        $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $header_name));
        if (isset($_SERVER[$server_key])) {
            $headers[$header_name] = $_SERVER[$server_key];
        }
    }

    // map_deep() sanitizes every scalar leaf of the (possibly nested) array.
    $postmeta = map_deep([
        'post' => $_POST,
        'headers' => $headers,
    ], 'sanitize_text_field');

    $inserted = $wpdb->insert(
        get_log_table_name(),
        [
            'message' => sanitize_text_field($message),
            'gallery' => sanitize_key($gallery),
            'user_id' => $user_id ?? get_current_user_id(),
            'image_id' => $image_id,
            'message_type' => $message_type !== null ? sanitize_key($message_type) : 'single',
            'created_at' => current_time('mysql'),
            'postmeta' => wp_json_encode($postmeta),
        ],
        ['%s', '%s', '%d', '%d', '%s', '%s', '%s']
    );

    if ($inserted === false) {
        return new WP_Error('darkup_log_insert_failed', __('Could not write the log entry.', 'darkuploader'));
    }

    return (int) $wpdb->insert_id;
}
/**
 * Shorthand for add_log with the message_type 'error'
 *
 * @param string      $message      Human-readable description, e.g. "Uploaded 10 Pictures to gallery Testgallery".
 * @param string      $gallery      Gallery slug the upload went to (matches get_supported_galleries() keys).
 * @return int|WP_Error Inserted row id, or WP_Error on failure.
 */
function add_error_log(string $message, string $gallery)
{
    return add_log($message, $gallery, null, null, 'error');
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
 *     @type string $orderby  Optional. One of the keys in $sortable_columns below. Default 'date'.
 *     @type string $order    Optional. 'asc' or 'desc'. Default 'desc'.
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

    $sortable_columns = [
        'message_type' => 'message_type',
        'gallery' => 'gallery',
        'date' => 'created_at',
    ];
    $orderby_column = $sortable_columns[$args['orderby'] ?? 'date'] ?? 'created_at';
    $order = (isset($args['order']) && strtolower((string) $args['order']) === 'asc') ? 'ASC' : 'DESC';

    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params));

    $items_params = array_merge($params, [$per_page, $offset]);
    $items = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby_column} {$order} LIMIT %d OFFSET %d", $items_params),
        ARRAY_A
    );

    return [
        'items' => $items ?: [],
        'total' => $total,
        'total_pages' => $per_page > 0 ? (int) ceil($total / $per_page) : 0,
    ];
}

/**
 * Increments the plugin-wide upload counters stored in DARKUP_STATISTICS_OPTION
 * (total, per-gallery, per-user), for the "Statistics" panel of the History tab.
 *
 * @param string   $gallery      Gallery slug the upload went to.
 * @param int      $total_images How many images this call represents. Default 1.
 * @param int|null $user         Optional. Defaults to the current user.
 */
function update_statistic(string $gallery = "", int $total_images = 1, ?int $user = null)
{
    $current_stats = (array) get_option(DARKUP_STATISTICS_OPTION, []);
    $current_stats['total_images_uploaded'] = intval($current_stats['total_images_uploaded'] ?? 0) + $total_images;

    $user_id = $user ?? get_current_user_id();
    $current_stats['galleries'][$gallery] = intval($current_stats['galleries'][$gallery] ?? 0) + $total_images;
    $current_stats['by_user'][$user_id] = intval($current_stats['by_user'][$user_id] ?? 0) + $total_images;

    update_option(DARKUP_STATISTICS_OPTION, $current_stats, false);
}

/**
 * Retrieves the upload counters recorded by update_statistic()
 *
 * @return array{total_images_uploaded?: int, galleries?: array<string,int>, by_user?: array<int,int>}
 */
function get_statistics(): array
{
    return get_option(DARKUP_STATISTICS_OPTION, []);
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
        __('Logs deleted', 'darkuploader'),
        'info'
    );
}

/**
 * Deletes log entries created before the given Unix timestamp.
 *
 * @param int $till_timestamp Cutoff; entries older than this are deleted.
 */
function delete_logs(int $till_timestamp)
{
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . get_log_table_name() . ' WHERE created_at < FROM_UNIXTIME(%d)',
        $till_timestamp
    ));
}

/**
 * Cron callback for DARKUP_DAILY_CRON_HOOK. Purges log entries older than
 * the configured retention period, unless retention is set to 'forever'.
 */
function daily_cron()
{
    //Get the cron delete option
    $options = get_option(DARKUP_SETTINGS_OPTION, []);
    $logging = $options['logs'] ?? null;

    //Check if the logging cleanup should happen
    if ($logging === null || $logging === 'forever') {
        return;
    }
    $date_cutoff = strtotime('-' . strval($logging));
    delete_logs($date_cutoff);
    add_log(
        sprintf(esc_html__('Logs older than %s got deleted', 'darkuploader'), $logging),
        'none',
        null,
        null,
        'system'
    );
}

/**
 * Schedules the daily cleanup cron event, if it isn't already scheduled.
 * Runs on plugin activation.
 */
function add_cron()
{
    if (!wp_next_scheduled(DARKUP_DAILY_CRON_HOOK)) {
        wp_schedule_event(time(), 'daily', DARKUP_DAILY_CRON_HOOK);
    }
}

/**
 * Unschedules the daily cleanup cron event. Runs on plugin deactivation.
 */
function remove_cron()
{
    $timestamp = wp_next_scheduled(DARKUP_DAILY_CRON_HOOK);
    wp_unschedule_event($timestamp, DARKUP_DAILY_CRON_HOOK);
}

/**
 * Debug helper: inserts 1000 fake log rows (random gallery/user/image,
 * one per day going back in time) for exercising the Statistics & History
 * UI. Only reachable when WP_DEBUG is enabled — see tab-stats-history.php.
 */
function add_fake_log()
{
    global $wpdb;

    $entries = 1000;
    $entries_done = 0;
    while ($entries_done < $entries) {

        // map_deep() sanitizes every scalar leaf of the (possibly nested) array.
        $postmeta = map_deep([
            'post' => $_POST,
            'headers' => ['test' => 'only testdata'],
        ], 'sanitize_text_field');

        $gallery = (rand(1, 2) === 1) ? 'media-library' : 'nextgen-gallery';
        $message_type = (rand(1, 2) === 1) ? 'single' : 'collection';
        $image_id = rand(1, 100000);
        $time = strtotime('-' . $entries_done . 'days');
        $user_id = rand(1, 5);
        $inserted = $wpdb->insert(
            get_log_table_name(),
            [
                'message' => sanitize_text_field('I am the test message ' . $entries_done),
                'gallery' => sanitize_key($gallery),
                'user_id' => $user_id ?? get_current_user_id(),
                'image_id' => $image_id,
                'message_type' => $message_type !== null ? sanitize_key($message_type) : 'single',
                'created_at' => wp_date('Y-m-d H:i:s', $time),
                'postmeta' => wp_json_encode($postmeta),
            ],
            ['%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        update_statistic($gallery, 1, $user_id);
        $entries_done++;
    }
    echo "Added entries to log: " . $entries_done;
}
