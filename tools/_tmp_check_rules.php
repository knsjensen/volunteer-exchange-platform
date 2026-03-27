<?php
require 'D:/Projects/WordpressLocalInstall/wp-load.php';

do_action('init');
flush_rewrite_rules();

$rules = get_option('rewrite_rules');
$has_en = false;
$has_da = false;
if (is_array($rules)) {
    $has_en = array_key_exists('vep/updateparticipant/([^/]+)/?$', $rules);
    $has_da = array_key_exists('vep/opdaterdeltager/([^/]+)/?$', $rules);
}

echo 'HAS_EN_RULE=' . ($has_en ? '1' : '0') . "\n";
echo 'HAS_DA_RULE=' . ($has_da ? '1' : '0') . "\n";
?>
