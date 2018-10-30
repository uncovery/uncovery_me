 #!/bin/bash
 # /etc/init.d/minecraft
 # version 0.3.9 2012-08-13 (YYYY-MM-DD)
 
 ### BEGIN INIT INFO
 # Provides:   minecraft
 # Required-Start: $local_fs $remote_fs screen-cleanup
 # Required-Stop:  $local_fs $remote_fs
 # Should-Start:   $network
 # Should-Stop:    $network
 # Default-Start:  2 3 4 5
 # Default-Stop:   0 1 6
 # Short-Description:    Minecraft server
 # Description:    Starts the minecraft server
 ### END INIT INFO
 
 #Settings
 SERVICE='spigot.jar'
 OPTIONS='nogui'
 USERNAME='uncovery'
 WORLD='city'
 MCPATH='/home/minecraft/server/bukkit'
 MAXHEAP='5G'
 MINHEAP='20G'
 CPU_COUNT=12
 INVOCATION="java -Xmx15G -Xms5G -XX:MaxPermSize=1G -XX:+UseConcMarkSweepGC -XX:+CMSIncrementalPacing -XX:+AggressiveOpts -jar spigot.jar nogui" 
 ME=`whoami`
 as_user() {
   if [ $ME == $USERNAME ] ; then
     bash -c "$1"
   else
     su - $USERNAME -c "$1"
   fi
 }
 
 mc_start() {
   if  pgrep -u $USERNAME -f $SERVICE > /dev/null
   then
     echo "$SERVICE is already running!"
   else
     echo "Replacing spigot.jar with new version:"
     as_user "mv /home/minecraft/server/buildtools/spigot-1.8.8.jar /home/minecraft/server/bukkit/spigot.jar"
     echo "Deleting End contents"
     as_user "rm -R /home/minecraft/server/bukkit/the_end/*"
     echo "Starting $SERVICE..."
     cd $MCPATH
     as_user "cd $MCPATH && screen -dmS minecraft $INVOCATION"
     sleep 7
     if pgrep -u $USERNAME -f $SERVICE > /dev/null
     then
       echo "$SERVICE is now running."
     else
       echo "Error! Could not start $SERVICE!"
     fi
   fi
 }
 
 mc_backup() {
   if pgrep -u $USERNAME -f $SERVICE > /dev/null
   then
     echo "$SERVICE is running... suspending saves"
#     as_user "screen -p 0 -S minecraft -X eval 'stuff \"say SERVER BACKUP STARTING. Server going readonly...\"\015'"
     as_user "screen -p 0 -S minecraft -X eval 'stuff \"save-off\"\015'"
     as_user "screen -p 0 -S minecraft -X eval 'stuff \"save-all\"\015'"
     sync
     sleep 10
     rsync -aPvzl --delete /home/minecraft /data/mirror/home
     as_user "screen -p 0 -S minecraft -X eval 'stuff \"save-on\"\015'"
#     as_user "screen -p 0 -S minecraft -X eval 'stuff \"say SERVER BACKUP ENDED. Server going read-write...\"\015'"
   else
     echo "$SERVICE is not running. Not suspending saves."
   fi
 }
 
 mc_announce() {
   if pgrep -u $USERNAME -f $SERVICE > /dev/null
   then
     echo "Stopping $SERVICE"
     as_user "screen -p 0 -S minecraft -X eval 'stuff \"say SERVER SHUTTING DOWN IN 5 minutes!\"\015'"
   fi
 }

 mc_stop() {
   if pgrep -u $USERNAME -f $SERVICE > /dev/null
   then
     echo "Stopping $SERVICE"
     as_user "screen -p 0 -S minecraft -X eval 'stuff \"say 10 SECONDS TO SHUTDOWN. PLEASE LOG OUT NOW. Saving map...\"\015'"
     as_user "screen -p 0 -S minecraft -X eval 'stuff \"save-all\"\015'"
     sleep 10
     as_user "screen -p 0 -S minecraft -X eval 'stuff \"stop\"\015'"
     sleep 7
   else
     echo "$SERVICE was not running."
   fi
   sleep 10
   if pgrep -u $USERNAME -f $SERVICE > /dev/null
   then
     echo "Error! $SERVICE could not be stopped."
   else
     echo "$SERVICE is stopped."
     php /home/minecraft/server/bin/commands/schedule_pre_reboot.php
   fi
 } 
 
 mc_command() {
   command="$1";
   if pgrep -u $USERNAME -f $SERVICE > /dev/null
   then
     pre_log_len=`wc -l "$MCPATH/logs/latest.log" | awk '{print $1}'`
     echo "$SERVICE is running... executing command"
     as_user "screen -p 0 -S minecraft -X eval 'stuff \"$command\"\015'"
     sleep .1 # assumes that the command will run and print to the log file in less than .1 seconds
     # print output
     tail -n $[`wc -l "$MCPATH/logs/latest.log" | awk '{print $1}'`-$pre_log_len] "$MCPATH/logs/latest.log"
   fi
 }
 
 #Start-Stop here
 case "$1" in
   start)
     mc_start
     ;;
   stop)
     mc_stop
     ;;
   announce)
     mc_announce
     ;;
   restart)
     mc_stop
     mc_start
     ;;
   backup)
     mc_backup
     ;;
   status)
     if pgrep -u $USERNAME -f $SERVICE > /dev/null
     then
       echo "$SERVICE is running."
     else
       echo "$SERVICE is not running."
     fi
     ;;
   command)
     if [ $# -gt 1 ]; then
       shift
       mc_command "$*"
     else
       echo "Must specify server command (try 'help'?)"
     fi
     ;;
 
   *)
   echo "Usage: $0 {start|stop|update|backup|status|restart|command \"server command\"}"
   exit 1
   ;;
 esac
 
 exit 0