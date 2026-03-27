<?php
require 'D:/Projects/WordpressLocalInstall/wp-load.php';

global $wpdb;
$table = $wpdb->prefix . 'vep_participants';
$key = (string) $wpdb->get_var("SELECT randon_key FROM {$table} WHERE randon_key IS NOT NULL AND randon_key <> '' LIMIT 1");
if ($key === '') { echo "NO_KEY\n"; exit; }
$url = add_query_arg('vep_update_participant_key', rawurlencode($key), home_url('/'));
$res = wp_remote_get($url, array('timeout' => 15, 'sslverify' => false));
$body = is_wp_error($res) ? '' : (string) wp_remote_retrieve_body($res);
$has_form = strpos($body, 'id="vep-update-participant-form"') !== false ? '1' : '0';
$code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
echo "URL={$url}\n";
echo "CODE={$code}\n";
echo "HAS_FORM={$has_form}\n";
?>
