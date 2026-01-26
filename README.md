# scip-php

SCIP indexer for PHP. Fork of [davidrjenni/scip-php](https://github.com/davidrjenni/scip-php) with standalone CLI and improved relationship handling.

## Installation

### Static Binary (recommended)

Download from [releases](https://github.com/kloc-dev/scip-php/releases) or build:

```bash
./build/build.sh  # requires Docker
# Output: build/scip-php
```

### Via Composer

```bash
composer require --dev kloc/scip-php
```

## Usage

```bash
# Index project
./scip-php -d /path/to/project

# Upload to Sourcegraph
src code-intel upload
```

### Options

```
-d, --project-dir=PATH    Project directory (default: current)
-o, --output=PATH         Output file (default: index.scip)
-c, --composer=PATH       Custom composer.json path
    --config=PATH         Custom scip-php.json config
    --memory-limit=SIZE   Memory limit (default: 1G)
```

## Configuration

Optional `scip-php.json` to treat external packages as internal (full indexing):

```json
{
    "internal_packages": ["symfony/console"],
    "internal_classes": ["Symfony\\Component\\Console\\Command\\Command"]
}
```

## Fork Improvements

- Standalone CLI with custom paths support
- Static binary build (no PHP required)
- Method override relationships (bidirectional references)
- Extends vs implements distinction
- Type definition relationships for properties/parameters/returns
- Foreach loop variable type tracking

## License

MIT - see [LICENSE](LICENSE)
