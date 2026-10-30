#!/usr/bin/env bash
# Test helper: runs setup.sh's detect_os against a fixture os-release file and
# prints what it found, plus the script's own VERSION to prove it was not overwritten.
set -Eeuo pipefail

repo="$1"
os_release="$2"
set --   # sourcing setup.sh would otherwise parse these as installer options

COMMYPANEL_SOURCE_ONLY=1 source "$repo/installer/setup.sh"
COMMYPANEL_OS_RELEASE="$os_release"
export COMMYPANEL_OS_RELEASE

VERSION="0.1.0"
detect_os
printf 'id=%s version_id=%s codename=%s release=%s\n' "$OS_ID" "$OS_VERSION_ID" "$OS_CODENAME" "$VERSION"
