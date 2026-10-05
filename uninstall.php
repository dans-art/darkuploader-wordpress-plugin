<?php

/**
 * Removes everything DarkUploader stored when the plugin gets deleted:
 * the log table, the options, the batch transients and the cleanup cron.
 *
 * Runs without the plugin being loaded, so the names are spelled out here
 * instead of using the DARKUP_* constants.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) exit;

/**
 * Removes the plugin data of the current site.
 */
function darkup_uninstall_site(): void
{
    global $wpdb;

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- dropping our own custom table on uninstall.
    $wpdb->query('DROP TABLE IF EXISTS ' . esc_sql($wpdb->prefix . 'darkup_logs'));

    delete_option('darkup_settings');
    delete_option('darkup_stats');
    delete_option('darkup_db_version');
    delete_option('darkup_db_error');

    // Batch transients are keyed by a hash, so they can't be deleted by name.
    // They expire after an hour anyway; this only covers transients stored in the options table.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_darkup_batch_') . '%',
        $wpdb->esc_like('_transient_timeout_darkup_batch_') . '%'
    ));

    wp_clear_scheduled_hook('darkup_daily_cron');
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $darkup_site_id) {
        switch_to_blog($darkup_site_id);
        darkup_uninstall_site();
        restore_current_blog();
    }
} else {
    darkup_uninstall_site();
}
