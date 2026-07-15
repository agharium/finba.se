#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  PROJECT_ID=my-gcp-project ./scripts/deploy-cloud-run.sh [--allow-dirty] [--skip-build] [--update-migrate-job]

Environment:
  PROJECT_ID   (required) GCP project id
  REGION       (optional, default: southamerica-east1)
  SERVICE      (optional, default: finba)
  REPOSITORY   (optional, default: finba)
  APP_VERSION  (optional, default: 0.1.0-beta)
  IMAGE_TAG    (optional, default: short git sha)
EOF
}

ALLOW_DIRTY=0
SKIP_BUILD=0
UPDATE_MIGRATE_JOB=0

for arg in "$@"; do
  case "$arg" in
    -h|--help)
      usage
      exit 0
      ;;
    --allow-dirty)
      ALLOW_DIRTY=1
      ;;
    --skip-build)
      SKIP_BUILD=1
      ;;
    --update-migrate-job)
      UPDATE_MIGRATE_JOB=1
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
REPOSITORY="${REPOSITORY:-finba}"
APP_VERSION="${APP_VERSION:-0.1.0-beta}"

if [[ -z "$PROJECT_ID" ]]; then
  echo "PROJECT_ID is required." >&2
  usage
  exit 1
fi

if [[ "$ALLOW_DIRTY" -ne 1 ]]; then
  if ! git diff --quiet || ! git diff --cached --quiet || [[ -n "$(git ls-files --others --exclude-standard)" ]]; then
    echo "Git working tree is dirty. Commit/stash changes or pass --allow-dirty." >&2
    exit 1
  fi
fi

GIT_SHA="$(git rev-parse --short HEAD)"
APP_BUILD="$(date -u +%Y%m%d%H%M%S)"
IMAGE_TAG="${IMAGE_TAG:-$GIT_SHA}"
IMAGE="${REGION}-docker.pkg.dev/${PROJECT_ID}/${REPOSITORY}/app:${IMAGE_TAG}"

echo "Project:   $PROJECT_ID"
echo "Region:    $REGION"
echo "Service:   $SERVICE"
echo "Image:     $IMAGE"
echo "APP_VERSION=$APP_VERSION"
echo "APP_BUILD=$APP_BUILD"
echo "GIT_SHA=$GIT_SHA"

gcloud config set project "$PROJECT_ID" >/dev/null

if [[ "$SKIP_BUILD" -ne 1 ]]; then
  gcloud builds submit \
    --tag "$IMAGE" \
    --project "$PROJECT_ID"
fi

echo "Deploying Cloud Run service (secrets/env must already be configured on the service)..."

gcloud run deploy "$SERVICE" \
  --image "$IMAGE" \
  --region "$REGION" \
  --platform managed \
  --allow-unauthenticated \
  --port 8080 \
  --memory 1Gi \
  --cpu 1 \
  --min-instances 0 \
  --max-instances 3 \
  --concurrency 40 \
  --timeout 60 \
  --update-env-vars "APP_ENV=production,APP_DEBUG=false,APP_URL=https://app.finba.se,LOG_CHANNEL=stderr,LOG_LEVEL=info,FINBA_STORAGE_DISK=finba,QUEUE_CONNECTION=sync,SESSION_DRIVER=database,CACHE_STORE=database,APP_VERSION=${APP_VERSION},APP_BUILD=${APP_BUILD},GIT_SHA=${GIT_SHA}"

if [[ "$UPDATE_MIGRATE_JOB" -eq 1 ]]; then
  if gcloud run jobs describe finba-migrate --region "$REGION" >/dev/null 2>&1; then
    gcloud run jobs update finba-migrate \
      --image "$IMAGE" \
      --region "$REGION"
  else
    echo "Migration job finba-migrate does not exist yet. Create it with scripts/migrate-cloud-run.sh --create"
  fi
fi

echo
echo "Deploy submitted."
echo "Image: $IMAGE"
echo "Run migrations explicitly when ready:"
echo "  PROJECT_ID=$PROJECT_ID ./scripts/migrate-cloud-run.sh"
echo "Smoke-test checklist: docs/beta-smoke-test.md"
