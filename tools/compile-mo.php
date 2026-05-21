<?php
/**
 * Simple PO -> MO compiler.
 *
 * Usage:
 *   php tools/compile-mo.php languages/volunteer-exchange-platform-da_DK.po languages/volunteer-exchange-platform-da_DK.mo
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$in = $argv[1] ?? null;
$out = $argv[2] ?? null;

if (!$in || !$out) {
    fwrite(STDERR, "Usage: php tools/compile-mo.php <input.po> <output.mo>\n");
    exit(1);
}

if (!is_file($in)) {
    fwrite(STDERR, "Input file not found: {$in}\n");
    exit(1);
}

$lines = file($in, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Failed to read: {$in}\n");
    exit(1);
}

$entries = [];
$state = null; // 'msgctxt' | 'msgid' | 'msgstr'
$msgctxt = '';
$msgid = '';
$msgid_plural = '';
$msgstr = '';          // used for simple (non-plural) entries
$msgstr_plural = [];   // used for plural entries: index => translation

$flush = function () use (&$entries, &$msgctxt, &$msgid, &$msgid_plural, &$msgstr, &$msgstr_plural) {
    if ($msgid !== '' || $msgid === '') { // always flush (header has empty msgid)
        $key = $msgctxt ? $msgctxt . "\x04" . $msgid : $msgid;

        if ($msgid_plural !== '') {
            // Plural entry: key is "singular\0plural", value is "t0\0t1\0..."
            $key = $msgctxt
                ? $msgctxt . "\x04" . $msgid . "\x00" . $msgid_plural
                : $msgid . "\x00" . $msgid_plural;
            ksort($msgstr_plural);
            $entries[$key] = implode("\x00", $msgstr_plural);
        } else {
            $entries[$key] = $msgstr;
        }
    }
    $msgctxt     = '';
    $msgid       = '';
    $msgid_plural = '';
    $msgstr      = '';
    $msgstr_plural = [];
};

$unquote = function (string $s): string {
    $s = trim($s);
    if ($s === '""') {
        return '';
    }
    if (preg_match('/^"(.*)"$/', $s, $m)) {
        return stripcslashes($m[1]);
    }
    return '';
};

foreach ($lines as $line) {
    $line = rtrim($line, "\r\n");

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (str_starts_with($line, 'msgctxt ')) {
        if ($state !== null) {
            $flush();
        }
        $state = 'msgctxt';
        $msgctxt = $unquote(substr($line, 7));
        continue;
    }

    if (str_starts_with($line, 'msgid_plural ')) {
        $state = 'msgid_plural';
        $msgid_plural = $unquote(substr($line, 12));
        continue;
    }

    if (str_starts_with($line, 'msgid ')) {
        if ($state !== null && $state !== 'msgctxt') {
            $flush();
        }
        $state = 'msgid';
        $msgid = $unquote(substr($line, 5));
        continue;
    }

    // msgstr[N] "..." — plural translation forms
    if (preg_match('/^msgstr\[(\d+)\] (.*)$/', $line, $m)) {
        $state = 'msgstr_plural';
        $msgstr_plural[(int) $m[1]] = $unquote($m[2]);
        continue;
    }

    if (str_starts_with($line, 'msgstr ')) {
        $state = 'msgstr';
        $msgstr = $unquote(substr($line, 6));
        continue;
    }

    if (str_starts_with($line, '"')) {
        if ($state === 'msgctxt') {
            $msgctxt .= $unquote($line);
        } elseif ($state === 'msgid') {
            $msgid .= $unquote($line);
        } elseif ($state === 'msgid_plural') {
            $msgid_plural .= $unquote($line);
        } elseif ($state === 'msgstr') {
            $msgstr .= $unquote($line);
        } elseif ($state === 'msgstr_plural') {
            // Continuation of the last msgstr[N].
            $last = max(array_keys($msgstr_plural));
            $msgstr_plural[$last] .= $unquote($line);
        }
        continue;
    }
}

if ($state !== null) {
    $flush();
}

// Ensure deterministic order.
ksort($entries, SORT_STRING);

$originals = [];
$translations = [];
foreach ($entries as $o => $t) {
    $originals[] = $o;
    $translations[] = $t;
}

$count = count($originals);

// Build MO binary (little-endian).
$MAGIC = 0x950412de;
$REVISION = 0;
$ORIG_TABLE_OFFSET = 28;
$TRANS_TABLE_OFFSET = $ORIG_TABLE_OFFSET + ($count * 8);
$HASH_SIZE = 0;
$HASH_OFFSET = $TRANS_TABLE_OFFSET + ($count * 8);

$header = pack('V7', $MAGIC, $REVISION, $count, $ORIG_TABLE_OFFSET, $TRANS_TABLE_OFFSET, $HASH_SIZE, $HASH_OFFSET);

$origTable = '';
$transTable = '';
$origStrings = '';
$transStrings = '';

$origOffset = $HASH_OFFSET;
$transOffset = $origOffset;

// Originals string pool
foreach ($originals as $o) {
    $bytes = $o . "\0";
    $len = strlen($o);
    $origTable .= pack('V2', $len, $origOffset);
    $origStrings .= $bytes;
    $origOffset += strlen($bytes);
}

$transOffset = $origOffset;

// Translations string pool
foreach ($translations as $t) {
    $bytes = $t . "\0";
    $len = strlen($t);
    $transTable .= pack('V2', $len, $transOffset);
    $transStrings .= $bytes;
    $transOffset += strlen($bytes);
}

$mo = $header . $origTable . $transTable . $origStrings . $transStrings;

$outDir = dirname($out);
if (!is_dir($outDir)) {
    if (!mkdir($outDir, 0777, true) && !is_dir($outDir)) {
        fwrite(STDERR, "Failed to create output directory: {$outDir}\n");
        exit(1);
    }
}

if (file_put_contents($out, $mo) === false) {
    fwrite(STDERR, "Failed to write: {$out}\n");
    exit(1);
}

fwrite(STDOUT, "Wrote MO: {$out} (entries: {$count})\n");
