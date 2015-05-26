<?php

/**
 * PDO abstraction
 *
 */

// legacy
mysql_connect('localhost', 'minecraft', '9sd6ncC9vEcTD55Z');

// new way
$UMC_DB = new PDO('mysql:host=localhost;dbname=minecraft', "minecraft", "9sd6ncC9vEcTD55Z");
$UMC_DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

/**
 * Replacement for mysql_query
 * returns result. includes error notification
 *
 * can close the query if needed, otherwise returns result
 *
 * @global PDO $UMC_DB
 * @param type $sql
 * @param type $close
 * @return type
 */
function umc_mysql_query($sql, $close = false) {
    global $UMC_DB;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $rst = $UMC_DB->query($sql);
    $error = $UMC_DB->errorInfo();
    if (!is_null($error[2])) {
        XMPP_ERROR_trigger("MySQL Query Error: '$sql' : " . $error[2]);
    } else if ($close) {
        $rst->closeCursor();
    } else {
        return $rst;
    }
}

/**
 * Replacement for mysql_insert_id
 *
 * @global PDO $UMC_DB
 * @return type integer
 */
function umc_mysql_insert_id() {
    global $UMC_DB;
    return $UMC_DB->lastInsertId();
}

/**
 * Replacement for mysql_fetch_array (MYSQL_ASSOC)
 * Returns one line of associative arrays
 *
 * @param type $rst
 * @return type
 */
function umc_mysql_fetch_array($rst) {
    
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());    
    if (!$rst) {
        XMPP_ERROR_trigger("tried fetch_array on erroneous recordset");
        return false;
    } 
    $row = $rst->fetch(PDO::FETCH_ASSOC);
    return $row;
}

/**
 * Replacement of mysql_free_result
 *
 * @param type $rst
 */
function umc_mysql_free_result($rst) {
    $rst->closeCursor();
}

/**
 * Replacement of mysql_real_escape_string
 * ATTENTION: This also puts quotes around the value
 *
 * @global PDO $UMC_DB
 * @param type $value
 * @return type
 */
function umc_mysql_real_escape_string($value) {
    global $UMC_DB;
    return $UMC_DB->quote($value);
}

function umc_mysql_fetch_all($sql) {
    global $UMC_DB;
    XMPP_ERROR_trace(__FUNCTION__, func_get_args());
    $stmt = $UMC_DB->prepare($sql);
    if (!$stmt) {
        $error = $UMC_DB->errorInfo();
        XMPP_ERROR_trigger($error);
        return false;
    } else {
        $stmt->execute();
    }
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result;
}
