<?php

/**
 * Main wrapper for the DarkUploader settings screen.
 * Expects $tabs (slug => label) and $active_tab to be set by the caller.
 */

if (! defined('ABSPATH')) exit;

?>
<div class="wrap">
    <h1><?php esc_html_e('DarkUploader', 'darkup'); ?></h1>

    <?php settings_errors(DARKUP_SETTINGS_GROUP); ?>

    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_slug => $tab_label) :
            $tab_url = add_query_arg(['page' => DARKUP_SLUG, 'tab' => $tab_slug], admin_url('upload.php'));
            $tab_class = ($tab_slug === $active_tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
        ?>
            <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr($tab_class); ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="darkup-tab-content">
        <?php
        $tab_view = DARKUP_PLUGIN_DIR . '/include/views/tab-' . $active_tab . '.php';
        if (file_exists($tab_view)) {
            require $tab_view;
        }
        ?>
    </div>
</div>
