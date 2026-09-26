<?php

if (! defined('ABSPATH')) exit;
(function () {
?>
    <h2><?php esc_html_e('Help', 'darkuploader'); ?></h2>
    <div class="darkup-help">
        <div id="help-installation" class="stat-field stat-field-full">
            <h3><?php echo esc_html__('How to install the Darktable script', 'darkuploader'); ?></h3>
            <p>
                <?php echo esc_html__('To send the pictures to WordPress, you need to install the Darktable script. You can find the latest version on Github. Follow the instructions in the readme to add the script.', 'darkuploader'); ?>
            </p>
            <p class="align-right">
                <a href="https://github.com/dans-art/darkwp" class="button button-primary" rel="noopener noreferrer" target="_blank"><?php esc_html_e('DarkWP on GitHub', 'darkuploader'); ?></a>
            </p>
        </div>
        <div id="help-rating" class="stat-field stat-field-half">
            <h3><?php echo esc_html__('Do you like the plugin?', 'darkuploader'); ?></h3>
            <p>
                <?php echo esc_html__('Please consider leaving a review on wordpress.org', 'darkuploader'); ?><br />
                <span aria-hidden="true">⭐⭐⭐⭐⭐</span>
            </p>
            <p class="align-right">
                <a href="https://wordpress.org/support/plugin/darkuploader/reviews/#new-post" class="button button-primary" rel="noopener noreferrer" target="_blank"><?php esc_html_e('Review the Plugin', 'darkuploader'); ?></a>
            </p>
        </div>
        <div id="help-misc" class="stat-field stat-field-half">
            <h3><?php echo esc_html__('Need help?', 'darkuploader'); ?></h3>
            <p>
                <?php echo esc_html__('Are you having troubles with the plugin or does something not work? Let me know.', 'darkuploader'); ?>
            </p>
            <p>
                <a href="https://wordpress.org/support/plugin/darkuploader/" class="link" rel="noopener noreferrer" target="_blank"><?php echo esc_html__('DarkUploader WordPress.org support forum', 'darkuploader'); ?></a>
            </p>
            <p>
                <a href="mailto:info@dans-art.ch" class="link"><?php echo esc_html__('Write an email to info@dans-art.ch', 'darkuploader'); ?></a>
            </p>
        </div>
    </div>
<?php
})();
?>