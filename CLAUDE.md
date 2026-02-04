# scip-php Development

SCIP indexer for PHP. All development and usage is Docker-based.

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

This runs scip-php in Docker with:
- `/path/to/project` mounted as `/input` (read-only)
- `/path/to/output` mounted as `/output`

Output files: `index.scip`, `calls.json`, `index.kloc`

### Call Kinds

**Stable (always generated):**
- `access`, `method`, `constructor`, `access_static`, `method_static`

**Experimental (require `--experimental` flag):**
- `function`, `access_array`, `coalesce`, `ternary`, `ternary_full`, `match`

## Documentation

Reference documentation for output format and expected behavior:

| Document | Description |
|----------|-------------|
| [`docs/reference/kloc-scip/calls-schema.json`](../docs/reference/kloc-scip/calls-schema.json) | JSON Schema for calls.json - authoritative spec |
| [`docs/reference/kloc-scip/calls-and-data-flow.md`](../docs/reference/kloc-scip/calls-and-data-flow.md) | Detailed explanation with examples |
| [`docs/reference/kloc-scip/calls-schema-docs.md`](../docs/reference/kloc-scip/calls-schema-docs.md) | Quick reference for schema fields |
| [`docs/specs/scip-php-missing-features.md`](../docs/specs/scip-php-missing-features.md) | Features to implement (from contract tests) |

## Contract Tests

Contract tests validate scip-php output against the schema:

```bash
# Run contract tests (from kloc root)
cd ../kloc-reference-project-php/contract-tests
bin/run.sh test

# Run with experimental call kinds
bin/run.sh test --experimental

# Generate test documentation
bin/run.sh docs
```

See [`kloc-reference-project-php/contract-tests/CLAUDE.md`](../kloc-reference-project-php/contract-tests/CLAUDE.md) for details.

## Dev Container (for development/testing)

```bash
docker build -t scip-php .
docker run --rm -it -v $(pwd):/app --entrypoint sh scip-php
```

## Testing & Linting

```bash
# Build dev image first
docker build -t scip-php-dev -f Dockerfile.dev .

# Run tests
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpunit --no-coverage

# Run linting
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpcs
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpstan --memory-limit=2G
```

Or use the existing scip-php-dev image if available:
```bash
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpunit --no-coverage
```
