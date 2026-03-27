<?php
require 'D:/Projects/WordpressLocalInstall/wp-load.php';
$rules = get_option('rewrite_rules');
if (!is_array($rules)) { echo "NO_RULES\n"; exit; }
foreach ($rules as $k => $v) {
    if (strpos($k, 'vep/') !== false || strpos($v, 'vep_') !== false) {
        echo $k . ' => ' . $v . "\n";
    }
}
?>
