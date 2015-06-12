<?php

function umc_github_link() {
    $secret = 'IY8uSgfq3HWl60jiOzgC';
    echo "Hello World!";

    $foo = file_get_contents("php://input");

    $value = var_export(json_decode($foo, true), true);
    XMPP_ERROR_trigger("$value");

    
}