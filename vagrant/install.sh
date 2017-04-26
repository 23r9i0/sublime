#!/bin/bash

# Prevent execution if not vagrant box
[ ! -d /home/vagrant ] && exit

# Create home bin if not exists
[ ! -d "$HOME/bin" ] && mkdir "$HOME/bin"

source_dir="$(dirname $BASH_SOURCE)"
wp_subl_file="${source_dir}/wp_subl"

if [[ -f "${wp_subl_file}" ]]; then
  cp -f "${wp_subl_file}" "$HOME/bin/wp_subl" && \
  chmod 700 /home/vagrant/bin/wp_subl
fi