<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve plugin root.\n");
    exit(1);
}

$packageName = 'aireset-expresso-order';
$pluginFile = $root . DIRECTORY_SEPARATOR . $packageName . '.php';
$pluginVersion = resolvePluginVersion($pluginFile);
$distRoot = $root . DIRECTORY_SEPARATOR . 'dist';
$packageRoot = $distRoot . DIRECTORY_SEPARATOR . $packageName;
$zipPath = $root . DIRECTORY_SEPARATOR . $packageName . '.zip';

if ($pluginVersion === '') {
    fwrite(STDERR, "Unable to resolve plugin version.\n");
    exit(1);
}

$excludedTopLevel = [
    '.git',
    '.github',
    'dist',
    'node_modules',
    'scripts',
];

$excludedFiles = [
    '.gitignore',
    'AGENT.md',
    'aireset-expresso-order.zip',
    'CHANGELOG.md',
    'package.json',
    'README.md',
];

$obfuscatedFiles = [
    'includes/class-admin-page.php',
    'includes/class-eop-license-base.php',
    'includes/class-eop-license-manager.php',
    'includes/class-eop-integrity.php',
    'includes/trait-eop-license-guard.php',
    'includes/class-eop-telemetry.php',
];

rrmdir($packageRoot);
@mkdir($distRoot, 0777, true);
@mkdir($packageRoot, 0777, true);

$items = scandir($root);
if ($items === false) {
    fwrite(STDERR, "Unable to scan plugin root.\n");
    exit(1);
}

foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }

    if (is_file($root . DIRECTORY_SEPARATOR . $item) && 'zip' === strtolower((string) pathinfo($item, PATHINFO_EXTENSION))) {
        continue;
    }

    if (in_array($item, $excludedTopLevel, true) || in_array($item, $excludedFiles, true)) {
        continue;
    }

    $source = $root . DIRECTORY_SEPARATOR . $item;
    $destination = $packageRoot . DIRECTORY_SEPARATOR . $item;

    if (is_dir($source)) {
        copyDirectory($source, $destination);
        continue;
    }

    copy($source, $destination);
}

foreach ($obfuscatedFiles as $relativePath) {
    $target = $packageRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($target)) {
        continue;
    }

    if (containsInternalPhpTransitions($target)) {
        continue;
    }

    $obfuscated = obfuscatePhpFile($target);
    if ($obfuscated === '') {
        fwrite(STDERR, "Unable to obfuscate file: {$relativePath}\n");
        exit(1);
    }

    file_put_contents($target, $obfuscated);
}

buildReleaseArchive($packageRoot, $zipPath, $packageName);

fwrite(STDOUT, $packageRoot . PHP_EOL);
fwrite(STDOUT, $zipPath . PHP_EOL);

function resolvePluginVersion(string $pluginFile): string
{
    $contents = file_get_contents($pluginFile);
    if ($contents === false || $contents === '') {
        return '';
    }

    if (!preg_match('/^[ \t]*\*[ \t]*Version:\s*(.+)$/mi', $contents, $matches)) {
        return '';
    }

    return trim((string) $matches[1]);
}

function buildReleaseArchive(string $packageRoot, string $zipPath, string $packageName): void
{
    if (is_file($zipPath) && !unlink($zipPath)) {
        throw new RuntimeException("Unable to replace existing zip: {$zipPath}");
    }

    if ('Windows' === PHP_OS_FAMILY) {
        $powerShellCommand = <<<'PS'
$ErrorActionPreference = 'Stop'
$source = '__SOURCE__'
$zip = '__ZIP__'
Compress-Archive -Path $source -DestinationPath $zip -Force
PS;

        $powerShellCommand = str_replace(
            ['__SOURCE__', '__ZIP__'],
            [escapePowerShellLiteral($packageRoot), escapePowerShellLiteral($zipPath)],
            $powerShellCommand
        );

        $encodedCommand = base64_encode(mb_convert_encoding($powerShellCommand, 'UTF-16LE', 'UTF-8'));
        $command = 'powershell.exe -NoProfile -EncodedCommand ' . escapeshellarg($encodedCommand);
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($zipPath)) {
            throw new RuntimeException('Unable to create zip archive with PowerShell Compress-Archive.');
        }

        return;
    }

    if (class_exists('ZipArchive')) {
        $archive = new ZipArchive();
        if ($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create zip archive: {$zipPath}");
        }

        $archive->addEmptyDir($packageName);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packageRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $pathName = $item->getPathname();
            $relativePath = ltrim(str_replace($packageRoot, '', $pathName), DIRECTORY_SEPARATOR);
            $localPath = $packageName . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            if ($item->isDir()) {
                $archive->addEmptyDir($localPath);
                continue;
            }

            $archive->addFile($pathName, $localPath);
        }

        $archive->close();
        return;
    }

    throw new RuntimeException('ZipArchive extension is required to build the release zip on this environment.');
}

function escapePowerShellLiteral(string $value): string
{
    return str_replace("'", "''", $value);
}

function copyDirectory(string $source, string $destination): void
{
    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        throw new RuntimeException("Unable to create directory: {$destination}");
    }

    $iterator = new DirectoryIterator($source);
    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }

        $sourcePath = $item->getPathname();
        $destinationPath = $destination . DIRECTORY_SEPARATOR . $item->getFilename();

        if ($item->isDir()) {
            copyDirectory($sourcePath, $destinationPath);
            continue;
        }

        copy($sourcePath, $destinationPath);
    }
}

function rrmdir(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($directory);
}

function obfuscatePhpFile(string $path): string
{
    $source = file_get_contents($path);
    if ($source === false || $source === '') {
        return '';
    }

    $strippedSource = stripPhpTags($source);
    if ($strippedSource === '') {
        return '';
    }

    $minifiedSource = minifyPhpSource($strippedSource);
    if ($minifiedSource === '') {
        return '';
    }

    $innerPayload = base64_encode($minifiedSource);
    $innerLoader = buildInnerLoader($innerPayload);
    $outerPayload = base64_encode(gzcompress($innerLoader, 9));

    return buildOuterLoader($outerPayload);
}

function containsInternalPhpTransitions(string $path): bool
{
    $source = file_get_contents($path);
    if ($source === false || $source === '') {
        return false;
    }

    $strippedSource = stripPhpTags($source);
    if ($strippedSource === '') {
        return false;
    }

    return str_contains($strippedSource, '?>') || preg_match('/<\?(php|=)?/i', $strippedSource) === 1;
}

function stripPhpTags(string $source): string
{
    $source = trim($source);

    if (str_starts_with(strtolower($source), '<?php')) {
        $source = substr($source, 5);
    } elseif (str_starts_with($source, '<?')) {
        $source = substr($source, 2);
    }

    $source = trim($source);

    if (str_ends_with($source, '?>')) {
        $source = substr($source, 0, -2);
    }

    return trim($source);
}

function buildInnerLoader(string $innerPayload): string
{
    $decoderVar = randomVarName();
    $decoderSeedVar = randomVarName();
    $payloadVar = randomVarName();
    $runnerVar = randomVarName();

    $lines = [
        '$' . $decoderSeedVar . '=' . var_export(base64_encode('base64_decode'), true) . ';',
        '$' . $decoderVar . '=base64_decode($' . $decoderSeedVar . ');',
        '$' . $payloadVar . '=' . var_export($innerPayload, true) . ';',
        '$' . $runnerVar . '=function($__eop_code__){eval($__eop_code__);};',
        '$' . $runnerVar . '(@$' . $decoderVar . '($' . $payloadVar . '));',
    ];

    return implode('', addNoiseToStatements($lines));
}

function buildOuterLoader(string $outerPayload): string
{
    $decoderVar = randomVarName();
    $decoderSeedVar = randomVarName();
    $inflateVar = randomVarName();
    $inflateSeedVar = randomVarName();
    $payloadVar = randomVarName();
    $runnerVar = randomVarName();
    $codeVar = randomVarName();

    $lines = [
        '$' . $decoderSeedVar . '=' . var_export(base64_encode('base64_decode'), true) . ';',
        '$' . $decoderVar . '=base64_decode($' . $decoderSeedVar . ');',
        '$' . $inflateSeedVar . '=' . var_export(base64_encode('gzuncompress'), true) . ';',
        '$' . $inflateVar . '=@$' . $decoderVar . '($' . $inflateSeedVar . ');',
        '$' . $payloadVar . '=' . var_export($outerPayload, true) . ';',
        '$' . $runnerVar . '=function($__eop_blob__){eval($__eop_blob__);};',
        '$' . $codeVar . '=@$' . $inflateVar . '(@$' . $decoderVar . '($' . $payloadVar . '));',
        '$' . $runnerVar . '($' . $codeVar . ');',
    ];

    return '<?php ' . implode('', addNoiseToStatements($lines));
}

function addNoiseToStatements(array $lines): array
{
    $noisy = [];

    foreach ($lines as $line) {
        $noisy[] = randomComment() . $line . randomComment();
    }

    return $noisy;
}

function minifyPhpSource(string $source): string
{
    $tokens = token_get_all('<?php ' . $source);
    $output = '';
    $previousWasWhitespace = false;

    foreach ($tokens as $index => $token) {
        if ($index === 0) {
            continue;
        }

        if (is_string($token)) {
            $output .= $token;
            $previousWasWhitespace = false;
            continue;
        }

        [$id, $text] = $token;

        if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
            continue;
        }

        if ($id === T_WHITESPACE) {
            if ($previousWasWhitespace) {
                continue;
            }

            $output .= ' ';
            $previousWasWhitespace = true;
            continue;
        }

        $output .= $text;
        $previousWasWhitespace = false;
    }

    $output = preg_replace('/\s*([{}();,:=\[\]])\s*/', '$1', $output);
    $output = preg_replace('/\s*(=>)\s*/', '$1', $output);
    $output = preg_replace('/\s+/', ' ', $output);

    return is_string($output) ? trim($output) : '';
}

function randomVarName(): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyz';
    $length = random_int(8, 18);
    $buffer = '';

    for ($i = 0; $i < $length; $i++) {
        $buffer .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return '_' . $buffer;
}

function randomComment(): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $length = random_int(4, 14);
    $buffer = '';

    for ($i = 0; $i < $length; $i++) {
        $buffer .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return '/*' . $buffer . '*/';
}
