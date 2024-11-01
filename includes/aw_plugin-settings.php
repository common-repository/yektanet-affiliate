<?php

// Exit if accessed directly
if (!defined('ABSPATH')){
    exit;
}

function yektanetaffiliate_add_admin_menu(  ) {
    add_options_page( 'chavosh', 'chavosh', 'manage_options', 'chavosh', 'yektanetaffiliate_options_page' );
}

function yektanetaffiliate_settings_init(  ) {
    register_setting( 'chavoshPage', 'yektanetaffiliate_settings', 'yektanetaffiliate_validate_input');

    add_settings_section(
        'yektanetaffiliate_chavoshPage_section',
        __( 'chavosh', 'yektanetaffiliate' ),
        'yektanetaffiliate_settings_section_callback',
        'chavoshPage'
    );

    add_settings_field(
        'yektanetaffiliate_token',
        __( 'Token', 'yektanetaffiliate' ),
        'yektanetaffiliate_token_render',
        'chavoshPage',
        'yektanetaffiliate_chavoshPage_section'
    );

    add_settings_field(
        'yektanetaffiliate_app_id',
        __( 'App Id', 'yektanetaffiliate' ),
        'yektanetaffiliate_app_id_render',
        'chavoshPage',
        'yektanetaffiliate_chavoshPage_section'
    );

    yektanetaffiliate_activation_redirect();
}

function yektanetaffiliate_validate_input($input) {
    $output = array();
    foreach ($input as $key => $value) {
        if (isset($input[$key])) {
            if ($key == 'yektanetaffiliate_token') {
                if (strlen($value) == 40) {
                    $output[$key] = $value;
                } else {
                    $output[$key] = get_option('yektanetaffiliate_settings')['yektanetaffiliate_token'];
                    add_settings_error('yektanetaffiliate_settings',405,'Invalid input, Token should be 40 characters','error');
                }
            } else {
                if (strlen($value) == 8) {
                    $output[$key] = $value;
                } else {
                    $output[$key] = get_option('yektanetaffiliate_settings')['yektanetaffiliate_app_id'];
                    add_settings_error('yektanetaffiliate_settings',405,'Invalid input, App_Id should be 8 characters','error');
                }
            }
        }
    }
    return apply_filters( 'yektanetaffiliate_validate_input', $output, $input );
}

function yektanetaffiliate_token_render(  ) {
    $options = get_option( 'yektanetaffiliate_settings' );
    ?>
    <input type='text' name='yektanetaffiliate_settings[yektanetaffiliate_token]' value='<?php echo $options['yektanetaffiliate_token']; ?>'>
    <?php
}

function yektanetaffiliate_app_id_render(  ) {
    $options = get_option( 'yektanetaffiliate_settings' );
    ?>
    <input type='text' name='yektanetaffiliate_settings[yektanetaffiliate_app_id]' value='<?php echo $options['yektanetaffiliate_app_id']; ?>'>
    <?php
}

function yektanetaffiliate_settings_section_callback(  ) {
    echo __('please enter your token and app_id for authentication Note: By entering your token you are agreeing to send your data to our servers', 'yektanetaffiliate' );
}

function yektanetaffiliate_options_page(  ) {

    ?>
    <form action='options.php' method='post'>

        <h2>chavosh affiliate</h2>

        <?php
        settings_fields( 'chavoshPage' );
        do_settings_sections( 'chavoshPage' );
        submit_button();
        ?>

    </form>
    <?php

}
