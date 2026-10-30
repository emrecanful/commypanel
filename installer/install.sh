#!/usr/bin/env bash
# CommyPanel installer: downloads a release, checks its checksum, unpacks it
# and hands over to the setup script inside it.
#
#   curl -fsSL https://github.com/commypanel/commypanel/releases/latest/download/install.sh | sudo bash
#
# Options (all optional):
#   --version X       install version X instead of the latest
#   --upgrade         upgrade an existing installation (also automatic)
#   --panel-port N    port for the panel's web interface (default 8443)
#   --tarball FILE    use a local release tarball instead of downloading
#   --render-only     only write config files below COMMYPANEL_ROOT (testing)
set -Eeuo pipefail

# TODO: set this to the real GitHub repository before publishing.
REPO="${COMMYPANEL_REPO:-commypanel/commypanel}"
PREFIX="/opt/commypanel"

VERSION="latest"
TARBALL=""
PASSTHROUGH=()

die() { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }
log() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }

while [ "$#" -gt 0 ]; do
    case "$1" in
        --version) VERSION="${2:?--version needs a value}"; shift 2 ;;
        --tarball) TARBALL="${2:?--tarball needs a file}"; shift 2 ;;
        --upgrade|--render-only|--no-docker) PASSTHROUGH+=("$1"); shift ;;
        --panel-port) PASSTHROUGH+=("$1" "${2:?--panel-port needs a value}"); shift 2 ;;
        -h|--help) sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) die "Unknown option: $1" ;;
    esac
done

[ "$(id -u)" -eq 0 ] || die "Run this as root, for example: curl -fsSL <url> | sudo bash"
for tool in tar sha256sum; do
    command -v "$tool" >/dev/null 2>&1 || die "$tool is required."
done

WORK="$(mktemp -d)"
trap 'rm -rf "${WORK:?}"' EXIT

if [ -n "$TARBALL" ]; then
    [ -f "$TARBALL" ] || die "Tarball not found: $TARBALL"
    cp "$TARBALL" "$WORK/commypanel.tar.gz"
    if [ -f "$TARBALL.sha256" ]; then
        cp "$TARBALL.sha256" "$WORK/commypanel.tar.gz.sha256"
    else
        log "No $TARBALL.sha256 next to the tarball; skipping the checksum check for this local file."
    fi
else
    command -v curl >/dev/null 2>&1 || die "curl is required."
    if [ "$VERSION" = "latest" ]; then
        base="https://github.com/$REPO/releases/latest/download"
    else
        base="https://github.com/$REPO/releases/download/v${VERSION#v}"
    fi
    log "Downloading CommyPanel ($VERSION)"
    curl -fsSL --proto '=https' --tlsv1.2 -o "$WORK/commypanel.tar.gz" "$base/commypanel.tar.gz" \
        || die "Could not download $base/commypanel.tar.gz"
    curl -fsSL --proto '=https' --tlsv1.2 -o "$WORK/commypanel.tar.gz.sha256" "$base/commypanel.tar.gz.sha256" \
        || die "Could not download the checksum file."
fi

if [ -f "$WORK/commypanel.tar.gz.sha256" ]; then
    log "Verifying the checksum"
    (cd "$WORK" && sha256sum --check --status commypanel.tar.gz.sha256) \
        || die "The checksum does not match. The download is corrupt or has been tampered with; nothing was installed."
fi

log "Unpacking"
mkdir -p "$PREFIX/releases"
incoming="$PREFIX/releases/.incoming-$$"
rm -rf "${incoming:?}"
mkdir -p "$incoming"
tar -xzf "$WORK/commypanel.tar.gz" -C "$incoming" --no-same-owner

release="$(tr -d '[:space:]' < "$incoming/VERSION" 2>/dev/null || true)"
case "$release" in
    ""|*[!A-Za-z0-9._-]*) rm -rf "${incoming:?}"; die "The release has no valid VERSION file." ;;
esac
[ -x "$incoming/installer/setup.sh" ] || { rm -rf "${incoming:?}"; die "The release does not contain installer/setup.sh."; }

rm -rf "${PREFIX:?}/releases/${release:?}"
mv "$incoming" "$PREFIX/releases/$release"

exec "$PREFIX/releases/$release/installer/setup.sh" ${PASSTHROUGH[@]+"${PASSTHROUGH[@]}"}
