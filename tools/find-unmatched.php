<?php
$tokens = token_get_all(file_get_contents(__DIR__ . '/../src/Admin/SettingsPage.php'));
$depth = 0;
$line  = 1;
foreach ($tokens as $tok) {
    if (is_array($tok)) {
        $line = $tok[2];
    } else {
        if ($tok === '{') {
            $depth++;
            if ($depth <= 2) echo "{ at line $line (depth now $depth)\n";
        }
        if ($tok === '}') {
            if ($depth <= 2) echo "} at line $line (depth was $depth)\n";
            $depth--;
            if ($depth < 0) {
                echo "UNMATCHED } at line $line (depth went to $depth)\n";
                break;
            }
        }
    }
}
echo "Final depth: $depth\n";
