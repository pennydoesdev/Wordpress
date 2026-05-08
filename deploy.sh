#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Vicinity — Deployment Script
# Usage:
#   ./deploy.sh                 Build ZIPs + push to GitHub (main branch)
#   ./deploy.sh --tag           Build ZIPs + push + create a version tag
#   ./deploy.sh --zip-only      Build ZIPs only, do not push
#   ./deploy.sh --dry-run       Print what would happen, do nothing
#
# Prerequisites:
#   - git installed and repo initialized
#   - Remote "origin" pointing to github.com/pennydoesdev/vicinity
#   - Logged in: gh auth login  (or SSH keys set up)
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────
PLUGIN_DIR="plugins/vicinity-plugin"
THEME_DIR="themes/vicinity-theme"
DIST_DIR="dist"
REPO_REMOTE="origin"
BRANCH="main"

# ── Flags ─────────────────────────────────────────────────────────────────────
DO_TAG=false
ZIP_ONLY=false
DRY_RUN=false

for arg in "$@"; do
  case $arg in
    --tag)      DO_TAG=true ;;
    --zip-only) ZIP_ONLY=true ;;
    --dry-run)  DRY_RUN=true ;;
  esac
done

run() {
  if [ "$DRY_RUN" = true ]; then
    echo "[dry-run] $*"
  else
    "$@"
  fi
}

# ── Read versions ─────────────────────────────────────────────────────────────
PLUGIN_VERSION=$(grep "define( 'VICINITY_VERSION'" "$PLUGIN_DIR/vicinity-plugin.php" \
  | grep -oE "'[0-9]+\.[0-9]+\.[0-9]+'" | tr -d "'")

THEME_VERSION=$(grep "^Version:" "$THEME_DIR/style.css" \
  | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")

if [ -z "$PLUGIN_VERSION" ] || [ -z "$THEME_VERSION" ]; then
  echo "❌  Could not read version numbers. Aborting."
  exit 1
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Vicinity Deploy"
echo "  Plugin : v${PLUGIN_VERSION}  (${PLUGIN_DIR})"
echo "  Theme  : v${THEME_VERSION}  (${THEME_DIR})"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── Build ZIPs ────────────────────────────────────────────────────────────────
echo ""
echo "▶  Building packages..."
run mkdir -p "$DIST_DIR"

run bash -c "cd plugins && zip -qr ../\"$DIST_DIR/vicinity-plugin-v${PLUGIN_VERSION}.zip\" \
  vicinity-plugin/ -x '*.DS_Store' '*.git*'"

run bash -c "cd themes && zip -qr ../\"$DIST_DIR/vicinity-theme-v${THEME_VERSION}.zip\" \
  vicinity-theme/ -x '*.DS_Store' '*.git*'"

echo "   ✓ dist/vicinity-plugin-v${PLUGIN_VERSION}.zip"
echo "   ✓ dist/vicinity-theme-v${THEME_VERSION}.zip"

if [ "$ZIP_ONLY" = true ]; then
  echo ""
  echo "✅  Zip-only mode. Done."
  exit 0
fi

# ── Git commit + push ─────────────────────────────────────────────────────────
echo ""
echo "▶  Staging and pushing to GitHub (${BRANCH})..."

run git add plugins/ themes/ .github/ deploy.sh README.md

# Only commit if there are staged changes
if git diff --cached --quiet; then
  echo "   No changes to commit."
else
  COMMIT_MSG="chore: release plugin v${PLUGIN_VERSION} / theme v${THEME_VERSION}"
  run git commit -m "$COMMIT_MSG"
  echo "   ✓ Committed: ${COMMIT_MSG}"
fi

run git push "$REPO_REMOTE" "$BRANCH"
echo "   ✓ Pushed to ${REPO_REMOTE}/${BRANCH}"

# ── Optionally tag ────────────────────────────────────────────────────────────
if [ "$DO_TAG" = true ]; then
  TAG="v${PLUGIN_VERSION}"
  echo ""
  echo "▶  Creating tag ${TAG}..."
  run git tag -a "$TAG" -m "Release ${TAG}: plugin v${PLUGIN_VERSION} / theme v${THEME_VERSION}"
  run git push "$REPO_REMOTE" "$TAG"
  echo "   ✓ Tagged ${TAG} and pushed"
  echo "   → GitHub Actions will auto-attach ZIPs to the release"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅  Deploy complete"
echo "   GitHub   : https://github.com/pennydoesdev/vicinity"
echo "   Actions  : https://github.com/pennydoesdev/vicinity/actions"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"