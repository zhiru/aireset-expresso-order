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
$distRoot = $root . DIRECTORY_SEPARATOR . 'dist';
$packageRoot = $distRoot . DIRECTORY_SEPARATOR . $packageName;

$excludedTopLevel = [
    '.git',
    '.github',
    'dist',
    'node_modules',
    'scripts',
];

$excludedFiles = [
    'AGENT.md',
    'package.json',
];

$protectedFiles = [
    'includes/class-eop-license-base.php',
    'includes/class-eop-license-manager.php',
    'includes/trait-eop-license-guard.php',
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

foreach ($protectedFiles as $relativePath) {
    $target = $packageRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($target)) {
        continue;
    }

    $stripped = php_strip_whitespace($target);
    if ($stripped === '' || $stripped === false) {
        fwrite(STDERR, "Unable to harden file: {$relativePath}\n");
        exit(1);
    }

    file_put_contents($target, $stripped);
}

fwrite(STDOUT, $packageRoot . PHP_EOL);

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
