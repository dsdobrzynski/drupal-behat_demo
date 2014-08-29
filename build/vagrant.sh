#!/bin/bash

project=$1

if [[ ! -f /opt/phantomjs ]]
then
  wget https://bitbucket.org/ariya/phantomjs/downloads/phantomjs-1.9.7-linux-x86_64.tar.bz2 -O - | tar xj -C /tmp
  cp /tmp/phantom*/bin/phantomjs /opt
fi

[[ -z `which firefox` ]] && apt-get update -y && apt-get install -q -y iceweasel || true
/opt/phantomjs --webdriver=8643 &> /dev/null &
