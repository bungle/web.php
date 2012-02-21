# What is web.php?

**web.php** is a simple and minimalistic front controller based model-view-controller implementation for PHP > 5.3.

## Hello World in web.php

    <?php
    include 'web.php';
    get('/', function() {
        die('Hello, World!');
    });

## Installation

Download `web.php` (for logging download `log.php`, and for password hashing download `password.php`).

### Modify .htaccess

    <IfModule mod_rewrite.c>
        RewriteCond %{REQUEST_FILENAME} !-f
    	RewriteCond %{REQUEST_FILENAME} !-d
    	RewriteRule . index.php [L]
    </IfModule>

If you are using something other than `Apache` with `mod_rewrite`, Google for instructions.

### Create `index.php` bootstrap file

    <?php
    include 'web.php';
    get('/', function() {
        die('Hello, World!');
    });
    status('404');
    die('404 Not Found');

## Routing

`web.php` has support for routing HTTP GET, POST, PUT, and DELETE request.

### GET Routes

Use `get($path, $func)` to route HTTP GET requests.

Routes without parameters (the routes work on sub-directories too):

    get('/', function() {
        die('Hello, World!');
    });
    // or
    get('/posts', function() {
        die(json_encode(array(
            array(
                'id'    => 1,
                'title' => 'Trying out web.php',
                'body'  => 'Lorem...' 
            ),
            array(
                'id'    => 2,
                'title' => 'I'm really starting to like web.php',
                'body'  => 'Lorem...' 
            )
        )));
    });

Routes with parameters

Route parameters in `web.php` are parsed with `sscanf` and `vsprintf`, but we have added extra parameter `%p` which acts
the same as `%[^/]` (everything until / except `/`). Please read the documentation of `[sprintf](http://www.php.net/manual/function.sprintf.php)` 