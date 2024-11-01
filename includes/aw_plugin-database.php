<?php

// Exit if accessed directly
if (!defined('ABSPATH')){
    exit;
}

function yektanetaffiliate_create_table_general() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'yektanetaffiliate_data';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  data_type text NOT NULL,
  fields text NOT NULL,
  PRIMARY KEY  (id)
) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta( $sql );
}

function yektanetaffiliate_create_table () {
    if (get_option('yektanetaffiliate_version') === false) {
        update_option('yektanetaffiliate_version', '1.1.0');
    }
    if (get_option('yektanetaffiliate_db_version') === false) {
        update_option('yektanetaffiliate_db_version', '1.1');
    }

    yektanetaffiliate_create_table_general();
}

function yektanetaffiliate_add_data($fields, $type) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'yektanetaffiliate_data';
    $wpdb->insert(
        $table_name,
        array(
            'fields' => $fields,
            'data_type' => $type,
        )
    );
}

function yektanetaffiliate_delete_data($type) {
    global $wpdb;
    $table  = $wpdb->prefix . 'yektanetaffiliate_data';
    $delete = $wpdb->query("DELETE FROM $table WHERE data_type = '$type'");
}

function yektanetaffiliate_get_data($type) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'yektanetaffiliate_data';
    return $wpdb->get_results("SELECT fields FROM $table_name WHERE data_type = '$type'");
}
