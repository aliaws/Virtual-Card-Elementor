#!/bin/bash

# Define the name of the zip file
ZIP_NAME="virtual-card-elementor.zip"
rm -rf ZIP_NAME

# Find and zip all files and folders except .git, .idea, and *.zip
zip -r "$ZIP_NAME" . -x "*.git*" "*.idea*" "*.zip" "generate.sh" "package.json" "composer.lock" "composer.json" "webpack.config.js" "node_modules/*" "tmp/*" "download_images.py"
# Output message
echo "Created $ZIP_NAME successfully!"
