#!/bin/bash

# Bumps version based on previous commit message.

# Read the last commit message
LAST_COMMIT_MSG=$(git log -1 --pretty=%B)

# Determine version bump type
VERSION_BUMP="patch"
if [[ "$LAST_COMMIT_MSG" == *"feat:"* ]]; then
  VERSION_BUMP="minor"
fi
if [[ "$LAST_COMMIT_MSG" == *"BREAKING CHANGE"* ]] || [[ "$LAST_COMMIT_MSG" == *"!:"* ]]; then
  VERSION_BUMP="major"
fi

# Get current version
CURRENT_VERSION=$(cat version.txt)

# Increment version
NEW_VERSION=$(python -c "import semver; print(semver.bump_${VERSION_BUMP}('${CURRENT_VERSION}'))")
echo $NEW_VERSION > version.txt

# Temporary file for new changelog content
TEMP_FILE=$(mktemp)

# Format new changelog entry
{
    echo "## $NEW_VERSION - $(date +%Y-%m-%d)"
    echo "$LAST_COMMIT_MSG"
    echo
} > $TEMP_FILE

# Append existing changelog to the new content
cat CHANGELOG.md >> $TEMP_FILE

# Replace old changelog with new content
mv $TEMP_FILE CHANGELOG.md