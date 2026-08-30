<?php

if (! defined('ABSPATH')) exit;

?>
<h2><?php esc_html_e('Help', 'darkup'); ?></h2>
<div class="darkup-help">
    <div id="help-installation" class="stat-field stat-field-full">
        <h3><?php echo esc_html__('How to install the Darktable script', 'darkup'); ?></h3>
        <p>
            <?php echo esc_html__('To send the pictures to WordPress, you need to install the Darktable script. You can find the latest version on Github. Follow the instructions in the readme to add the script.', 'darkup'); ?>
        </p>
        <p class="align-right">
            <a href="https://github.com/dans-art/darkwp" class="button button-primary" target="_blank">Github</a>
        </p>
    </div>
    <div id="help-rating" class="stat-field stat-field-half">
        <h3><?php echo esc_html__('Do you like the plugin?', 'darkup'); ?></h3>
        <p>
            <?php echo esc_html__('Please consider leaving a review on wordpress.org', 'darkup'); ?><br/>
            <?php echo esc_html__('⭐⭐⭐⭐⭐', 'darkup'); ?>
        </p>
        <p class="align-right">
            <a href="https://github.com/dans-art/darkwp-wordpress-plugin" class="button button-primary" target="_blank">Review the Plugin</a>
        </p>
    </div>
    <div id="help-misc" class="stat-field stat-field-half">
        <h3><?php echo esc_html__('Need help?', 'darkup'); ?></h3>
        <p>
            <?php echo esc_html__('Are you having troubles with the plugin or does something not work? Let me know.', 'darkup'); ?>
        </p>
        <p>
            <a href="https://github.com/dans-art/darkwp-wordpress-plugin" class="link" target="_blank"><?php echo esc_html__('DarkUploader WordPress.org support forum', 'darkup'); ?></a>
        </p>
        <p>
            <a href="mailto:info@dans-art.ch" class="link" target="_blank"><?php echo esc_html__('Write an email to info@dans-art.ch', 'darkup'); ?></a>
        </p>
    </div>
</div>