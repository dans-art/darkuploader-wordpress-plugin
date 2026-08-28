<?php

if (! defined('ABSPATH')) exit;

?>
<form method="post" action="options.php">
    <?php
    settings_fields(DARKUP_SETTINGS_GROUP);
    do_settings_sections(DARKUP_SLUG . '-general');
    submit_button();
    ?>
</form>
