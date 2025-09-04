<?php
// tracker.php
// logs visits by country into data.json using IP2Location DB1.LITE.IPV6.CSV
// supports IPv4 + IPv6 because that CSV contains both

// --- CONFIG ---
$CSV_IPV6 = __DIR__ . '/IP2LOCATION-LITE-DB1.IPV6.CSV'; // drop the CSV here
$DATA_JSON = __DIR__ . '/data.json';
// trust proxies? (if behind CF/nginx etc.)
$TRUST_PROXY = false;
$TRUSTED_PROXIES = [];
// ---------------

header('Content-Type: application/json');

function client_ip(bool $trust_proxy, array $trusted) : string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ($trust_proxy && in_array($ip, $trusted, true)) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) {
            $parts = array_map('trim', explode(',', $xff));
            if (!empty($parts[0])) return $parts[0];
        }
    }
    return $ip;
}

function ipToBin16(string $ip) : ?string {
    $bin = @inet_pton($ip);
    if ($bin === false || $bin === null) return null;
    if (strlen($bin) === 4) { // IPv4
        return str_repeat("\x00", 10) . "\xff\xff" . $bin; // v4-mapped 16 bytes
    }
    return $bin; // IPv6
}

function decToBin16(string $dec) : string {
    if (function_exists('gmp_init')) {
        $g = gmp_init($dec, 10);
        $hex = gmp_strval($g, 16);
    } else {
        // fallback using BCMath
        $hex = '';
        $n = $dec;
        if ($n === '0') $hex = '0';
        while (bccomp($n, '0') > 0) {
            $rem = bcmod($n, '16');
            $hex = dechex((int)$rem) . $hex;
            $n = bcdiv($n, '16', 0);
        }
    }
    if (strlen($hex) % 2) $hex = '0' . $hex;
    $bin = hex2bin($hex);
    return str_pad($bin, 16, "\x00", STR_PAD_LEFT);
}

function ipv4DecToBin16(string $dec) : string {
    $n = (int)$dec;
    $v4 = pack('N', $n);
    return str_repeat("\x00", 10) . "\xff\xff" . $v4;
}

function parseCsvRowToRange(array $row) : ?array {
    if (count($row) < 4) return null;
    [$from, $to, $cc] = [$row[0], $row[1], strtoupper(trim($row[2]))];

    $decimal = ctype_digit($from) && ctype_digit($to);
    if ($decimal) {
        // detect IPv4 vs IPv6 by size of number
        if (strlen($to) <= 10 && (int)$to <= 4294967295) {
            $fromBin = ipv4DecToBin16($from);
            $toBin   = ipv4DecToBin16($to);
        } else {
            $fromBin = decToBin16($from);
            $toBin   = decToBin16($to);
        }
    } else {
        $fromBin = ipToBin16($from);
        $toBin   = ipToBin16($to);
        if ($fromBin === null || $toBin === null) return null;
    }
    return [$fromBin, $toBin, $cc];
}

function country_from_ip(string $ip, string $csvFile) : string {
    $ip16 = ipToBin16($ip);
    if ($ip16 === null) return 'ZZ';
    if (!is_readable($csvFile)) return 'ZZ';

    $fh = fopen($csvFile, 'r');
    if (!$fh) return 'ZZ';
    while (($row = fgetcsv($fh)) !== false) {
        $rec = parseCsvRowToRange($row);
        if (!$rec) continue;
        [$fromBin, $toBin, $cc] = $rec;
        if (strcmp($ip16, $fromBin) >= 0 && strcmp($ip16, $toBin) <= 0) {
            fclose($fh);
            return $cc ?: 'ZZ';
        }
    }
    fclose($fh);
    return 'ZZ';
}

function load_data(string $file) : array {
    if (!is_file($file)) return ['countries' => [], 'last_updated' => null];
    $raw = @file_get_contents($file);
    if ($raw === false) return ['countries' => [], 'last_updated' => null];
    $j = @json_decode($raw, true);
    if (!is_array($j)) return ['countries' => [], 'last_updated' => null];
    if (!isset($j['countries']) || !is_array($j['countries'])) $j['countries'] = [];
    return $j;
}

function save_data(string $file, array $data) : bool {
    $data['last_updated'] = gmdate('c');
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) === false) {
        return false;
    }
    return rename($tmp, $file);
}

// ---- main ----
// ---- main ----
$ip = $_GET['testip'] ?? client_ip($TRUST_PROXY, $TRUSTED_PROXIES);

if (!is_readable($CSV_IPV6)) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'CSV not found']);
    exit;
}

$cc = country_from_ip($ip, $CSV_IPV6);
$data = load_data($DATA_JSON);
if (!isset($data['countries'][$cc])) $data['countries'][$cc] = 0;
$data['countries'][$cc]++;

if (!save_data($DATA_JSON, $data)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'failed to save data.json']);
    exit;
}

echo json_encode([
    'ok'=>true,
    'ip'=>$ip,
    'country'=>$cc,
    'total'=>$data['countries'][$cc]
]);
