#!/usr/bin/env bash

site="$1"
target_env="$2"
source_branch="$3"
deployed_tag="$4"
repo_url="$5"
repo_type="$6"
drush_alias=${site}.${target_env}

SECONDS=0
echo "Clearing caches..."
/usr/local/bin/drush @${drush_alias} cr --strict=0
echo "Caches cleared in $SECONDS seconds."
SECONDS=0
echo "Applying DB updates..."
/usr/local/bin/drush @${drush_alias} updb -y --strict=0
echo "DB updates run in $SECONDS seconds."
SECONDS=0
echo "Importing configuration..."
/usr/local/bin/drush @${drush_alias} cim -y --strict=0
echo "Configuration imported in $SECONDS seconds."
