--TEST--
route() function - '/' route.
--ENV--
return <<<END
REQUEST_URI=/
SCRIPT_NAME=/index.php
END;
--FILE--
<?php
include '../web.php';
var_dump(route('/', function() {
    return true;
}));
?>
--EXPECT--
bool(true)