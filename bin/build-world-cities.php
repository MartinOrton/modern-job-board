<?php
$work = getenv('TEMP') . '/mjb-geonames';
$rawDir = $work . '/letter-raw';
$outBase = dirname(__DIR__) . '/assets/data';
$prefixDir = $outBase . '/cities-by-prefix';
$tmp = $work . '/prefix3-raw';
if (!is_dir($rawDir)) { fwrite(STDERR,"no raw\n"); exit(1); }
if (!is_dir($prefixDir)) mkdir($prefixDir,0755,true);
if (!is_dir($tmp)) mkdir($tmp,0755,true);
foreach (glob($prefixDir.'/*') as $f) if(is_file($f)) @unlink($f);
foreach (glob($tmp.'/*') as $f) @unlink($f);

$reserved = array_flip(['con','prn','aux','nul','com1','com2','com3','com4','com5','com6','com7','com8','com9','lpt1','lpt2','lpt3','lpt4','lpt5','lpt6','lpt7','lpt8','lpt9']);

function mjb_prefix_key3($name_l, $reserved) {
    $len = function_exists('mb_strlen') ? mb_strlen($name_l,'UTF-8') : strlen($name_l);
    if ($len < 1) return 'zzz';
    $p = function_exists('mb_substr') ? mb_substr($name_l,0,3,'UTF-8') : substr($name_l,0,3);
    if ($len < 3) $p = str_pad($p, 3, 'x');
    $safe = strtolower(preg_replace('/[^a-z0-9]/u', '', $p));
    if ($safe === '' || strlen($safe) < 1) {
        $safe = 'u';
        $n = min(3, $len);
        for ($i=0;$i<$n;$i++) {
            $ch = function_exists('mb_substr') ? mb_substr($name_l,$i,1,'UTF-8') : substr($name_l,$i,1);
            $ord = function_exists('mb_ord') ? mb_ord($ch,'UTF-8') : ord($ch);
            $safe .= dechex((int)$ord);
        }
    }
    // Windows reserved device names
    if (isset($reserved[$safe]) || isset($reserved[substr($safe,0,3)])) {
        $safe = 'x' . $safe;
    }
    // Cap filename length
    if (strlen($safe) > 40) $safe = substr($safe,0,40);
    return $safe;
}

$handles = array();
function pref_fh($k, &$handles, $tmp) {
    if (isset($handles[$k]) && is_resource($handles[$k])) {
        return $handles[$k];
    }
    // Limit open handles (Windows)
    if (count($handles) >= 64) {
        $n = 0;
        foreach ($handles as $kk => $hh) {
            if (is_resource($hh)) fclose($hh);
            unset($handles[$kk]);
            if (++$n >= 32) break;
        }
    }
    $path = $tmp . '/' . $k . '.tsv';
    $h = @fopen($path, 'ab');
    if ($h === false) {
        // fallback hash name
        $path = $tmp . '/h' . substr(md5($k), 0, 12) . '.tsv';
        $h = fopen($path, 'ab');
        if ($h === false) {
            throw new RuntimeException('fopen failed for ' . $k);
        }
    }
    $handles[$k] = $h;
    return $h;
}

$written = 0;
foreach (glob($rawDir . '/*.tsv') as $path) {
    $f = fopen($path, 'rb');
    while (($line = fgets($f)) !== false) {
        $p = explode("\t", rtrim($line, "\r\n"));
        if (count($p) < 6) continue;
        $k = mjb_prefix_key3($p[2], $reserved);
        fwrite(pref_fh($k, $handles, $tmp), $line);
        $written++;
        if ($written % 500000 === 0) echo "written=$written open=".count($handles)."\n";
    }
    fclose($f);
}
foreach ($handles as $h) if (is_resource($h)) fclose($h);
echo "streamed=$written files=" . count(glob($tmp.'/*.tsv')) . PHP_EOL;

$meta = array(); $total = 0; $max = 0; $maxk = ''; $big = array();
foreach (glob($tmp . '/*.tsv') as $path) {
    $key = basename($path, '.tsv');
    $rows = array(); $dedupe = array();
    $f = fopen($path, 'rb');
    while (($line = fgets($f)) !== false) {
        $p = explode("\t", rtrim($line, "\r\n"));
        if (count($p) < 6) continue;
        $dk = $p[2] . '|' . $p[1] . '|' . strtolower($p[4]) . '|' . strtolower($p[3]);
        $pop = (int) $p[5];
        if (isset($dedupe[$dk])) {
            $i = $dedupe[$dk];
            if ($pop > $rows[$i][5]) $rows[$i] = array($p[0], $p[1], $p[2], $p[3], $p[4], $pop);
            continue;
        }
        $dedupe[$dk] = count($rows);
        $rows[] = array($p[0], $p[1], $p[2], $p[3], $p[4], $pop);
    }
    fclose($f);
    usort($rows, function ($a, $b) { return $b[5] <=> $a[5]; });
    file_put_contents($prefixDir . '/' . $key . '.ser.gz', gzencode(serialize($rows), 6));
    $n = count($rows);
    $meta[$key] = $n;
    $total += $n;
    if ($n > $max) { $max = $n; $maxk = $key; }
    if ($n > 15000) $big[$key] = $n;
    unset($rows, $dedupe);
}
file_put_contents($prefixDir . '/index.json', json_encode(array('total' => $total, 'prefixes' => count($meta), 'max' => array($maxk => $max)), JSON_PRETTY_PRINT));
$bytes = 0;
foreach (glob($prefixDir . '/*.ser.gz') as $f) $bytes += filesize($f);
echo "TOTAL=$total prefixes=" . count($meta) . " bytes=$bytes max=$maxk:$max\n";
arsort($big);
echo "big: " . json_encode(array_slice($big, 0, 12, true)) . "\n";

function loadp($k, $dir) {
    $t0 = microtime(true);
    $rows = unserialize(gzdecode(file_get_contents($dir . '/' . $k . '.ser.gz')));
    return array($rows, round((microtime(true) - $t0) * 1000, 1));
}
foreach (array('roo'=>'rooihuiskraal','bel'=>'bellville','san'=>'sandton','sho'=>'shoreditch','bon'=>'bondi','che'=>'chelsea','dur'=>'durbanville','joh'=>'johannesburg','lon'=>'london') as $pref => $q) {
    $path = $prefixDir . '/' . $pref . '.ser.gz';
    if (!is_readable($path)) { echo "$q MISSING $pref\n"; continue; }
    list($rows, $ms) = loadp($pref, $prefixDir);
    $hits = array();
    $t0 = microtime(true);
    foreach ($rows as $r) {
        if (strpos($r[2], $q) === 0) {
            $hits[] = $r[0] . ' [' . $r[1] . ']';
            if (count($hits) >= 3) break;
        }
    }
    echo "$q load_ms=$ms rows=" . count($rows) . " search_ms=" . round((microtime(true)-$t0)*1000,2) . " => " . implode(' | ', $hits) . "\n";
}
