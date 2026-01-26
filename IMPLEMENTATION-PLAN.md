# Standalone CLI Application Implementation Plan

## Goal
Transform scip-php from a composer package dependency to a standalone CLI application with static binary support.

## Requirements
1. CLI accepts arguments for project directory, composer.json path, config path, and output file
2. Create build script for static binary using static-php-cli
3. Support architectures: Linux x86_64, Linux aarch64, macOS aarch64
4. Update package metadata (name, author, README)

---

## Phase 1: CLI Enhancement

### Step 1.1: Update bin/scip-php CLI script
Add new options:
- `-d, --project-dir=PATH` - Project directory to index (default: current directory)
- `-c, --composer=PATH` - Path to composer.json (default: <project-dir>/composer.json)
- `--config=PATH` - Path to scip-php.json config (default: <project-dir>/scip-php.json)
- `-o, --output=PATH` - Output file path (default: index.scip)
- Keep existing: `-h, --help`, `--memory-limit`

### Step 1.2: Update Indexer class
- Add optional parameters for composer.json and config file paths
- Pass paths to Composer class

### Step 1.3: Update Composer class
- Accept optional composer.json path parameter
- Accept optional scip-php.json config path parameter
- Use provided paths instead of hardcoded filenames

---

## Phase 2: Static Binary Build System

### Step 2.1: Create build/build.sh script
- Detect OS (Linux/macOS)
- Detect architecture (x86_64/aarch64)
- Download correct static-php-cli binary
- Build PHP with required extensions
- Create micro self-executing binary

### Step 2.2: Create build/craft.yml
Configuration for static-php-cli with required extensions:
- json, mbstring, tokenizer, phar
- protobuf (if available, otherwise use pure PHP)

### Step 2.3: Create build entry point
- Create bin/scip-php-build.php wrapper that includes all dependencies
- Or use box/phar for packaging

---

## Phase 3: Package Metadata Updates

### Step 3.1: Update composer.json
- Change name: "kloc/scip-php"
- Add author: Michal Kloc
- Update description
- Keep original author credit

### Step 3.2: Update README.md
- Add fork notice at top
- Credit original author (davidrjenni)
- Document new CLI arguments
- Document static binary build process
- Document scip-php.json config format
- Keep original usage instructions

---

## Files to Create/Modify

### New Files
- `build/build.sh` - Build script for static binary
- `build/craft.yml` - static-php-cli configuration
- `IMPLEMENTATION-PLAN.md` - This file
- `IMPLEMENTATION-PROGRESS.md` - Progress tracking

### Modified Files
- `bin/scip-php` - Add new CLI arguments
- `src/Indexer.php` - Accept file path parameters
- `src/Composer/Composer.php` - Accept file path parameters
- `composer.json` - Package metadata
- `README.md` - Documentation

---

## Technical Notes

### static-php-cli Download URLs
- Linux x86_64: `https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64`
- Linux aarch64: `https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-aarch64`
- macOS aarch64: `https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-aarch64`
- macOS x86_64: `https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-x86_64`

### Required PHP Extensions for scip-php
- json (built-in PHP 8+)
- mbstring
- tokenizer
- phar (for packaging)
- protobuf (via google/protobuf composer package - pure PHP fallback)

### Build Output
- Static binary: `scip-php-<os>-<arch>`
- Example: `scip-php-linux-x86_64`, `scip-php-darwin-aarch64`
