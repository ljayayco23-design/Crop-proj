<?php
/**
 * PAGASA / GDACS feed tester (standalone, no Laravel needed)
 *
 * HOW TO RUN
 *   Terminal:  php pagasa_feed_test.php
 *   Browser:   put it in your /public folder, open  /pagasa_feed_test.php
 *   If SSL errors appear (some gov sites have cert problems), retry with:
 *              php pagasa_feed_test.php insecure
 *              or  /pagasa_feed_test.php?insecure=1
 *
 * Delete this file after testing.
 */

$isCli    = (PHP_SAPI === 'cli');
$insecure = $isCli ? in_array('insecure', $argv ?? [], true) : isset($_GET['insecure']);

// Candidate sources. "feed" = expected to be RSS/Atom/CAP XML. "page" = just a reachability check.
$targets = [
    ['name' => 'PAGASA CAP feed (http)',      'url' => 'http://publicalert.pagasa.dost.gov.ph/feeds/',  'kind' => 'feed'],
    ['name' => 'PAGASA CAP feed (https)',     'url' => 'https://publicalert.pagasa.dost.gov.ph/feeds/', 'kind' => 'feed'],
    ['name' => 'PAGASA main website',         'url' => 'https://www.pagasa.dost.gov.ph/',               'kind' => 'page'],
    ['name' => 'PAGASA Severe Weather Bulletin page', 'url' => 'https://bagong.pagasa.dost.gov.ph/tropical-cyclone/severe-weather-bulletin', 'kind' => 'page'],
    ['name' => 'GDACS RSS (global, filter PHL)', 'url' => 'https://www.gdacs.org/xml/rss.xml',          'kind' => 'feed'],
];

function out($s) { global $isCli; echo $isCli ? $s . "\n" : htmlspecialchars($s) . "<br>\n"; }
function line() { out(str_repeat('-', 70)); }

function fetchUrl($url, $insecure) {
    $start = microtime(true);
    $res = ['ok' => false, 'code' => 0, 'type' => '', 'body' => '', 'error' => '', 'ms' => 0, 'final' => $url];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (RICEGUARD feed test)',
            CURLOPT_SSL_VERIFYPEER => !$insecure,
            CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/xml, application/rss+xml, application/atom+xml, text/xml, */*'],
        ]);
        $body = curl_exec($ch);
        $res['code']  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $res['type']  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $res['final'] = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if ($body === false) $res['error'] = curl_error($ch);
        else $res['body'] = $body;
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => 20, 'header' => "User-Agent: RICEGUARD feed test\r\n", 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => !$insecure, 'verify_peer_name' => !$insecure],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) $res['error'] = 'file_get_contents failed (no curl available)';
        else $res['body'] = $body;
        if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $res['code'] = (int) $m[1];
    }

    $res['ms'] = (int) round((microtime(true) - $start) * 1000);
    $res['ok'] = ($res['error'] === '' && $res['code'] >= 200 && $res['code'] < 300);
    return $res;
}

function analyzeXml($body) {
    $info = ['valid' => false, 'format' => 'unknown', 'items' => 0, 'titles' => [], 'phl' => 0, 'cap' => false];
    $trim = ltrim($body, "\xEF\xBB\xBF \t\r\n");
    if ($trim === '' || $trim[0] !== '<') return $info;

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($trim, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) return $info;
    $info['valid'] = true;

    $root = strtolower($xml->getName());
    if ($root === 'rss')        { $info['format'] = 'RSS';  $nodes = $xml->channel->item ?? []; }
    elseif ($root === 'feed')   { $info['format'] = 'Atom'; $nodes = $xml->entry ?? []; }
    elseif ($root === 'alert')  { $info['format'] = 'CAP (single alert)'; $nodes = [$xml]; $info['cap'] = true; }
    else                        { $info['format'] = "XML <{$root}>"; $nodes = []; }

    foreach ($nodes as $n) {
        $info['items']++;
        $title = trim((string) ($n->title ?? $n->headline ?? ''));
        if ($title === '' && isset($n->info->headline)) $title = trim((string) $n->info->headline);
        if (count($info['titles']) < 5) $info['titles'][] = $title !== '' ? $title : '(no title)';
        $blob = strtolower($n->asXML());
        if (strpos($blob, 'philippines') !== false || strpos($blob, 'phl') !== false || strpos($blob, 'pagasa') !== false) $info['phl']++;
        if (strpos($blob, 'urn:oasis:names:tc:emergency:cap') !== false || strpos($blob, '<cap:') !== false) $info['cap'] = true;
    }
    return $info;
}

if (!$isCli) echo '<pre style="font:13px/1.5 monospace;white-space:pre-wrap">';

out('PAGASA / GDACS feed test  -  ' . date('Y-m-d H:i:s') . '  (SSL verify: ' . ($insecure ? 'OFF' : 'ON') . ')');
line();

$summary = [];
foreach ($targets as $t) {
    out('TEST: ' . $t['name']);
    out('URL : ' . $t['url']);
    $r = fetchUrl($t['url'], $insecure);

    if ($r['error'] !== '') {
        out('RESULT: FAIL  (' . $r['error'] . ')  [' . $r['ms'] . ' ms]');
        $summary[] = [$t['name'], 'FAIL'];
        line();
        continue;
    }

    out("HTTP  : {$r['code']}   Type: {$r['type']}   Size: " . strlen($r['body']) . " bytes   Time: {$r['ms']} ms");
    if ($r['final'] !== $t['url']) out('Redirected to: ' . $r['final']);

    if (!$r['ok']) {
        out('RESULT: FAIL  (HTTP ' . $r['code'] . ')');
        $summary[] = [$t['name'], 'FAIL'];
        line();
        continue;
    }

    if ($t['kind'] === 'page') {
        out('RESULT: REACHABLE (page loads; not a feed)');
        $summary[] = [$t['name'], 'REACHABLE'];
        line();
        continue;
    }

    $a = analyzeXml($r['body']);
    if (!$a['valid']) {
        out('RESULT: NOT A VALID XML FEED (got HTML or something else)');
        out('First 200 chars: ' . preg_replace('/\s+/', ' ', substr($r['body'], 0, 200)));
        $summary[] = [$t['name'], 'NOT FEED'];
    } elseif ($a['items'] === 0) {
        out("RESULT: EMPTY  (valid {$a['format']} but 0 items)");
        $summary[] = [$t['name'], 'EMPTY'];
    } else {
        out("RESULT: PASS  ({$a['format']}, {$a['items']} items, {$a['phl']} mention Philippines/PAGASA" . ($a['cap'] ? ', CAP detected' : '') . ')');
        foreach ($a['titles'] as $i => $title) out('   ' . ($i + 1) . '. ' . $title);
        $summary[] = [$t['name'], 'PASS'];
    }
    line();
}

out('SUMMARY');
foreach ($summary as [$n, $s]) out(sprintf('  %-45s %s', $n, $s));
line();
out('How to read it:');
out('  PASS      = feed works and has data, can be parsed in Laravel.');
out('  EMPTY     = feed is alive but has no active alerts right now (try again during a storm/rain warning).');
out('  NOT FEED  = URL returns a web page, not XML. This source is not usable as a feed.');
out('  FAIL      = blocked, timed out, or SSL error. Retry with the "insecure" option, or test from your deployed server.');

if (!$isCli) echo '</pre>';