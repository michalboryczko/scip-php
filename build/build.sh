#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

IMAGE_NAME="scip-php"

echo "Building scip-php Docker image..."

cd "$PROJECT_DIR"
docker build -t "$IMAGE_NAME" .

echo ""
echo "Done! Docker image: $IMAGE_NAME"
echo ""
echo "Usage: ./bin/scip-php.sh -d /path/to/project -o /path/to/output"
