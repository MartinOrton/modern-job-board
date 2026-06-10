<?php
/**
 * Generate languages/modern-job-board.pot from plugin PHP sources.
 */

$root = dirname(__DIR__);
$domain = 'modern-job-board';
$pot_path = $root . '/languages/' . $domain . '.pot';
$files = array_merge(
    array($root . '/modern-job-board.php'),
    glob($root . '/includes/*.php') ?: array()
);

$strings = array();

foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        continue;
    }

    $patterns = array(
        '/__\(\s*\'((?:\\\\\'|[^\'])*)\'\s*,\s*[\'"]' . preg_quote($domain, '/') . '[\'"]\s*\)/',
        '/_e\(\s*\'((?:\\\\\'|[^\'])*)\'\s*,\s*[\'"]' . preg_quote($domain, '/') . '[\'"]\s*\)/',
        '/esc_html__\(\s*\'((?:\\\\\'|[^\'])*)\'\s*,\s*[\'"]' . preg_quote($domain, '/') . '[\'"]\s*\)/',
        '/esc_attr__\(\s*\'((?:\\\\\'|[^\'])*)\'\s*,\s*[\'"]' . preg_quote($domain, '/') . '[\'"]\s*\)/',
    );

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $contents, $matches)) {
            foreach ($matches[1] as $string) {
                $strings[stripslashes($string)] = true;
            }
        }
    }
}

ksort($strings);

if (!is_dir(dirname($pot_path))) {
    mkdir(dirname($pot_path), 0777, true);
}

$pot = '';
$pot .= "# Copyright (C) 2026 Martin Orton\n";
$pot .= "# This file is distributed under the GPL.\n";
$pot .= "msgid \"\"\n";
$pot .= "msgstr \"\"\n";
$pot .= "\"Project-Id-Version: Modern Job Board\\n\"\n";
$pot .= "\"Report-Msgid-Bugs-To: https://github.com/MartinOrton/modern-job-board\\n\"\n";
$pot .= "\"POT-Creation-Date: " . gmdate('Y-m-d H:iO') . "\\n\"\n";
$pot .= "\"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n\"\n";
$pot .= "\"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n\"\n";
$pot .= "\"Language-Team: LANGUAGE <LL@li.org>\\n\"\n";
$pot .= "\"MIME-Version: 1.0\\n\"\n";
$pot .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$pot .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$pot .= "\"X-Domain: {$domain}\\n\"\n\n";

foreach (array_keys($strings) as $string) {
    $escaped = addcslashes($string, "\"\\");
    $pot .= "msgid \"{$escaped}\"\n";
    $pot .= "msgstr \"\"\n\n";
}

file_put_contents($pot_path, $pot);
fwrite(STDOUT, "Wrote " . count($strings) . " strings to {$pot_path}\n");