#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

get_platform() {
    local os arch
    case "$(uname -s)" in
        Linux*)  os="linux" ;;
        Darwin*) os="darwin" ;;
        *) echo "Unsupported OS"; exit 1 ;;
    esac
    case "$(uname -m)" in
        x86_64|amd64) arch="x86_64" ;;
        aarch64|arm64) arch="aarch64" ;;
        *) echo "Unsupported architecture"; exit 1 ;;
    esac
    echo "${os}-${arch}"
}

get_spc_url() {
    local os
    case "$(uname -s)" in
        Linux*)  os="linux" ;;
        Darwin*) os="macos" ;;
    esac
    case "$(uname -m)" in
        x86_64|amd64) echo "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-${os}-x86_64" ;;
        aarch64|arm64) echo "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-${os}-aarch64" ;;
    esac
}

cd "$SCRIPT_DIR"

# Download spc if not present
if [[ ! -f "spc" ]]; then
    echo "Downloading static-php-cli..."
    curl -fsSL -o spc "$(get_spc_url)"
    chmod +x spc
fi

# Build PHP with micro sapi
echo "Building PHP..."
./spc craft

# Ensure vendor is installed
if [[ ! -d "$PROJECT_DIR/vendor" ]]; then
    echo "Installing dependencies..."
    composer install --no-dev -d "$PROJECT_DIR"
fi

# Create phar with all code and dependencies
echo "Creating phar..."
./buildroot/bin/php -d phar.readonly=0 << 'PHARSCRIPT'
<?php
$projectDir = dirname(__DIR__);
$pharFile = __DIR__ . '/scip-php.phar';

if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile);
$phar->startBuffering();

// Add all PHP files from src/
$phar->buildFromIterator(
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectDir . '/src', FilesystemIterator::SKIP_DOTS)
    ),
    $projectDir
);

// Add bin/scip-php
$phar->addFile($projectDir . '/bin/scip-php', 'bin/scip-php');

// Add vendor/ (dependencies)
$phar->buildFromIterator(
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectDir . '/vendor', FilesystemIterator::SKIP_DOTS)
    ),
    $projectDir
);

// Add composer.json
$phar->addFile($projectDir . '/composer.json', 'composer.json');

$stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('scip-php.phar');
require 'phar://scip-php.phar/vendor/autoload.php';
require 'phar://scip-php.phar/bin/scip-php';
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();

echo "Phar created: " . filesize($pharFile) . " bytes\n";
PHARSCRIPT

# Combine micro.sfx + phar = standalone binary
PLATFORM=$(get_platform)
OUTPUT="scip-php-${PLATFORM}"
echo "Creating binary: ${OUTPUT}"
cat buildroot/bin/micro.sfx scip-php.phar > "$OUTPUT"
chmod +x "$OUTPUT"

echo ""
echo "Done! Binary: build/${OUTPUT}"
echo "Size: $(du -h "$OUTPUT" | cut -f1)"
echo ""
echo "Usage: ./${OUTPUT} -d /path/to/project"
