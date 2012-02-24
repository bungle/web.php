--TEST--
route() function - '/no-handler' route.
--ENV--
return <<<END
REQUEST_URI=/no-handler
END;
--FILE--
<?php
$_SERVER['SCRIPT_NAME'] = '/index.php';
include '../web.php';
var_dump(route('/', function() {
    return true;
}));
?>
--EXPECT--
bool(false)