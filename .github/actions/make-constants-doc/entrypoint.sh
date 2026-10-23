#!/usr/bin/env bash
set -Eeuo pipefail
trap 'echo "::error::Command failed on line ${LINENO}: ${BASH_COMMAND}"' ERR

SOURCE_FILE="${INPUT_SOURCE_FILE:-lib/Cleantalk/ApbctWP/Constant.php}"
OUTPUT_FILE="${INPUT_OUTPUT_FILE:-docs/CONSTANT.MD}"
TARGET_BRANCH="${INPUT_TARGET_BRANCH:-beta}"
SCRIPT_DIR="${GITHUB_ACTION_PATH:-$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)}"

generate_document() {
  python3 "$SCRIPT_DIR/generate.py" --source "$SOURCE_FILE" --output "$OUTPUT_FILE"
}

# Returns 1 when the generated reference has no changes.
# Errors must exit explicitly: errexit is suspended when called from an `if` condition.
commit_document() {
  generate_document || exit 1
  git add -- "$OUTPUT_FILE" || exit 1
  if git diff --cached --quiet -- "$OUTPUT_FILE"; then
    return 1
  fi
  git commit --only -m 'docs: update constants reference [skip ci]' -- "$OUTPUT_FILE" || exit 1
}

git config user.name 'github-actions[bot]'
git config user.email 'github-actions[bot]@users.noreply.github.com'

if ! commit_document; then
  echo "Constants reference is up to date."
  exit 0
fi

for attempt in 1 2 3; do
  if git push origin "HEAD:${TARGET_BRANCH}"; then
    exit 0
  fi
  if [ "$attempt" -eq 3 ]; then
    echo "::error::Could not push constants reference to ${TARGET_BRANCH}."
    exit 1
  fi
  git fetch origin "$TARGET_BRANCH"
  # Rebuild on top of the fresh branch instead of rebasing a stale generated commit.
  git reset --hard "origin/${TARGET_BRANCH}"
  if ! commit_document; then
    echo "Constants reference is up to date."
    exit 0
  fi
done
