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

### Create `index.php` Bootstrap File

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

#### Routes without Parameters (the Routes Work on Sub-Directories too):

    <?php
    get('/', function() {
        die('Hello, World!');
    });

or:

    <?php
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

#### Parameterized Routes

Route parameters in `web.php` are parsed with `sscanf` and `vsprintf`, but we have added extra parameter `%p` which acts
the same as `%[^/]` (everything until or except `/`). Please read the documentation for the format from
[sprintf's](http://www.php.net/manual/function.sprintf.php) documentation.

    <?php
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

## Views, Layouts, Blocks, Partials, and Pagelets

`web.php` has support for views, and layouts (or even sub-layouts).

### Views

    <?php
    get('/', function() {
        die(new view('view.php'));
    });

view.php:

    <!DOCTYPE html>
    Hello World

### Views with Parameters

    <?php
    get('/%s', function($text) {
        $view = new view('view.php');
        $view->text = htmlspecialchars($text);
        die($view);
    });

view.php:

    <!DOCTYPE html>
    Hello, <?= $text ?>!

### Global View Variables

You can define global view variables that all the views will get with the following code:

    <?php
    view::$globals->title = 'web.php rocks!';

Note: If local view variables are defined with same name as global variables, local variables overwrite the global ones.

### Layouts

You can define the layout by setting the `layout` variable in a view, you can do it like this:

    <?php
    view::$globals->layout = 'layout.php'; // or
    $view = new view('view.php', 'layout.php'); // or
    $view = new view('view.php');
    $view->layout = 'layout.php'; // or
    $view = new view('view.php');

view.php:

    <?php $layout = 'layout.php'; ?>
    Hello, World!

layout.php:

    <!DOCTYPE html>
    <html>
        <body><?= $view ?></body>
    </html>

### Blocks

TBD

### Partials

TBD

### Pagelets

TBD (see: [Facebook's BigPipe](https://www.facebook.com/note.php?note_id=389414033919).

## Forms, and Filters

TBD
    
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