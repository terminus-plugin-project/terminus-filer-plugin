#!/usr/bin/env bats

#
# confirm-install.bats
#
# Ensure that Terminus and the Composer plugin have been installed correctly
#

@test "confirm terminus version" {
  terminus --version
}

@test "get help on site:filer command" {
  run terminus help site:filer
  [[ $output == *"Opens the site using an SFTP Client"* ]]
  [ "$status" -eq 0 ]
}