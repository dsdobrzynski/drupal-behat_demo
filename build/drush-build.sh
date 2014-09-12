#!/usr/bin/env bash

set -e

if [ -z "$1" ];
then
  echo "No new install will occur without the \"install\" option. See README for NEW details.";
fi

#option
install=$1

#paths
build_path="$( cd "$( dirname "$0" )" && pwd )"
install_path="$( cd "$( dirname "$0" )/install" && pwd )"
top_path="$( cd "../" && pwd )"
env_path="$top_path/.env"

#drush flags
drush_flags="-r $top_path/www"
drush="drush $drush_flags"

# Load environment config 
if [ -e "$top_path/.env" ]
then
  echo "Loading config"
  source "$top_path/.env"
fi

if [ -n "$install" ] && [ "$install" == "install" ]
then
  echo "Install option is $install"
  echo "Building with install option"
  echo "Seed is $DROPSHIP_SEEDS"
  source "$install_path/install.sh"
else
  echo no install in progress
fi

$drush kw-manifests
echo "Clearing caches.";
$drush cc all -y