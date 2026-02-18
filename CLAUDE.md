# scip-php Development Guide

## Overview

SCIP (Source Code Intelligence Protocol) indexer for PHP. Analyzes PHP codebases and produces a unified JSON file containing symbol definitions, references, relationships, call graph, and data-flow information. All development and usage is Docker-based.

## Requirements

- PHP >=8.3
- Composer
- Docker (for builds and CI)

Key dependencies: nikic/php-parser v5, google/protobuf, phpstan/phpdoc-parser, composer/class-map-generator.

## Build

```bash
./build/build.sh  # builds Docker image: scip-php
```

## Usage

```bash
# Standard indexing (stable call kinds only)
./bin/scip-php.sh -d /path/to/project -o /path/to/output

# With experimental call kinds (function, access_array, operators)
./bin/scip-php.sh -d /path/to/project -o /path/to/output --experimental
```

Runs scip-php in Docker with the project mounted as `/input` (read-only) and the output directory as `/output`.

Output: `index.json` (unified JSON with SCIP + calls + values, version 4.0)

### Call kinds

**Stable (always generated):**
- `access`, `method`, `constructor`, `access_static`, `method_static`

**Experimental (require `--experimental` flag):**
- `function`, `access_array`, `coalesce`, `ternary`, `ternary_full`, `match`

## Testing and Linting

All test/lint commands run inside Docker:

```bash
# Build dev image first
docker build -t scip-php-dev -f Dockerfile.dev .

# Run tests
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpunit --no-coverage

# Run linting
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpcs
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpstan --memory-limit=2G
```

Or using composer scripts (inside the container):
```bash
composer test      # phpunit --no-coverage
composer lint      # phpcs + phpstan + composer validate
```

## Architecture

### Source layout

```
src/
  Indexer.php           # Main entry point: orchestrates indexing pipeline
  SymbolNamer.php       # Symbol name generation (SCIP symbol strings)
  DocGenerator.php      # Doc comment extraction
  DocIndexer.php        # Documentation indexing
  Bindings/             # SCIP protobuf bindings (auto-generated)
    GPBMetadata/
    Scip/
  Calls/                # Call graph analysis
  Composer/             # Composer autoload integration
  File/                 # File-level operations
  Indexing/             # Core indexing logic (visitors, scopes)
  Parser/               # PHP-Parser integration
  Types/                # Type resolution and inference
```

### Pipeline

1. Composer autoload map discovers all PHP files in the project
2. PHP-Parser parses each file into an AST
3. Indexing visitors walk the AST, generating SCIP occurrences (definitions + references)
4. Call analysis extracts method calls, property accesses, constructors
5. Results are serialized to unified JSON format (index.json v4.0)

## Contract Tests

Contract tests live in the reference project and validate scip-php output against the schema:

```bash
# From kloc root
cd kloc-reference-project-php/contract-tests
bin/run.sh test

# With experimental call kinds
bin/run.sh test --experimental
```

## Dev Container

```bash
docker build -t scip-php .
docker run --rm -it -v $(pwd):/app --entrypoint sh scip-php
```

## Reference Documentation

| Document | Description |
|----------|-------------|
| `docs/reference/kloc-scip/calls-schema.json` | JSON Schema for calls data (authoritative spec) |
| `docs/reference/kloc-scip/calls-and-data-flow.md` | Detailed explanation with examples |
| `docs/reference/kloc-scip/calls-schema-docs.md` | Quick reference for schema fields |
| `docs/specs/scip-php-missing-features.md` | Features to implement (from contract tests) |

Paths are relative to the kloc monorepo root.
