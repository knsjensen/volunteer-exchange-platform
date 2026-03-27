<?php
require 'D:/Projects/WordpressLocalInstall/wp-load.php';

global $wp;
$vars = isset($wp->public_query_vars) && is_array($wp->public_query_vars) ? $wp->public_query_vars : array();
echo 'HAS_PARTICIPANT_ID=' . (in_array('vep_participant_id', $vars, true) ? '1' : '0') . "\n";
?>
