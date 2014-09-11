#!/usr/bin/env bash

set -e

if [ -z "$1" ];
then
  echo "No new install will occur without the \"slaughter\" option. See README for NEW details.";
fi

#option
slaughter=$1

#paths
build_path="$( cd "$( dirname "$0" )" && pwd )"
slaughter_path="$( cd "$( dirname "$0" )/slaughter" && pwd )"
top_path="$( cd "../" && pwd )"
env_path="$top_path/.env"

#drush flags
drush_flags="-r $top_path/www"
drush="drush $drush_flags"

#Sanity Checks
echo Build path is $build_path
echo Top path is $top_path
echo Environment config path is $env_path
echo $top_path/www/sites/default/files

# Load environment config 
if [ -e "$top_path/.env" ]
then
  echo "Loading config"
  source "$top_path/.env"
fi

if [ -n "$slaughter" ] && [ "$slaughter" == "slaughter" ]
then
  echo "Slaughter option is $slaughter"
  echo "Building with slaughter option"
  source "$env_path"
  echo "Seed is $DROPSHIP_SEEDS"
  echo "Preprocess CSS is $PREPROCESS_CSS"
  echo "Preprocess JS is $PREPROCESS_JS"
  source "$slaughter_path/slaughter.sh"
else
  echo no slaughter in progress \(aren\'t you glad?\)
fi

$drush kw-manifests
echo "Clearing caches.";
$drush cc all -y
echo "Reverting all features.";
$drush fra -y
echo "Running any updates.";
$drush updb -y
#echo "Setting the theme default.";
#$drush scr $build_path/scripts/default_set_theme.php
echo "Clearing caches one last time.";
$drush cc all -y
