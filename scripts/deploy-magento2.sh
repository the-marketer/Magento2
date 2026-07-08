#!/usr/bin/env bash
set -euo pipefail

BRANCH="${BRANCH:-refactoring}"
REPO_URL="${REPO_URL:-https://github.com/the-marketer/Magento2.git}"
MAGENTO_ROOT="${MAGENTO_ROOT:-/var/www/html}"
PHP_BIN="${PHP_BIN:-php -d memory_limit=-1}"
SKIP_BUILD=0
SKIP_COMPILE=0
SKIP_STATIC=0
BRANCH_FROM_ARGS=0

reexec_from_temp() {
  if [[ "${DEPLOY_SCRIPT_REEXECUTED:-0}" == "1" ]]; then
    return
  fi

  local source_path="${BASH_SOURCE[0]}"
  if [[ -f "$source_path" ]]; then
    local tmp_path
    tmp_path="$(mktemp /tmp/deploy-magento2.XXXXXX)"
    cp "$source_path" "$tmp_path"
    chmod +x "$tmp_path"
    DEPLOY_SCRIPT_REEXECUTED=1 exec "$tmp_path" "$@"
  fi
}

usage() {
  cat <<'USAGE'
Usage:
  scripts/deploy-magento2.sh <branch> [--no-build] [--no-compile] [--no-static]
  scripts/deploy-magento2.sh --branch <branch> [--no-build] [--no-compile] [--no-static]

Run this inside the Magento Docker container. The script attaches the Magento
root to the Git repository if needed, fetches the requested branch, resets the
owned Mktr files to that branch, then runs the Magento build.

Environment overrides:
  BRANCH        Git branch to deploy. Default: refactoring
  REPO_URL      Git repository URL. Default: https://github.com/the-marketer/Magento2.git
  MAGENTO_ROOT  Magento root inside Docker. Default: /var/www/html
  PHP_BIN       PHP command. Default: php -d memory_limit=-1

Options:
  -b, --branch  Branch to deploy.
  --no-build    Pull the code only; skip Magento build commands.
  --sync-only   Alias for --no-build, kept for older notes.
  --no-compile  Skip setup:di:compile; useful on low-memory containers.
  --no-static   Skip setup:static-content:deploy; useful when assets already exist.
USAGE
}

reexec_from_temp "$@"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --branch|-b)
      if [[ -z "${2:-}" ]]; then
        echo "Missing value for $1" >&2
        exit 2
      fi
      BRANCH="$2"
      BRANCH_FROM_ARGS=1
      shift 2
      ;;
    --no-build|--sync-only)
      SKIP_BUILD=1
      shift
      ;;
    --no-compile)
      SKIP_COMPILE=1
      shift
      ;;
    --no-static)
      SKIP_STATIC=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    -*)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 2
      ;;
    *)
      if [[ "$BRANCH_FROM_ARGS" -eq 1 ]]; then
        echo "Unexpected argument: $1" >&2
        usage >&2
        exit 2
      fi
      BRANCH="$1"
      BRANCH_FROM_ARGS=1
      shift
      ;;
  esac
done

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command inside container: $1" >&2
    exit 1
  fi
}

validate_inputs() {
  if [[ -z "$BRANCH" ]]; then
    echo "Branch cannot be empty." >&2
    exit 2
  fi

  if [[ "$BRANCH" == -* ]]; then
    echo "Branch cannot start with '-': $BRANCH" >&2
    exit 2
  fi

  if [[ -z "$MAGENTO_ROOT" || "$MAGENTO_ROOT" == "/" ]]; then
    echo "Refusing to deploy with unsafe MAGENTO_ROOT: $MAGENTO_ROOT" >&2
    exit 1
  fi

  if [[ ! -d "$MAGENTO_ROOT" ]]; then
    echo "Magento root does not exist: $MAGENTO_ROOT" >&2
    exit 1
  fi

  if [[ ! -f "$MAGENTO_ROOT/bin/magento" ]]; then
    echo "Magento CLI was not found at $MAGENTO_ROOT/bin/magento" >&2
    exit 1
  fi
}

configure_sparse_checkout() {
  git config --local core.sparseCheckout true
  mkdir -p .git/info
  cat > .git/info/sparse-checkout <<'SPARSE'
/.gitignore
/mktr.sh
/scripts/
/app/code/Mktr/Tracker/
/app/code/Mktr/Google/
/pub/media/logo/
SPARSE
}

prepare_git_repo() {
  cd "$MAGENTO_ROOT"

  if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    local repo_root
    repo_root="$(git rev-parse --show-toplevel)"
    if [[ "$repo_root" != "$MAGENTO_ROOT" ]]; then
      echo "Refusing to deploy: $MAGENTO_ROOT is inside another Git repository: $repo_root" >&2
      exit 1
    fi
  else
    git init
  fi

  if git remote get-url origin >/dev/null 2>&1; then
    git remote set-url origin "$REPO_URL"
  else
    git remote add origin "$REPO_URL"
  fi

  configure_sparse_checkout
}

pull_branch() {
  cd "$MAGENTO_ROOT"

  echo "Fetching branch '$BRANCH' from $REPO_URL"
  if ! git fetch --prune origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"; then
    echo "Could not fetch branch '$BRANCH'. Make sure it exists on origin and was pushed." >&2
    exit 1
  fi

  git checkout --detach >/dev/null 2>&1 || true
  git reset --hard "origin/$BRANCH"
  git checkout -B "$BRANCH"
  git branch --set-upstream-to="origin/$BRANCH" "$BRANCH" >/dev/null 2>&1 || true

  git clean -ffdx -- \
    app/code/Mktr/Tracker \
    app/code/Mktr/Google \
    mktr.sh \
    scripts \
    pub/media/logo

  echo "Deployed Git commit: $(git rev-parse --short HEAD)"
}

magento() {
  cd "$MAGENTO_ROOT"
  $PHP_BIN -f bin/magento "$@"
}

setup_upgrade_without_elasticsearch() {
  cd "$MAGENTO_ROOT"

  local file="app/code/Magento/Elasticsearch/Setup/Validator.php"
  local bak="/tmp/Validator.php.deploy.$(date +%s).bak"

  if [[ ! -f "$file" ]]; then
    magento setup:upgrade
    return
  fi

  cp -a "$file" "$bak"
  restore_validator() {
    cp -a "$bak" "$file"
    rm -f "$bak"
  }
  trap restore_validator EXIT

  php <<'PHP'
<?php
$file = 'app/code/Magento/Elasticsearch/Setup/Validator.php';
$source = file_get_contents($file);
$needle = "    public function validate(): array\n    {\n";
if (strpos($source, 'deploy temporary bypass') === false) {
    $source = str_replace(
        $needle,
        $needle . "        return []; // deploy temporary bypass for setup:upgrade without Elasticsearch\n",
        $source
    );
    file_put_contents($file, $source);
}
PHP

  local upgrade_log="/tmp/setup-upgrade.deploy.$(date +%s).log"
  set +e
  magento setup:upgrade 2>&1 | tee "$upgrade_log"
  local upgrade_status="${PIPESTATUS[0]}"
  set -e

  if [[ "$upgrade_status" -ne 0 ]]; then
    if grep -q 'Could not ping search engine' "$upgrade_log" \
      && magento setup:db:status 2>&1 | tee -a "$upgrade_log" | grep -q 'All modules are up to date'; then
      echo "Continuing after Elasticsearch ping failure; Magento DB is up to date."
    else
      return "$upgrade_status"
    fi
  fi

  rm -f "$upgrade_log"
  restore_validator
  trap - EXIT
}

publish_static_version_dir() {
  cd "$MAGENTO_ROOT"

  local version
  version="$(tr -d '[:space:]' < pub/static/deployed_version.txt)"
  local target="pub/static/version${version}"

  if [[ -z "$version" ]]; then
    echo "Missing pub/static/deployed_version.txt value" >&2
    return 1
  fi

  rm -rf "$target"
  mkdir -p "$target"
  cp -a pub/static/adminhtml "$target/"
  cp -a pub/static/frontend "$target/"
  echo "Published static assets to $target"
}

run_di_compile() {
  set +e
  magento setup:di:compile
  local compile_status="$?"
  set -e

  if [[ "$compile_status" -eq 137 ]]; then
    cat >&2 <<EOF
setup:di:compile was killed by the container, most likely because it ran out of memory.
Retry with:
  scripts/deploy-magento2.sh $BRANCH --no-compile
EOF
  fi

  return "$compile_status"
}

run_static_deploy() {
  set +e
  magento setup:static-content:deploy -f
  local static_status="$?"
  set -e

  if [[ "$static_status" -eq 137 ]]; then
    cat >&2 <<EOF
setup:static-content:deploy was killed by the container, most likely because it ran out of memory.
Retry with:
  scripts/deploy-magento2.sh $BRANCH --no-compile --no-static
EOF
  fi

  return "$static_status"
}

run_build() {
  magento module:enable --clear-static-content Mktr_Tracker Mktr_Google
  setup_upgrade_without_elasticsearch
  if [[ "$SKIP_COMPILE" -eq 1 ]]; then
    echo "Skipping setup:di:compile because --no-compile was passed."
  else
    run_di_compile
  fi
  magento cache:flush
  if [[ "$SKIP_STATIC" -eq 1 ]]; then
    echo "Skipping setup:static-content:deploy because --no-static was passed."
  else
    run_static_deploy
    publish_static_version_dir
  fi
  magento cache:clean
  chmod -R 777 "$MAGENTO_ROOT"
  magento setup:db:status
}

require_command git
require_command php
validate_inputs
prepare_git_repo
pull_branch

if [[ "$SKIP_BUILD" -eq 1 ]]; then
  echo "Git update complete. Magento build commands were skipped."
  exit 0
fi

run_build
