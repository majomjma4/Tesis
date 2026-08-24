<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$action = strtolower(trim((string)($argv[1] ?? '')));
if (!in_array($action, ['dry-run', 'cleanup', 'verify'], true)) {
    fwrite(STDERR, "Uso: php scripts/cleanup_unreferenced_repository_files.php [dry-run|cleanup|verify]\n");
    exit(1);
}

/** Confirmed QA/historical set only; ambiguous support-material files are intentionally excluded. */
$candidates = [
    'storage/private/projects/184/5175b44796e3c9d56117a5ee967fa172e5f80e7f35bac8eeace7e2ad68822314.png'=>'30202b11186974f3702d2c796f7e7b373ab4e5f049166c7dfffc1d542c6ef270',
    'storage/private/projects/184/5c91dfae288f3b23ff1be037564be8e5d854f83a50d32b0c92be4816b3fe602b.docx'=>'5727051cbea2648f667faae1a3030af66bae7779baa595fa48f3c98dd09f4e98',
    'storage/private/projects/184/66c819a482a9f867356e59d92db87ade1b1001e1abc2f46af45c94ea66945af3.docx'=>'0934d15368faec3933fb910eda588ca705834998cef884126e632f96a8dcc4db',
    'storage/private/projects/184/77aa3b3143c928e27232b06a4f6973f9db19365aa6b63c74dc911eeac1c328c4.docx'=>'f7f8632b077f5bdf165d4dd9e3ef8d99e9fb84c325f403482d9fb14019040ce1',
    'storage/private/projects/184/9090782d9967871b87b9d9a2f3d8c7832c248ca441a42ec31f37867ed1f742c0.pdf'=>'90d5251af1188b9511fe0c9fbf20fe1234200f94b6dc0489c7b852bcfd103e3d',
    'storage/private/projects/184/dd14ace42896af07a582718adc6b893263c1c8b914b2ca783b58b250d5f67e00.zip'=>'41aff4447fe6c9195eac8e00c7e0de495b86b8fd3a87764e614d9148df11cf93',
    'storage/private/projects/184/eeffd4aebefe7d9d17fa40390ab921ae14a22abed66911eeaff6105367a2b8c3.docx'=>'f7f8632b077f5bdf165d4dd9e3ef8d99e9fb84c325f403482d9fb14019040ce1',
    'storage/private/projects/184/f46c197bd6226cc6cf452c0c6bcc478f51d3f1bbe40a88dd207eaf86fdfe939c.docx'=>'0e4d3ea5221d39690b87c98c47b105c26c0727d8543fa64fc9a16d3e30948107',
    'storage/private/projects/184/f5fe631dae7d4b6346aeaee4a7089c635b6afaa1f8b9081f10d07bb6572f0e7b.docx'=>'1d756a2f3dc2233b42543e0c76243088bf5dddebd1ff5856e57cf97464e124fb',
    'storage/private/projects/184/fd4cea25f3218760c72192dd496a74b310ec930a33b828a55bbd5ba4173ad0ad.png'=>'30202b11186974f3702d2c796f7e7b373ab4e5f049166c7dfffc1d542c6ef270',
    'storage/private/projects/185/qa-publicacion-pis.txt'=>'f3dc36acf35b9fbc0a59e1b203527e04ecafc952ff8f09e29a20f489cef0dd25',
    'storage/private/projects/186/6c429872404d64f428a10baa0478193f943de17520473f888ba9b80afcaf0d9f.txt'=>'d6ecc99a4481cc4fde54617422826cde83ff64d03d9d680be9caa55144a53b18',
];
foreach ([22,25,26,27] as $projectId) {
    $directory = ROOT_PATH . '/storage/private/projects/' . $projectId;
    foreach (is_dir($directory) ? (array)glob($directory . '/*') : [] as $file) {
        if (is_file($file)) $candidates[str_replace('\\','/',substr($file, strlen(ROOT_PATH)+1))] = hash_file('sha256', $file);
    }
}
foreach ([39,42,83,84,85] as $projectId) {
    $directory = ROOT_PATH . '/storage/private/projects/' . $projectId;
    foreach (is_dir($directory) ? (array)glob($directory . '/*') : [] as $file) {
        if (is_file($file)) $candidates[str_replace('\\','/',substr($file, strlen(ROOT_PATH)+1))] = hash_file('sha256', $file);
    }
}

$absolute = static fn(string $relative): string => ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
$storageRoot = realpath(ROOT_PATH . '/storage');

if ($action === 'verify') {
    if ($storageRoot === false) {
        throw new RuntimeException('No se pudo resolver el confinamiento de storage.');
    }

    $remaining = 0;
    foreach (array_keys($candidates) as $relative) {
        $path = $absolute($relative);
        $parent = realpath(dirname($path));
        if ($parent === false || !str_starts_with(strtolower($parent), strtolower($storageRoot . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Candidato fuera de storage: ' . $relative);
        }

        if (is_file($path)) {
            $real = realpath($path);
            if ($real === false || !str_starts_with(strtolower($real), strtolower($storageRoot . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Candidato fuera de storage: ' . $relative);
            }
            $remaining++;
        }
    }

    echo 'confirmed_candidates_remaining=' . $remaining . PHP_EOL;
    exit(0);
}

$rows = [];
foreach ($candidates as $relative => $expectedHash) {
    $path = $absolute($relative);
    $real = realpath($path);
    $root = realpath(ROOT_PATH . '/storage');
    if ($real === false || $root === false || !str_starts_with(strtolower($real), strtolower($root . DIRECTORY_SEPARATOR)) || !is_file($real)) {
        throw new RuntimeException('Candidato fuera de storage o inexistente: ' . $relative);
    }
    $actual = hash_file('sha256', $real);
    $rows[] = ['path'=>$relative,'bytes'=>filesize($real),'mtime'=>date('c', filemtime($real)),'sha256'=>$actual,'expected_sha256'=>$expectedHash,'hash_ok'=>hash_equals($expectedHash, $actual)];
}

if ($action === 'dry-run') {
    echo 'mode=dry-run' . PHP_EOL . 'confirmed_candidates=' . count($rows) . PHP_EOL;
    foreach ($rows as $row) echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

foreach ($rows as $row) {
    if (!$row['hash_ok']) throw new RuntimeException('Abortado: cambió el hash desde la clasificación: ' . $row['path']);
}
foreach ($rows as $row) {
    $path = $absolute($row['path']);
    if (!is_file($path) || hash_file('sha256', $path) !== $row['expected_sha256']) throw new RuntimeException('Abortado por cambio concurrente: ' . $row['path']);
    if (!unlink($path)) throw new RuntimeException('No se pudo retirar: ' . $row['path']);
}
echo 'files_removed=' . count($rows) . PHP_EOL;
