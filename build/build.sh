#!/bin/bash
set -e

# scip-php Static Binary Build Script
# Uses static-php-cli to create a standalone executable

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$SCRIPT_DIR/output"
SPC_VERSION="2.4.2"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Detect OS and Architecture
detect_platform() {
    local os arch

    case "$(uname -s)" in
        Linux*)
            os="linux"
            ;;
        Darwin*)
            os="macos"
            ;;
        *)
            log_error "Unsupported operating system: $(uname -s)"
            exit 1
            ;;
    esac

    case "$(uname -m)" in
        x86_64|amd64)
            arch="x86_64"
            ;;
        aarch64|arm64)
            arch="aarch64"
            ;;
        *)
            log_error "Unsupported architecture: $(uname -m)"
            exit 1
            ;;
    esac

    echo "${os}-${arch}"
}

# Get static-php-cli download URL
get_spc_url() {
    local platform="$1"
    local base_url="https://dl.static-php.dev/static-php-cli/spc-bin/nightly"

    case "$platform" in
        linux-x86_64)
            echo "${base_url}/spc-linux-x86_64"
            ;;
        linux-aarch64)
            echo "${base_url}/spc-linux-aarch64"
            ;;
        macos-x86_64)
            echo "${base_url}/spc-macos-x86_64"
            ;;
        macos-aarch64)
            echo "${base_url}/spc-macos-aarch64"
            ;;
        *)
            log_error "No static-php-cli binary available for platform: $platform"
            exit 1
            ;;
    esac
}

# Download static-php-cli
download_spc() {
    local platform="$1"
    local spc_path="$BUILD_DIR/spc"
    local url

    if [[ -f "$spc_path" ]]; then
        log_info "static-php-cli already downloaded"
        return
    fi

    url=$(get_spc_url "$platform")
    log_info "Downloading static-php-cli from: $url"

    mkdir -p "$BUILD_DIR"
    curl -fsSL -o "$spc_path" "$url"
    chmod +x "$spc_path"

    log_info "static-php-cli downloaded successfully"
}

# Create craft.yml configuration
create_craft_config() {
    local config_path="$BUILD_DIR/craft.yml"

    log_info "Creating craft.yml configuration"

    cat > "$config_path" << 'EOF'
# static-php-cli configuration for scip-php
php-version: "8.3"

# Required extensions for scip-php
# - json: built-in in PHP 8+
# - mbstring: string handling
# - tokenizer: PHP parsing
# - phar: for packaging
# - filter: input validation
extensions:
  - bcmath
  - ctype
  - filter
  - mbstring
  - phar
  - tokenizer

# Build only CLI and micro (for self-extracting binary)
sapi:
  - cli
  - micro

# Download options
download-options:
  prefer-pre-built: true
  retry: 3
  timeout: 120
EOF

    log_info "craft.yml created at: $config_path"
}

# Build PHP with static-php-cli
build_php() {
    local spc_path="$BUILD_DIR/spc"

    log_info "Building PHP with static-php-cli..."

    cd "$BUILD_DIR"

    # Download sources
    log_info "Downloading PHP sources and dependencies..."
    "$spc_path" download --with-php=8.3 --for-extensions="bcmath,ctype,filter,mbstring,phar,tokenizer"

    # Build
    log_info "Building PHP (this may take a while)..."
    "$spc_path" build "bcmath,ctype,filter,mbstring,phar,tokenizer" --build-cli --build-micro

    log_info "PHP build completed"
}

# Create the scip-php phar
create_phar() {
    local php_path="$BUILD_DIR/buildroot/bin/php"
    local phar_path="$BUILD_DIR/scip-php.phar"

    log_info "Creating scip-php.phar..."

    cd "$PROJECT_DIR"

    # Create a stub that loads the CLI
    cat > "$BUILD_DIR/stub.php" << 'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('scip-php.phar');
require 'phar://scip-php.phar/bin/scip-php';
__HALT_COMPILER();
STUB

    # Use the built PHP to create the phar
    "$php_path" -d phar.readonly=0 << PHARSCRIPT
<?php
\$phar = new Phar('$phar_path', 0, 'scip-php.phar');
\$phar->buildFromDirectory('$PROJECT_DIR', '/\.(php|json)$/');
\$phar->setStub(file_get_contents('$BUILD_DIR/stub.php'));
\$phar->compressFiles(Phar::GZ);
echo "Phar created: $phar_path\n";
PHARSCRIPT

    chmod +x "$phar_path"
    log_info "scip-php.phar created at: $phar_path"
}

# Create self-extracting binary using micro
create_binary() {
    local platform="$1"
    local micro_path="$BUILD_DIR/buildroot/bin/micro.sfx"
    local phar_path="$BUILD_DIR/scip-php.phar"
    local binary_name="scip-php-${platform}"
    local binary_path="$BUILD_DIR/${binary_name}"

    if [[ ! -f "$micro_path" ]]; then
        log_error "micro.sfx not found at: $micro_path"
        exit 1
    fi

    if [[ ! -f "$phar_path" ]]; then
        log_error "scip-php.phar not found at: $phar_path"
        exit 1
    fi

    log_info "Creating self-extracting binary..."

    # Combine micro.sfx with the phar to create a standalone binary
    cat "$micro_path" "$phar_path" > "$binary_path"
    chmod +x "$binary_path"

    log_info "Static binary created: $binary_path"
    log_info "Size: $(du -h "$binary_path" | cut -f1)"
}

# Clean build artifacts
clean() {
    log_info "Cleaning build directory..."
    rm -rf "$BUILD_DIR"
    log_info "Clean completed"
}

# Show usage
usage() {
    cat << EOF
scip-php Static Binary Build Script

Usage: $0 [command]

Commands:
  build       Build the static binary (default)
  clean       Clean build artifacts
  download    Download static-php-cli only
  phar        Create phar file only (requires PHP)
  help        Show this help message

Supported Platforms:
  - Linux x86_64
  - Linux aarch64 (ARM64)
  - macOS x86_64
  - macOS aarch64 (ARM64/Apple Silicon)

Requirements:
  - curl
  - ~2GB disk space for build

Examples:
  $0              # Build static binary
  $0 build        # Same as above
  $0 clean        # Remove build artifacts

The resulting binary will be at: build/output/scip-php-<platform>
EOF
}

# Main
main() {
    local command="${1:-build}"
    local platform

    platform=$(detect_platform)
    log_info "Detected platform: $platform"

    case "$command" in
        build)
            download_spc "$platform"
            create_craft_config
            build_php
            create_phar
            create_binary "$platform"
            log_info "Build completed successfully!"
            log_info "Binary location: $BUILD_DIR/scip-php-${platform}"
            ;;
        download)
            download_spc "$platform"
            ;;
        phar)
            if command -v php &> /dev/null; then
                # Use system PHP for phar creation
                BUILD_DIR="$BUILD_DIR" php -d phar.readonly=0 << 'PHARSCRIPT'
<?php
$buildDir = getenv('BUILD_DIR');
$projectDir = dirname($buildDir);
$pharPath = "$buildDir/scip-php.phar";

$phar = new Phar($pharPath, 0, 'scip-php.phar');
$phar->buildFromDirectory($projectDir, '/\.(php|json)$/');
$phar->setStub('#!/usr/bin/env php
<?php
Phar::mapPhar("scip-php.phar");
require "phar://scip-php.phar/bin/scip-php";
__HALT_COMPILER();');
$phar->compressFiles(Phar::GZ);
echo "Phar created: $pharPath\n";
PHARSCRIPT
            else
                log_error "PHP is required to create phar. Run 'build' for full static build."
                exit 1
            fi
            ;;
        clean)
            clean
            ;;
        help|--help|-h)
            usage
            ;;
        *)
            log_error "Unknown command: $command"
            usage
            exit 1
            ;;
    esac
}

main "$@"
