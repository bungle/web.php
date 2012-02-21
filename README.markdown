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

`web.php` has support for routing HTTP GET, POST, PUT, and DELETE request. Routes are case-insensitive, and the trailing `/` is omitted.

### GET Routes

Use `get($path, $func)` to route HTTP GET requests.

#### Routes without parameters (the routes work on sub-directories too):

    get('/', function() {
        die('Hello, World!');
    });

or:

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

#### Parameterized routes

Route parameters in `web.php` are parsed with `sscanf` and `vsprintf`, but we have added extra parameter `%p` which acts
the same as `%[^/]` (everything until or except `/`). Please read the documentation for the format from
[sprintf's](http://www.php.net/manual/function.sprintf.php) documentation.

    get('/posts/%d', function($id) {
        switch ($id) {
            case 1: die(json_encode(array(
                    'id'    => 1,
                    'title' => 'Trying out web.php',
                    'body'  => 'Lorem...' 
                )));
            case 2: die(json_encode(array(
                    'id'    => 2,
                    'title' => 'I'm really starting to like web.php',
                    'body'  => 'Lorem...' 
                )));
        }
    }

### POST Routes

Use `post($path, $func)` to route HTTP POST requests. See the *GET Routes* examples.

### PUT Routes

Use `put($path, $func)` to route HTTP PUT requests. See the *GET Routes* examples.

### DELETE Routes

Use `delete($path, $func)` to route HTTP DELETE requests. See the *GET Routes* examples.

## FAQ

> Is routing to anonymous function the only option?

You can actually route to `files`, `functions`, `static class methods`, and `object instance methods`:

    get('/', 'router.php');
    get('/%p', 'die');
    get('/', 'Clazz::staticMethod');
    get('/', 'Clazz->instanceMethod'); // Instantiates new object from class 'Clazz' using parameterless constructor

> Why are you using 'die' inside controllers? How can I execute code after executing route, i.e. cleanup resources?

This design decision is probably something that people may or may not agree. We think that it is user's responsibility to
manage the execution. You don't have to `die`, but keep in mind that any other route that is executed after the matching
one will also be executed if it matches the url. That's why it's common to `die` with web.php.

If you want to run cleanup code, please try to build your code so that cleanup routines can be registered with
`register_shutdown_function`.