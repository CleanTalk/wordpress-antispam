#!/usr/bin/env bash
set -Eeuo pipefail
trap 'echo "::error::Command failed on line ${LINENO}: ${BASH_COMMAND}"' ERR

PLUGIN_FILE="${INPUT_PLUGIN_FILE}"
README_FILE="${INPUT_README_FILE}"
CHANGELOG_FILE="${INPUT_CHANGELOG_FILE:-changelog.txt}"
MATRIX_SERVER="${INPUT_MATRIX_SERVER:-}"
MATRIX_ROOM="${INPUT_MATRIX_ROOM:-}"
MATRIX_TOKEN="${INPUT_MATRIX_TOKEN:-}"
TARGET_BRANCH="${INPUT_TARGET_BRANCH:-beta}"
AUTO_COMMIT_MESSAGE="${INPUT_AUTO_COMMIT_MESSAGE:-}"
BASE_REF="${INPUT_BASE_REF:-origin/master}"
DATE_FORMAT="${INPUT_DATE_FORMAT:-%b %d %Y}"
export COMMIT_PREFIXES="${COMMIT_PREFIXES:-Fix|Upd|New|Code}"
export TASK_URL_PREFIX="${TASK_URL_PREFIX:-https://app.doboard.com/1/task/}"
SCRIPT_DIR="${GITHUB_ACTION_PATH:-$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)}"

TMP_DIR="$(mktemp -d)"
SUMMARY_FILE="${GITHUB_STEP_SUMMARY:-${TMP_DIR}/summary.md}"
ENTRIES_FILE="${TMP_DIR}/entries.txt"
BLOCK_FILE="${TMP_DIR}/block.txt"
SKIPPED_FILE="${TMP_DIR}/skipped.txt"
: > "$SKIPPED_FILE"
MATRIX_LOG_TXT="${TMP_DIR}/matrix.txt"
MATRIX_LOG_HTML="${TMP_DIR}/matrix.html"
: > "$MATRIX_LOG_TXT"
: > "$MATRIX_LOG_HTML"
trap 'rm -rf "${TMP_DIR}"' EXIT

notice() { echo "::notice::$1"; }
warn() { echo "::warning::$1"; }
error_annot() { echo "::error::$1"; }
append_summary() { printf '%s\n' "$1" >> "$SUMMARY_FILE"; }

escape_html_file() {
  python3 "$SCRIPT_DIR/utils.py" escape-file "$1"
}

escape_html_text() {
  python3 "$SCRIPT_DIR/utils.py" escape-text "$1"
}

send_matrix() {
  local body="$1"
  local formatted="${2:-}"
  [[ -n "$MATRIX_SERVER" && -n "$MATRIX_ROOM" && -n "$MATRIX_TOKEN" ]] || return 0
  python3 "$SCRIPT_DIR/matrix_send.py" "$MATRIX_SERVER" "$MATRIX_ROOM" "$MATRIX_TOKEN" "$body" "$formatted"
}

flush_matrix() {
  local header="$1"
  local body_txt formatted
  body_txt="${header}"$'\n\n'"$(cat "$MATRIX_LOG_TXT")"
  formatted="<strong>${header}</strong><br><ul>$(cat "$MATRIX_LOG_HTML")</ul>"
  send_matrix "$body_txt" "$formatted" || true
}

fail() {
  local msg="$1"
  warn "$msg"
  append_summary "## Warning"
  append_summary "- $msg"
  printf 'SKIP: %s\n' "$msg" >> "$MATRIX_LOG_TXT"
  printf '<li><strong>SKIP</strong>: %s</li>\n' "$(escape_html_text "$msg")" >> "$MATRIX_LOG_HTML"
  return 1
}

require_file() {
  [[ -f "$1" ]] || { fail "Required file not found: $1"; return 1; }
}

trim() {
  local s="$1"
  s="${s#${s%%[![:space:]]*}}"
  s="${s%${s##*[![:space:]]}}"
  printf '%s' "$s"
}

current_date() {
  date "+${DATE_FORMAT}"
}

get_plugin_version() {
  local version
  version="$(sed -nE 's/^\s*Version:\s*([0-9]+(\.[0-9]+)*)\s*$/\1/p' "$PLUGIN_FILE" | head -n1)"
  [[ -n "$version" ]] || { fail "Could not read Version from $PLUGIN_FILE"; return 1; }
  printf '%s' "$version"
}

get_stable_tag() {
  local stable
  stable="$(sed -nE 's/^Stable tag:[[:space:]]*([0-9]+(\.[0-9]+)*)[[:space:]]*$/\1/p' "$README_FILE" | head -n1)"
  [[ -n "$stable" ]] || { fail "Could not read Stable tag from $README_FILE"; return 1; }
  printf '%s' "$stable"
}

find_version_range() {
  local current_version="$1"

  mapfile -t commits < <(git log --format='%H' -- "$PLUGIN_FILE")
  [[ ${#commits[@]} -gt 0 ]] || { fail "No git history found for $PLUGIN_FILE"; return 1; }

  local current_commit=""
  local previous_version=""
  local previous_commit=""
  local commit version
  local seen_current=0
  local seen_previous=0

  for commit in "${commits[@]}"; do
    version="$(git show "${commit}:${PLUGIN_FILE}" 2>/dev/null \
      | sed -nE 's/^[[:space:]]*Version:[[:space:]]*([0-9]+(\.[0-9]+)*)[[:space:]]*$/\1/p' \
      | head -n1)"

    [[ -n "$version" ]] || continue

    if [[ "$seen_current" -eq 0 && "$version" == "$current_version" ]]; then
      current_commit="$commit"
      seen_current=1
      continue
    fi

    if [[ "$seen_current" -eq 1 && -z "$previous_version" && "$version" != "$current_version" ]]; then
      previous_version="$version"
      previous_commit="$commit"
      seen_previous=1
      continue
    fi

    if [[ "$seen_previous" -eq 1 && "$version" == "$previous_version" ]]; then
      previous_commit="$commit"
      continue
    fi

    if [[ "$seen_previous" -eq 1 && "$version" != "$previous_version" ]]; then
      break
    fi
  done

  [[ -n "$current_commit" ]] || { fail "Could not find commit for current version $current_version"; return 1; }
  [[ -n "$previous_version" ]] || { fail "Could not determine previous version before $current_version"; return 1; }
  [[ -n "$previous_commit" ]] || { fail "Could not find commit for previous version $previous_version"; return 1; }

  printf '%s\t%s\t%s\n' "$previous_commit" "$current_commit" "$previous_version"
}

collect_release_entries() {
  local previous_commit="$1"

  : > "$ENTRIES_FILE"

  local found=0
  local sha subject body parsed task kind text line

  mapfile -t shas < <(git log --no-merges --format='%H' "${BASE_REF}..origin/${TARGET_BRANCH}")

  for sha in "${shas[@]}"; do
    [[ -n "$sha" ]] || continue
    subject="$(git log -1 --format='%s' "$sha")"
    body="$(git log -1 --format='%B' "$sha")"

    parsed="$(printf '%s\x1f%s' "$subject" "$body" | python3 "$SCRIPT_DIR/utils.py" parse-message || true)"
    if [[ -z "$parsed" ]]; then
      notice "Skipping commit $sha: subject does not match ${COMMIT_PREFIXES} prefixes or has no task link ($subject)"
      printf '%s\t%s\n' "$sha" "$subject" >> "$SKIPPED_FILE"
      continue
    fi

    IFS=$'\t' read -r kind text task <<< "$parsed"
    text="$(trim "$text")"
    line="${text}"
    printf '%s\n' "$line" >> "$ENTRIES_FILE"
    found=1
  done

  [[ "$found" -eq 1 ]] || { fail "No valid release commits found between previous version boundary and HEAD"; return 1; }
}

build_changelog_block() {
  local version="$1"
  local when="$2"

  {
    printf '= %s %s =\n' "$version" "$when"
    while IFS= read -r entry; do
      printf '* %s\n' "$entry"
    done < "$ENTRIES_FILE"
  } > "$BLOCK_FILE"
}

rewrite_readme() {
  local version="$1"
  python3 "$SCRIPT_DIR/changelog_rewrite.py" --mode readme --file "$README_FILE" --block-file "$BLOCK_FILE" --version "$version"
}

rewrite_changelog_file() {
  local version="$1"
  python3 "$SCRIPT_DIR/changelog_rewrite.py" --mode changelog --file "$CHANGELOG_FILE" --block-file "$BLOCK_FILE" --version "$version"
}

append_job_summary() {
  local version="$1"
  local stable="$2"
  local previous_commit="$3"
  local current_commit="$4"
  local previous_version="$5"

  append_summary "# Make changelog"
  append_summary ""
  append_summary "## Validation"
  append_summary "- Plugin version: ${version}"
  append_summary "- Stable tag at workflow start: ${stable}"
  append_summary "- Previous version: ${previous_version}"
  append_summary "- Previous version commit: ${previous_commit}"
  append_summary "- Current version commit: ${current_commit}"
  append_summary "- Changes committed and pushed to ${TARGET_BRANCH}."
  append_summary "- Current version commit: ${current_commit}"
  append_summary ""
  append_summary "## Entries"
  while IFS= read -r entry; do
    append_summary "- ${entry}"
  done < "$ENTRIES_FILE"
  append_summary ""
  append_summary "## Changelog block"
  append_summary '```text'
  cat "$BLOCK_FILE" >> "$SUMMARY_FILE"
  append_summary '```'
  append_summary ""

  if [[ -s "$SKIPPED_FILE" ]]; then
    append_summary "## Skipped commits"
    append_summary "Commits in ${BASE_REF}..origin/${TARGET_BRANCH} that did not match ${COMMIT_PREFIXES} prefixes or a ${TASK_URL_PREFIX} link:"
    append_summary ""
    append_summary '| SHA | Subject |'
    append_summary '|-----|---------|'
    while IFS=$'\t' read -r sha subj; do
      subj="${subj//|/\\|}"
      append_summary "| \`${sha:0:12}\` | ${subj} |"
    done < "$SKIPPED_FILE"
    append_summary ""
  fi
}

send_stage_notice() {
  local stage="$1"
  local text="$2"
  notice "$stage: $text"
  printf '%s: %s\n' "$stage" "$text" >> "$MATRIX_LOG_TXT"
  printf '<li><strong>%s</strong>: %s</li>\n' "$stage" "$(escape_html_text "$text")" >> "$MATRIX_LOG_HTML"
}

append_matrix_block() {
  local title="$1"
  local file="$2"
  printf '%s:\n%s\n' "$title" "$(cat "$file")" >> "$MATRIX_LOG_TXT"
  printf '<li><strong>%s</strong>:<pre>%s</pre></li>\n' "$title" "$(escape_html_file "$file")" >> "$MATRIX_LOG_HTML"
}

commit_and_push() {
  local version="$1"
  local message="${AUTO_COMMIT_MESSAGE:-chore(changelog): rebuild readme for v${version} [skip ci]}"

  if git diff --quiet -- "$README_FILE" "$CHANGELOG_FILE"; then
    warn "No changelog file changes detected after rebuild"
    append_summary "## Commit"
    append_summary "- No changes to commit."
    return 0
  fi

  git config user.name 'github-actions[bot]'
  git config user.email 'github-actions[bot]@users.noreply.github.com'
  git add "$README_FILE" "$CHANGELOG_FILE"
  git commit -m "$message"
  git push origin "HEAD:${TARGET_BRANCH}"

  append_summary "## Commit"
  append_summary "- Changes committed and pushed to ${TARGET_BRANCH}."
}

run_changelog() {
  require_file "$PLUGIN_FILE" || return 1
  require_file "$README_FILE" || return 1
  require_file "$CHANGELOG_FILE" || return 1

  local version stable previous_commit current_commit previous_version date_now range_info

  version="$(get_plugin_version)" || return 1
  stable="$(get_stable_tag)" || return 1

  send_stage_notice "Parsing" "Detected plugin version ${version}."

  if [[ "$stable" == "$version" ]]; then
    notice "Stable tag already matches plugin version ${version}. Continuing."
    append_summary "## Stable tag"
    append_summary "- Stable tag at workflow start: ${stable}."
    append_summary "- Stable tag already matches plugin version."
    append_summary ""
  else
    warn "Stable tag ${stable} does not match plugin version ${version}. It will be updated."
    append_summary "## Stable tag"
    append_summary "- Stable tag at workflow start: ${stable}."
    append_summary "- Plugin version: ${version}."
    append_summary "- Stable tag will be updated during readme rewrite."
    append_summary ""
  fi

  range_info="$(find_version_range "$version")" || return 1
  IFS=$'\t' read -r previous_commit current_commit previous_version <<< "$range_info"

  send_stage_notice "Validation" "Using range from version ${previous_version} (${previous_commit}) to HEAD for current version ${version}."
  collect_release_entries "$previous_commit" || return 1

  date_now="$(current_date)"
  build_changelog_block "$version" "$date_now"
  send_stage_notice "Changelog" "Built current version block with $(wc -l < "$ENTRIES_FILE" | tr -d ' ') entries."

  rewrite_readme "$version"
  send_stage_notice "Readme" "Updated Stable tag and current version changelog block in ${README_FILE}."

  rewrite_changelog_file "$version"
  send_stage_notice "Changelog file" "Updated current version changelog block in ${CHANGELOG_FILE}."

  append_job_summary "$version" "$stable" "$previous_commit" "$current_commit" "$previous_version"

  append_matrix_block "Make changelog entries for v${version}" "$BLOCK_FILE"

  commit_and_push "$version"
  send_stage_notice "Success" "Changelog rebuilt successfully for v${version}."
}

main() {
  notice "Matrix configured: server=${MATRIX_SERVER:+yes}, room=${MATRIX_ROOM:+yes}, token=${MATRIX_TOKEN:+yes}"
  send_stage_notice "Start" "Workflow started for branch ${TARGET_BRANCH}, main file is ${PLUGIN_FILE}."

  if run_changelog; then
    flush_matrix "Make changelog completed"
    exit 0
  fi

  warn "Changelog build was skipped due to errors (see summary). Continuing without failing the job."
  append_summary "## Result"
  append_summary "- Changelog build was skipped due to errors (see warnings above)."
  append_summary "- The workflow will continue so that the release asset can still be built."
  send_stage_notice "Skipped" "Changelog build was skipped due to errors. Downstream jobs will continue."
  flush_matrix "Make changelog skipped"
  exit 0
}

main "$@"
