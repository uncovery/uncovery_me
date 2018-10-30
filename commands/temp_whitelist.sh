USERNAME="uncovery"

ME="whoami"
as_user() {
  if [ "$ME" == "$USERNAME" ] ; then
    bash -c "$1"
  else
    su - $USERNAME -c "$1"
  fi
}

as_user "screen -p 0 -S temp -X eval 'stuff \"whitelist reload\"\015'"

