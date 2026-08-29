<?php

if (! defined('ABSPATH')) exit;

$statistics = \DarkUploaderLogging\get_statistics();
?>
<h2><?php esc_html_e('Statistics', 'darkup'); ?></h2>
<div class="darkup-statistics">
    <div id="stat-total" class="stat-field">
        <h3><?php echo esc_html__('Total images uploaded', 'darkup'); ?></h3>
        <p>
            <?php echo esc_html($statistics['total_images_uploaded'] ?? 0); ?>
        </p>
    </div>
    <div id="stat-uploads-per-target" class="stat-field">
        <h3><?php echo esc_html__('Uploads per gallery', 'darkup'); ?></h3>
        <p>
            <?php
            $galleries = \DarkUploaderAdmin\get_supported_galleries(false);
            $galleries_stats = $statistics['galleries'] ?? [];
            foreach ($galleries as $key => $gall) {

                if(!isset($galleries_stats[$key])){
                    continue;
                }
                $adapter = $gall['adapter'] ?? null;
                $adapter_meta = ($adapter) ? $adapter::get_plugin_metadata() : null;
                $name = $adapter_meta['name'] ?? 'Undefined';
                $value = $galleries_stats[$key];
                echo sprintf('<p class="stat-item">%s: %s</p>', esc_html($name), number_format_i18n($value));
                }
                ?>
        </p>
    </div>
    <div id="stat-uploads-by-user" class="stat-field">
        <h3><?php echo esc_html__('Uploads by user', 'darkup'); ?></h3>
        <p>
            <?php
                $user_stats = $statistics['by_user'] ?? [];
                foreach ($user_stats as $user_id => $value) {
                    $user = get_user_by('ID', $user_id);
                    $username = $user->display_name ?? 'Unknown';
                    echo sprintf('<p class="stat-item">%s: %s</p>', esc_html($username), number_format_i18n($value));
                }
            ?>
        </p>
    </div>
</div>
<h2><?php esc_html_e('History', 'darkup'); ?></h2>
<div class="darkup-history">
    <?php if (file_exists(DARKUP_PLUGIN_DIR . '/dist/js/darkup-history.asset.php')) : ?>
        <div id="darkup-history-root"></div>
    <?php else : ?>
        <p class="description">
            <?php esc_html_e('The upload history table has not been built yet. Run `npm run build` in the plugin directory.', 'darkup'); ?>
        </p>
    <?php endif; ?>
</div>