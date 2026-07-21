#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${APP_DIR}"

usage() {
  cat <<'EOF'
Usage (from anywhere):
  PROJECT_ID=my-gcp-project ./scripts/migrate-cloud-run.sh [--create] [--yes]

  Or from the monorepo root:
  PROJECT_ID=my-gcp-project ./apps/web/scripts/migrate-cloud-run.sh

Creates/updates the Cloud Run Job `finba-migrate` and optionally executes:
  php artisan migrate --force

Environment:
  PROJECT_ID   (required)
  REGION       (optional, default: southamerica-east1)
  IMAGE        (required unless --create uses SERVICE latest)
  SERVICE      (optional, default: finba) used to resolve image when IMAGE unset
EOF
}

CREATE=0
YES=0

for arg in "$@"; do
  case "$arg" in
    -h|--help)
      usage
      exit 0
      ;;
    --create)
      CREATE=1
      ;;
    --yes|-y)
      YES=1
      ;;
    *)
      echo "Unknown argument: $arg" >&2
      usage
      exit 1
      ;;
  esac
done

PROJECT_ID="${PROJECT_ID:-}"
REGION="${REGION:-southamerica-east1}"
SERVICE="${SERVICE:-finba}"
IMAGE="${IMAGE:-}"

if [[ -z "$PROJECT_ID" ]]; then
  echo "PROJECT_ID is required." >&2
  usage
  exit 1
fi

gcloud config set project "$PROJECT_ID" >/dev/null

if [[ -z "$IMAGE" ]]; then
  IMAGE="$(gcloud run services describe "$SERVICE" \
    --region "$REGION" \
    --format='value(spec.template.spec.containers[0].image)')"
fi

if [[ -z "$IMAGE" ]]; then
  echo "Could not resolve IMAGE. Pass IMAGE=... explicitly." >&2
  exit 1
fi

echo "Project: $PROJECT_ID"
echo "Region:  $REGION"
echo "Image:   $IMAGE"
echo "Job:     finba-migrate"

if [[ "$CREATE" -eq 1 ]] || ! gcloud run jobs describe finba-migrate --region "$REGION" >/dev/null 2>&1; then
  echo "Creating Cloud Run Job finba-migrate..."
  gcloud run jobs create finba-migrate \
    --image "$IMAGE" \
    --region "$REGION" \
    --command php \
    --args artisan,migrate,--force \
    --task-timeout 15m \
    --max-retries 0 \
    --memory 1Gi \
    --cpu 1
  echo
  echo "IMPORTANT: attach the same DB secrets/env vars to finba-migrate as the web service,"
  echo "then re-run this script without --create to execute migrations."
  exit 0
fi

gcloud run jobs update finba-migrate \
  --image "$IMAGE" \
  --region "$REGION" \
  --command php \
  --args artisan,migrate,--force >/dev/null

if [[ "$YES" -ne 1 ]]; then
  read -r -p "Execute finba-migrate now? [y/N] " reply
  case "$reply" in
    y|Y|yes|YES) ;;
    *)
      echo "Migration not executed."
      exit 0
      ;;
  esac
fi

echo "Executing migrations..."
gcloud run jobs execute finba-migrate \
  --region "$REGION" \
  --wait

echo "Migration job finished."
