#!/usr/bin/env bash
# Builds build/pkg_cbmodulecontent-<version>.zip from src/
set -euo pipefail
cd "$(dirname "$0")"
version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' pkg_cbmodulecontent.xml | head -1)
rm -rf build && mkdir -p build/pkg/packages
for ext in lib_cblisthelper mod_cblistmodule plg_content_cbprofile; do
	(cd "src/$ext" && zip -qrX "../../build/pkg/packages/$ext.zip" . -x '.*')
done
cp pkg_cbmodulecontent.xml build/pkg/
(cd build/pkg && zip -qrX "../pkg_cbmodulecontent-$version.zip" .)
rm -rf build/pkg
echo "build/pkg_cbmodulecontent-$version.zip"
