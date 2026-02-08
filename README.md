# scip-php

PHP code indexer that generates SCIP indexes and **unified JSON** output for data flow analysis.


## Features

- **SCIP Index** (`index.scip`) - Standard SCIP format for code navigation
- **Unified JSON** (`index.json`) - Combined SCIP index + call sites + values + data flow in one file (version 4.0)

## Installation

### Docker (recommended)

```bash
./build/build.sh  # builds scip-php Docker image
```

### Via Composer

```bash
composer require --dev kloc/scip-php
```

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
| `index.scip` | Standard SCIP protobuf index |
| `index.json` | Unified JSON output (SCIP + calls + values, version 4.0) |

### Options

```
-d, --project-dir=PATH    Project directory (default: current)
-o, --output=PATH         Output directory (default: current)
-c, --composer=PATH       Custom composer.json path
    --config=PATH         Custom scip-php.json config
    --experimental        Enable experimental call kinds
    --memory-limit=SIZE   Memory limit (default: 1G)
```

## Unified JSON Format (version 4.0)

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
- `access` - Property access (`$obj->property`)
- `method` - Method call (`$obj->method()`)
- `constructor` - Object instantiation (`new Foo()`)
- `access_static` - Static property (`Foo::$prop`)
- `method_static` - Static method (`Foo::method()`)

**Experimental** (require `--experimental`):
- `function` - Function call (`sprintf()`)
- `access_array` - Array access (`$arr['key']`)
- `coalesce` - Null coalesce (`$a ?? $b`)
- `ternary` / `ternary_full` - Ternary operators
- `match` - Match expressions

## Configuration

Optional `scip-php.json` to treat external packages as internal (full indexing):

```json
{
    "internal_packages": ["symfony/console"],
    "internal_classes": ["Symfony\\Component\\Console\\Command\\Command"]
}
```

## Fork Improvements
> **Note**: This is a fork of [davidrjenni/scip-php](https://github.com/davidrjenni/scip-php) with significant extensions for call tracking and data flow analysis. While it still produces standard SCIP indexes compatible with Sourcegraph, the primary focus is on generating unified JSON output for the [kloc](https://github.com/kloc-dev/kloc) code analysis toolkit.

Beyond the original scip-php:

- **Calls tracking** - Full call site and data flow analysis
- **Value tracking** - Parameters, locals, results with type info
- **Chain reconstruction** - Follow `receiver_value_id` through call chains
- **Argument binding** - Track which values are passed to which parameters
- **Unified JSON output** - Single file containing SCIP index + calls + values
- Standalone CLI with Docker support
- Method override relationships (bidirectional)
- Extends vs implements distinction
- Type definition relationships

## Related Projects

- [kloc](https://github.com/kloc-dev/kloc) - Code analysis toolkit using this indexer
- [SCIP](https://github.com/sourcegraph/scip) - Source Code Intelligence Protocol
- [Original scip-php](https://github.com/davidrjenni/scip-php) - Upstream project

## License

MIT - see [LICENSE](LICENSE)
