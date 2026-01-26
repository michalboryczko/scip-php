#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

get_os() {
    case "$(uname -s)" in
        Linux*)  echo "linux" ;;
        Darwin*) echo "darwin" ;;
        *) echo "Unsupported OS" >&2; exit 1 ;;
    esac
}

get_arch() {
    case "$(uname -m)" in
        x86_64|amd64) echo "x86_64" ;;
        aarch64|arm64) echo "aarch64" ;;
        *) echo "Unsupported architecture" >&2; exit 1 ;;
    esac
}

get_spc_url() {
    local os=$1 arch=$2
    local spc_os
    case "$os" in
        linux)  spc_os="linux" ;;
        darwin) spc_os="macos" ;;
    esac
    echo "https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-${spc_os}-${arch}"
}

OS=$(get_os)
ARCH=$(get_arch)
PLATFORM="${OS}-${ARCH}"
OUTPUT="scip-php"

echo "Building scip-php for ${PLATFORM}..."

# Step 1: Create tmp build directory and copy codebase (without vendor)
BUILD_TMP="$SCRIPT_DIR/tmp"
rm -rf "$BUILD_TMP"
mkdir -p "$BUILD_TMP"

echo "Copying source to tmp build dir..."
cp -r "$PROJECT_DIR/src" "$BUILD_TMP/"
cp -r "$PROJECT_DIR/bin" "$BUILD_TMP/"
cp "$PROJECT_DIR/composer.json" "$BUILD_TMP/"
cp "$PROJECT_DIR/composer.lock" "$BUILD_TMP/"

# Step 2: Install vendors using Docker Alpine with PHP
echo "Installing dependencies via Docker Alpine..."
docker run --rm \
    -v "$BUILD_TMP:/app" \
    -w /app \
    php:8.4-cli-alpine \
    sh -c "apk add --no-cache git unzip && \
           curl -sS https://getcomposer.org/installer | php && \
           php composer.phar install --no-dev --no-scripts --prefer-dist --optimize-autoloader"

# Step 3: Download spc if not present
cd "$SCRIPT_DIR"
if [[ ! -f "spc" ]]; then
    echo "Downloading static-php-cli..."
    curl -fsSL -o spc "$(get_spc_url "$OS" "$ARCH")"
    chmod +x spc
fi

# Step 4: Build micro.sfx
echo "Building PHP micro..."
./spc craft

# Step 5: Create phar from tmp build dir
echo "Creating phar..."
./buildroot/bin/php -d phar.readonly=0 -r '
$pharFile = "'"$SCRIPT_DIR"'/scip-php.phar";
$buildDir = "'"$BUILD_TMP"'";

$phar = new Phar($pharFile);
$phar->startBuffering();

// Add src/
$srcIter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($buildDir . "/src", FilesystemIterator::SKIP_DOTS)
);
foreach ($srcIter as $file) {
    if ($file->isFile()) {
        $phar->addFile($file->getPathname(), "src/" . $srcIter->getSubPathname());
    }
}

// Add vendor/
$vendorIter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($buildDir . "/vendor", FilesystemIterator::SKIP_DOTS)
);
foreach ($vendorIter as $file) {
    if ($file->isFile()) {
        $phar->addFile($file->getPathname(), "vendor/" . $vendorIter->getSubPathname());
    }
}

// Add bin/scip-php and composer.json
$phar->addFile($buildDir . "/bin/scip-php", "bin/scip-php");
$phar->addFile($buildDir . "/composer.json", "composer.json");

$stub = "#!/usr/bin/env php\n<?php\nPhar::mapPhar(\"scip-php.phar\");\nrequire \"phar://scip-php.phar/vendor/autoload.php\";\nrequire \"phar://scip-php.phar/bin/scip-php\";\n__HALT_COMPILER();\n";
$phar->setStub($stub);
$phar->stopBuffering();
echo "Phar: " . filesize($pharFile) . " bytes\n";
'

# Step 6: Combine using spc micro:combine
echo "Creating binary..."
./spc micro:combine scip-php.phar -O "$OUTPUT"
chmod +x "$OUTPUT"

# Cleanup all build artifacts (ignore permission errors)
rm -rf "$BUILD_TMP" buildroot downloads source pkgroot log spc scip-php.phar 2>/dev/null || true

# Copy to bin/ for consistency
mkdir -p "$PROJECT_DIR/bin"
cp "$OUTPUT" "$PROJECT_DIR/bin/scip-php" 2>/dev/null || true

echo ""
echo "Done! Binary: build/${OUTPUT}"
echo "Size: $(du -h "$OUTPUT" | cut -f1)"
echo ""
echo "Usage: ./build/${OUTPUT} -d /path/to/project"
