#!/usr/bin/env bash

site="$1"
target_env="$2"
source_branch="$3"
deployed_tag="$4"
repo_url="$5"
repo_type="$6"

drush @${drush_alias} updb -y --strict=0

if [ "$target_env" != "prod" ]; then
    drush @${drush_alias} cim -y
else
    drush @${drush_alias} cim -y
fi
