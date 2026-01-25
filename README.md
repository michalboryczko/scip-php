# scip-php

[![License: MIT](https://img.shields.io/github/license/kloc-dev/scip-php)](https://github.com/kloc-dev/scip-php/blob/main/LICENSE)

SCIP Code Intelligence Protocol (SCIP) indexer for PHP

> **Note:** This is a fork of [davidrjenni/scip-php](https://github.com/davidrjenni/scip-php) with additional features and improvements. See [Fork Improvements](#fork-improvements) below.

---

## Features

- Generate SCIP indexes for PHP projects
- Standalone CLI with flexible configuration options
- Support for custom project paths and configuration files
- Optional static binary build (no PHP runtime required)
- Enhanced SCIP relationship data for better code navigation

## Requirements

- PHP 8.3+
- Composer

For projects to be indexed:
- `composer.json` and `composer.lock` files
- Dependencies installed in the vendor directory

## Installation

### Via Composer (recommended)

```bash
composer require --dev kloc/scip-php
```

### Static Binary (no PHP required)

Download pre-built binaries from the [releases page](https://github.com/kloc-dev/scip-php/releases) or build your own:

```bash
./build/build.sh
# Output: build/output/scip-php-<platform>
```

Supported platforms:
- Linux x86_64
- Linux aarch64 (ARM64)
- macOS x86_64
- macOS aarch64 (Apple Silicon)

## Usage

### Basic Usage

```bash
# Index current directory
vendor/bin/scip-php

# Index specific project
vendor/bin/scip-php -d /path/to/project

# Custom output file
vendor/bin/scip-php -o output/index.scip
```

### CLI Options

```
Usage: scip-php [options]

Options:
  -h, --help                   Display help and exit
  -d, --project-dir=PATH       Project directory to index (default: current directory)
  -c, --composer=PATH          Path to composer.json (default: <project-dir>/composer.json)
      --config=PATH            Path to scip-php.json config (default: <project-dir>/scip-php.json)
  -o, --output=PATH            Output file path (default: index.scip)
      --memory-limit=SIZE      Memory limit (default: 1G)
```

### Examples

```bash
# Index a project with custom paths
scip-php -d /my/project -o /output/index.scip

# Use custom composer.json location
scip-php --composer=/shared/composer.json

# Use custom configuration
scip-php --config=/configs/scip-php.json

# Increase memory for large projects
scip-php --memory-limit=4G
```

### Uploading to Sourcegraph

After generating the index, upload it to Sourcegraph:

```bash
# Generate index
vendor/bin/scip-php

# Upload to Sourcegraph
src code-intel upload
```

For private Sourcegraph instances, set environment variables:
```bash
export SRC_ENDPOINT=https://sourcegraph.example.com
export SRC_ACCESS_TOKEN=your-token
src code-intel upload
```

## Configuration

### scip-php.json

Create a `scip-php.json` file in your project root to configure indexer behavior:

```json
{
    "internal_packages": [
        "symfony/console",
        "doctrine/orm"
    ],
    "internal_classes": [
        "Symfony\\Component\\Console\\Command\\Command",
        "Doctrine\\ORM\\EntityManagerInterface"
    ],
    "internal_methods": [
        "Symfony\\Component\\Console\\Command\\Command::execute"
    ]
}
```

#### Configuration Options

| Option | Description |
|--------|-------------|
| `internal_packages` | Treat entire packages as internal (full indexing) |
| `internal_classes` | Treat specific classes as internal |
| `internal_methods` | Treat specific methods as internal |

By default, external dependencies are indexed with limited detail. Use these options to get full relationship and reference data for specific external code.

---

## Fork Improvements

This fork includes several enhancements over the original [davidrjenni/scip-php](https://github.com/davidrjenni/scip-php):

### Standalone CLI Application
- Project directory, composer.json, and config file can be specified via CLI arguments
- No longer requires installation as a project dependency
- Can be built as a static binary for environments without PHP

### SCIP Relationship Improvements
- **Method override relationships**: Method overrides now emit both `is_implementation` and `is_reference` flags, enabling bidirectional "Find References" between parent and child methods
- **Extends vs implements distinction**: Class extension uses `is_reference`, interface implementation uses `is_implementation`
- **Trait relationship handling**: Trait `use` statements emit both `is_reference` and `is_implementation` flags
- **Type definition relationships**: Added `is_type_definition` for property types, parameter types, and return types (enables "Go to Type Definition")

### Type Resolution Improvements
- **Foreach loop variable types**: Loop variables now inherit element type from typed arrays (`Entity[]`)
- **External dependency inheritance**: Methods inherited from external classes (PHPUnit, Symfony, etc.) now resolve correctly
- **Array function type tracking**: `array_map`, `array_filter`, `array_values`, `array_keys` now track callback return types

### Configuration System
- **Internal package config**: New `scip-php.json` config file to treat external packages as internal for full indexing

---

## Building Static Binary

The build script uses [static-php-cli](https://github.com/crazywhalecc/static-php-cli) to create a standalone executable:

```bash
# Full build (downloads dependencies, builds PHP, creates binary)
./build/build.sh

# Clean build artifacts
./build/build.sh clean

# Show help
./build/build.sh help
```

The resulting binary includes PHP and all dependencies - no runtime installation required.

---

## Development

### Running Tests

```bash
composer test
```

### Running Linters

```bash
composer lint
```

### Generating Coverage Report

```bash
composer cover
```

### Inspecting Output

```bash
# Install scip CLI from https://github.com/sourcegraph/scip
# Generate index
bin/scip-php

# Generate snapshot files for inspection
scip snapshot

# Print index as JSON
scip print --json index.scip
```

### Updating SCIP Bindings

```bash
wget -O src/Bindings/scip.proto https://raw.githubusercontent.com/sourcegraph/scip/main/scip.proto
composer gen-bindings
```

---

## Credits

- Original package: [davidrjenni/scip-php](https://github.com/davidrjenni/scip-php) by David R. Jenni
- SCIP specification: [sourcegraph/scip](https://github.com/sourcegraph/scip)
- Static binary build: [static-php-cli](https://github.com/crazywhalecc/static-php-cli)

## License

MIT License - see [LICENSE](LICENSE) for details.
