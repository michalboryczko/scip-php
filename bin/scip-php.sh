#!/bin/bash
set -e

# scip-php Docker wrapper
# Runs scip-php indexer via Docker with volume mounts

IMAGE_NAME="scip-php"

show_help() {
    echo "scip-php - SCIP indexer for PHP (Docker wrapper)"
    echo ""
    echo "Usage: scip-php.sh -d <project-dir> [-o <output-dir>] [--experimental]"
    echo ""
    echo "Options:"
    echo "  -d, --project-dir=PATH    Project directory to index (required)"
    echo "  -o, --output=PATH         Output directory (default: current directory)"
    echo "  --experimental            Include experimental call kinds (function, access_array, etc.)"
    echo "  -h, --help                Display this help and exit"
    echo ""
    echo "Output files:"
    echo "  index.json    Unified JSON output (SCIP + calls + values, version 4.0)"
    echo ""
    echo "Examples:"
    echo "  scip-php.sh -d /path/to/project"
    echo "  scip-php.sh -d /path/to/project -o /path/to/output"
    echo "  scip-php.sh -d /path/to/project --experimental"
    echo ""
    echo "Note: Build the Docker image first with: ./build/build.sh"
}

# Parse arguments
PROJECT_DIR=""
OUTPUT_DIR="$(pwd)"
EXPERIMENTAL=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        -d|--project-dir)
            PROJECT_DIR="$2"
            shift 2
            ;;
        --project-dir=*)
            PROJECT_DIR="${1#*=}"
            shift
            ;;
        -o|--output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        --output=*)
            OUTPUT_DIR="${1#*=}"
            shift
            ;;
        --experimental)
            EXPERIMENTAL="--experimental"
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Validate project directory
if [[ -z "$PROJECT_DIR" ]]; then
    echo "Error: Project directory is required (-d)"
    echo ""
    show_help
    exit 1
fi

# Resolve to absolute paths
PROJECT_DIR="$(cd "$PROJECT_DIR" && pwd)"

# Ensure output directory exists and resolve to absolute path
if [[ ! -d "$OUTPUT_DIR" ]]; then
    mkdir -p "$OUTPUT_DIR"
fi
OUTPUT_DIR="$(cd "$OUTPUT_DIR" && pwd)"

# Check if Docker image exists
if ! docker image inspect "$IMAGE_NAME" >/dev/null 2>&1; then
    echo "Error: Docker image '$IMAGE_NAME' not found."
    echo "Build it first with: ./build/build.sh"
    exit 1
fi

echo "Indexing: $PROJECT_DIR"
echo "Output:   $OUTPUT_DIR"
if [[ -n "$EXPERIMENTAL" ]]; then
    echo "Mode:     experimental (including all call kinds)"
fi
echo ""

# Run Docker with volume mounts
# /input  - project to index (read-only)
# /output - where output files go
docker run --rm \
    -v "$PROJECT_DIR:/input:ro" \
    -v "$OUTPUT_DIR:/output" \
    "$IMAGE_NAME" \
    -d /input \
    -o /output/index.json \
    $EXPERIMENTAL

echo ""
echo "Done!"
