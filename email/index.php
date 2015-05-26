<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$s_post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
if (!isset($s_post['submit'])) {
    $out = "<form action=\"/moveimap/index.php/\" method=\"post\"><table>"
        . "<tr><td>Your old username</td><td><input type=\"text\" name=\"old_user\"></td></tr>\n"
        . "<tr><td>Your old password</td><td><input type=\"text\" name=\"old_pass\"></td></tr>\n"
        . "<tr><td>Your new username</td><td><input type=\"text\" name=\"new_user\"></td></tr>\n"
        . "<tr><td>Your new password</td><td><input type=\"text\" name=\"new_pass\"></td></tr>\n"
        . "<tr><td></td><td><input type=\"submit\" name=\"submit\" name=\"Synchronize!\"></td></tr>\n"
        . "</table></form>";
    echo $out;
} else {
    submit_it();
}

function submit_it() {
    $file = '/home/spiesshofer/public_html/moveimap/config.txt';    
    $s_post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    $new_user = $s_post['new_user'];
    $new_pass = $s_post['new_pass'];
    $old_user = $s_post['old_user'];
    $old_pass = $s_post['old_pass'];
    $data = "IMAPStore godaddy
RequireSSL yes
UseIMAPS yes
Port 993
UseSSLv2 yes
Host imap.secureserver.net
User $old_user
Pass $old_pass

IMAPStore 1and1
Port 993
RequireSSL yes
UseIMAPS yes
Host imap.1and1.com
User $new_user
Pass $new_pass

Channel default
Master :godaddy:
Slave :1and1:
Expunge None
Sync Pull
Patterns *
Create Slave";
    file_put_contents($file, $data);

    $cmd = "/usr/local/bin/mbsync -c $file default";
    disable_ob();
    $descriptors= array(
       0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
       1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
       2 => array("pipe", "w")    // stderr is a pipe that the child will write to
    );
    flush();
    $pipes = array();
    $process = proc_open($cmd, $descriptors, $pipes) or die("Can't open process $cmd!");
    echo "start...";    
    echo "<pre>";    
    $output = "";
    $stream1 = '';
    $stream2 = '';
    $read = array($stream1, $stream2);
    $write  = NULL;
    $last_out = false;
    $except = NULL;    
    while (!feof($pipes[2])) {
        $read = array($pipes[2]);
        stream_select($read, $write, $except, 0);
        if (!empty($read)) {
            $output .= fgets($pipes[2]);
        }
        # HERE PARSE $output TO UPDATE DOWNLOAD STATUS...
        print $output;
    }
    echo "</pre>";    
    echo "Done!";
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);    
}

function disable_ob() {
    // Turn off output buffering
    ini_set('output_buffering', 'off');
    // Turn off PHP output compression
    ini_set('zlib.output_compression', false);
    // Implicitly flush the buffer(s)
    ini_set('implicit_flush', true);
    ob_implicit_flush(true);
    // Clear, and turn off output buffering
    while (ob_get_level() > 0) {
        // Get the curent level
        $level = ob_get_level();
        // End the buffering
        ob_end_clean();
        // If the current level has not changed, abort
        if (ob_get_level() == $level) {
            break;
        }
    }
    // Disable apache output buffering/compression
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
        apache_setenv('dont-vary', '1');
    }
}