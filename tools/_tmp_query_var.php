<?php
require 'D:/Projects/WordpressLocalInstall/wp-load.php';

global $wp;
if (!isset($wp) || !is_object($wp)) { echo "NO_WP\n"; exit; }
$vars = isset($wp->public_query_vars) && is_array($wp->public_query_vars) ? $wp->public_query_vars : array();
echo 'HAS_QUERY_VAR=' . (in_array('vep_update_participant_key', $vars, true) ? '1' : '0') . "\n";
?>
