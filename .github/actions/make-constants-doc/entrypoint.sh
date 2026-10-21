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

generate_document
git add -- "$OUTPUT_FILE"
if git diff --cached --quiet -- "$OUTPUT_FILE"; then
  echo "Constants reference is up to date."
  exit 0
fi

git config user.name 'github-actions[bot]'
git config user.email 'github-actions[bot]@users.noreply.github.com'
git commit --only -m 'docs: update constants reference [skip ci]' \
  -m 'Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>' -- "$OUTPUT_FILE"

for attempt in 1 2 3; do
  if git push origin "HEAD:${TARGET_BRANCH}"; then
    exit 0
  fi
  if [ "$attempt" -eq 3 ]; then
    echo "::error::Could not push constants reference to ${TARGET_BRANCH}."
    exit 1
  fi
  git fetch origin "$TARGET_BRANCH"
  git rebase "origin/${TARGET_BRANCH}"
  generate_document
  git add -- "$OUTPUT_FILE"
  if ! git diff --cached --quiet -- "$OUTPUT_FILE"; then
    git commit --only -m 'docs: refresh constants reference [skip ci]' \
      -m 'Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>' -- "$OUTPUT_FILE"
  fi
done
