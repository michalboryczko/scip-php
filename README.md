# scip-php

PHP SCIP indexer that produces unified JSON output containing code structure, call sites, and data flow information. Fork of [davidrjenni/scip-php](https://github.com/davidrjenni/scip-php) with significant extensions for call tracking and data flow analysis.

## Pipeline Position

```
PHP code -> scip-php -> index.json -> kloc-mapper -> sot.json -> kloc-cli -> output
```

scip-php is the first step. It analyzes PHP source code and produces `index.json` (unified JSON with SCIP index + call graph + value tracking).

## Installation

### Docker (recommended)

```bash
./build/build.sh   # builds scip-php Docker image
```

### Via Composer

```bash
composer require --dev kloc/scip-php
```

Requires PHP 8.3+.

## Usage

```bash
# Standard indexing (stable call kinds only)
./bin/scip-php.sh -d /path/to/project -o /path/to/output

# With experimental call kinds (function calls, array access, operators)
./bin/scip-php.sh -d /path/to/project -o /path/to/output --experimental
```

### Output Files

| File | Description |
|------|-------------|
| `index.scip` | Standard SCIP protobuf index (compatible with Sourcegraph) |
| `index.json` | Unified JSON output: SCIP + calls + values (v4.0) |

### Options

```
-d, --project-dir=PATH    Project directory (default: current)
-o, --output=PATH         Output directory (default: current)
-c, --composer=PATH       Custom composer.json path
    --config=PATH         Custom scip-php.json config
    --experimental        Enable experimental call kinds
    --memory-limit=SIZE   Memory limit (default: 1G)
```

## Unified JSON Format (v4.0)

The `index.json` file contains all indexer output in a single file:

```json
{
  "version": "4.0",
  "scip": {
    "metadata": { ... },
    "documents": [ ... ]
  },
  "calls": {
    "values": [
      {"id": "file:line:col", "kind": "parameter", "symbol": "...", "type": "..."}
    ],
    "calls": [
      {"id": "file:line:col", "kind": "method", "callee": "...", "receiver_value_id": "..."}
    ]
  }
}
```

### Call Kinds

**Stable** (always generated):
- `access` -- Property access (`$obj->property`)
- `method` -- Method call (`$obj->method()`)
- `constructor` -- Object instantiation (`new Foo()`)
- `access_static` -- Static property (`Foo::$prop`)
- `method_static` -- Static method (`Foo::method()`)

**Experimental** (require `--experimental`):
- `function` -- Function call (`sprintf()`)
- `access_array` -- Array access (`$arr['key']`)
- `coalesce` -- Null coalesce (`$a ?? $b`)
- `ternary` / `ternary_full` -- Ternary operators
- `match` -- Match expressions

## Configuration

Optional `scip-php.json` to treat external packages as internal (full indexing):

```json
{
    "internal_packages": ["symfony/console"],
    "internal_classes": ["Symfony\\Component\\Console\\Command\\Command"]
}
```

## Extensions Beyond Original scip-php

- **Call site tracking** -- Full call site and data flow analysis
- **Value tracking** -- Parameters, locals, results with type info
- **Chain reconstruction** -- Follow `receiver_value_id` through call chains
- **Argument binding** -- Track which values are passed to which parameters
- **Unified JSON output** -- Single file containing SCIP index + calls + values
- Method override relationships (bidirectional)
- Extends vs implements distinction
- Standalone CLI with Docker support

## Development

All development uses Docker:

```bash
# Build dev image
docker build -t scip-php-dev -f Dockerfile.dev .

# Run tests
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpunit --no-coverage

# Run linting
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpcs
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpstan --memory-limit=2G
```

## License

MIT
