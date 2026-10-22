#!/usr/bin/env bash
# Builds nalu-ai.zip (unpacking to nalu-ai/) from the committed HEAD.
#
#   bin/build-zip.sh          # build the current version
#   bin/build-zip.sh v2.1.0   # also check the tag matches the plugin version
#
# Fails if the version in the plugin header, NALU_AI_VERSION, and readme.txt's
# Stable tag disagree, because WordPress offers updates based on the header.
set -euo pipefail

cd "$(dirname "$0")/.."

header_version=$(sed -nE 's/^ \* Version: +([0-9.]+)$/\1/p' nalu-ai.php)
constant_version=$(sed -nE "s/^const NALU_AI_VERSION = '([0-9.]+)';$/\1/p" nalu-ai.php)
readme_version=$(sed -nE 's/^Stable tag: +([0-9.]+)$/\1/p' readme.txt)

if [[ -z "$header_version" || "$header_version" != "$constant_version" || "$header_version" != "$readme_version" ]]; then
  echo "Version mismatch: header=$header_version constant=$constant_version readme=$readme_version" >&2
  exit 1
fi

if [[ $# -gt 0 && "${1#v}" != "$header_version" ]]; then
  echo "Tag $1 does not match plugin version $header_version" >&2
  exit 1
fi

if command -v php >/dev/null; then
  git ls-files '*.php' | xargs -n1 php -l >/dev/null
fi

# .gitattributes export-ignore keeps repo tooling out of the zip.
rm -f nalu-ai.zip
git archive --format=zip --prefix=nalu-ai/ -o nalu-ai.zip HEAD
echo "Built nalu-ai.zip (version $header_version)"
