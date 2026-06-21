<?php
define('PCSTATUS_DATA_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data');

function _safe_name(string $name): string {
    $s = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $name);
    return substr(trim($s) ?: 'unknown', 0, 60);
}

/**
 * Grava os dados de um PC em data/{pc_name}.json de forma atomica.
 */
function pcstatus_store(string $pc_name, array $data): bool {
    $dir = PCSTATUS_DATA_DIR;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $safe = _safe_name($pc_name);
    $file = $dir . DIRECTORY_SEPARATOR . $safe . '.json';
    $tmp  = $file . '.tmp';

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    $fh = @fopen($tmp, 'w');
    if (!$fh) return false;
    flock($fh, LOCK_EX);
    fwrite($fh, $json);
    flock($fh, LOCK_UN);
    fclose($fh);

    return rename($tmp, $file);
}

/**
 * Retorna array [ "Nome-do-PC" => [ ...dados... ], ... ]
 * Ignora arquivos sem o campo pc_name (ex: status.json legado).
 */
function pcstatus_fetch_all(): array {
    $dir    = PCSTATUS_DATA_DIR;
    $result = [];

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        $fh = @fopen($file, 'r');
        if (!$fh) continue;
        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['pc_name'])) continue;

        $result[$data['pc_name']] = $data;
    }

    uasort($result, fn($a, $b) =>
        strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '')
    );

    return $result;
}
