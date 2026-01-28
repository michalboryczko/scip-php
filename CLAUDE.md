# scip-php Development

SCIP indexer for PHP. All development uses Docker.

## Build

```bash
./build/build.sh  # outputs build/scip-php
```

## Dev Container

```bash
docker build -t scip-php-dev .
docker run --rm -it -v $(pwd):/app --entrypoint sh scip-php-dev
```

## Testing & Linting

```bash
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpunit --no-coverage
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpcs
docker run --rm -v $(pwd):/app scip-php-dev vendor/bin/phpstan --memory-limit=2G
```

## Usage

```bash
./build/scip-php -d /path/to/project -o index.scip
```

Key flags: `-d` project dir, `-o` output file, `-c` composer.json path, `--config` scip-php.json path, `--memory-limit` (default 1G).
