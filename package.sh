#!/usr/bin/env bash

#Script for create the plugin artifact
echo "Release tag: $RELEASE_TAG"

if [[ -z "$RELEASE_TAG" ]]; then
  RELEASE_TAG="1.0.1"
fi

SRC_DIR="."
FILE1="etc/module.xml"
FILE2="composer.json"

sed -i.bkp "s/3.1.1/${RELEASE_TAG}/g" "$SRC_DIR/$FILE1"
sed -i.bkp "s/\"version\": \"3.1.1\"/\"version\": \"${RELEASE_TAG}\"/g" "$SRC_DIR/$FILE2"

PLUGIN_FILE="plugin-transbank-webpay-magento2-$RELEASE_TAG.zip"

zip -FSr $PLUGIN_FILE . -x ./docs/\* ./.git/\* ./.DS_Store* */.DS_Store* ./.editorconfig* ./.gitignore* ./.vscode/\* ./package.sh ./.idea/\* ./.gitattributes ./.travis* ./README.md ./*.zip ./docker-magento2/\* "./$FILE1.bkp" "./$FILE2.bkp"

cp "$SRC_DIR/$FILE1.bkp" "$SRC_DIR/$FILE1"
cp "$SRC_DIR/$FILE2.bkp" "$SRC_DIR/$FILE2"
rm "$SRC_DIR/$FILE1.bkp"
rm "$SRC_DIR/$FILE2.bkp"

echo "Plugin version: $RELEASE_TAG"
echo "Plugin file: $PLUGIN_FILE"
