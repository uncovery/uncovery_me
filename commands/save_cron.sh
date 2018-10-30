#!/bin/sh

VOLATILE="/dev/shm/minecraft/"
PERMANENT="/home/minecraft/server/worlds/save/"

#TODO: Check if both directories actually exist, skipped here for clearness
rsync -r -t -v "$VOLATILE" "$PERMANENT"