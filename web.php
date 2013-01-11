<?php
// Core Functions
function get($path, $func) {
    return $_SERVER['REQUEST_METHOD'] === 'GET' ? route($path, $func) : false;
}
function post($path, $func) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (isset($_POST['_method']) && $_POST['_method'] !== 'POST') return false;
    return route($path, $func);
}
function put($path, $func) {
    return $_SERVER['REQUEST_METHOD'] === 'PUT' || (isset($_POST['_method']) && $_POST['_method'] === 'PUT') ? route($path, $func) : false;
}
function delete($path, $func) {
    return $_SERVER['REQUEST_METHOD'] === 'DELETE' || (isset($_POST['_method']) && $_POST['_method'] === 'DELETE') ? route($path, $func) : false;
}
function route($path, $func) {
    if ($func === false) return false;
    static $url = null;
    if ($url == null) {
        $url = parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH);
        $url = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(substr($url, 0, strrpos($url, '/')))), '/');
    }
    $path = trim($path, '/');
    $scnf = str_replace('%p', '%[^/]', $path);
    $prnf = str_replace('%p', '%s', $path);
    $args = sscanf($url, $scnf);
    if (substr_count($prnf, '%') !== count($args)) return false;
    $path = vsprintf($prnf, $args);
    if ($path !== $url) return false;
    $args = array_map(function($value) { return is_string($value) ? urldecode($value) : $value; }, $args);
    return call($func, $args);
}
function call($func, array $args = array()) {
    if (is_string($func)) {
        if (file_exists($func)) return require $func;
        if (strpos($func, '->') > 0) {
            list($clazz, $method) = explode('->', $func, 2);
            $func = array(new $clazz, $method);
        }
    } elseif (is_bool($func)) {
        return $func;
    }
    return call_user_func_array($func, $args);
}
function forward($name, $func = null) {
    static $routes = array();
    if ($func != null) {
        $routes[$name] = $func;
        return;
    }
    if (isset($routes[$name])) return call($routes[$name]);
    trigger_error(sprintf('Invalid forward: %s', $name), E_USER_WARNING);
}
function accept() {
    $rslt = false;
    if (func_num_args() === 1) {
        $hash = func_get_arg(0);
        $ctps = array_keys($hash);
        $type = http_negotiate_content_type($ctps, $rslt);
        if ($rslt == false) return false;
        header("Content-type: ${type}");
        call($hash[$type]);
        return true;
    }
    $ctps = func_get_args();
    $func = array_pop($ctps);
    $type = http_negotiate_content_type($ctps, $rslt);
    if ($rslt == false) return false;
    header("Content-type: ${type}");
    return $func;
}
function url($url = null, $abs = false) {
    if ($url == null) {
        $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $abs = true;
    }
    if (parse_url($url, PHP_URL_SCHEME) !== null) return $url;
    static $base = null, $path = null, $root = null;
    if ($base == null) {
        $base = parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH);
        $base = substr($base, 0, strrpos($base, '/'));
    }
    if ($path == null) {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = substr($path, 0, strrpos($path, '/'));
    }
    if (!$abs) return strpos($url, '~/') === 0 ? $base . '/' . substr($url, 2) : $url;
    if ($root == null) {
        $root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        $port = $_SERVER['SERVER_PORT'];
        if (($root[4] === 's' && $port !== '443') || $port !== '80') $root .= ":$port";
    }
    if (strpos($url, '~/') === 0) return $root . $base . '/' . substr($url, 2);
    return strpos($url, '/') === 0 ? $root . $url : $root . $path . '/' . $url;
}
function redirect($url, $code = 302, $die = true) {
    header('Location: ' . url($url, true), true, $code);
    if ($die) die;
}
function flash($name, $value = true, $hops = 1) {
    $_SESSION[$name] = $value;
    if (!isset($_SESSION['web.php:flash']))
        $_SESSION['web.php:flash'] = array($name => $hops);
    else
        $_SESSION['web.php:flash'][$name] = $hops;
}
function sendfile($path, $name = null, $disposition = 'inline', $mime = null, $die = true) {
    $filename = $name === null ? basename($path) : basename($name);
    if ($mime == null) {
        $fnfo = finfo_open(FILEINFO_MIME_TYPE);
        $fmim = finfo_file($fnfo, $path);
        finfo_close($fnfo);
        $mime = $fmim === false ? 'application/octet-stream' : $fmim;
    }
    header("Content-Type: {$mime}");
    header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
    if (defined('XSENDFILE_HEADER')) {
        if (defined('XSENDFILE_PATH')) {
            header(XSENDFILE_HEADER . ': ' . XSENDFILE_PATH . $path);
        } else {
            header(XSENDFILE_HEADER . ': ' . $path);
        }
    } else {
        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
    if ($die) die;
}
function ajax($func = null) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return $func == null ? true : $func();
    }
    return false;
}
// View
class view {
    static $globals;
    function __construct($file, $layout = null) {
        $this->file = $file;
        if ($layout != null) $this->layout = $layout;
    }
    function __toString() {
        extract((array)self::$globals);
        extract((array)$this);
        start:
        ob_start();
        include $file;
        if (!isset($layout)) return ob_get_clean();
        $view = ob_get_clean();
        $file = $layout;
        unset($layout);
        goto start;
    }
}
view::$globals = new stdClass;
function block(&$block = false) {
    if ($block === false) return ob_end_clean();
    return ob_start(function($buffer) use (&$block) { $block = $buffer; });
}
function partial($file, $args = null) {
    ob_start();
    if ($args !== null) extract($args);
    require $file;
    return ob_get_clean();
}
// Not yet finished
// see: https://www.facebook.com/note.php?note_id=389414033919
function pagelets($id = null, $func = null) {
    static $pagelets = array();
    if ($id == null && $func == null) {
        ob_flush();
        flush();
        $pagelets = array_map(function($pagelet) {
            $ret = $pagelet();
            ob_flush();
            flush();
            return $ret;
        }, $pagelets);
        return json_encode($pagelets);
    }
    $pagelets[] = $func;
    return "<div id=\"{$id}\"></div>";
}
// Filters
function filter() {
    $filters = func_get_args();
    $value = $original = array_shift($filters);
    $valid = true;
    foreach ($filters as $filter) {
        if ($filter === true) continue;
        if ($filter === false) return false;
        switch ($filter) {
            case 'bool':  $valid = is_bool($value) || false !== filter_var($value, FILTER_VALIDATE_BOOLEAN); break;
            case 'int':   $valid = false !== filter_var($value, FILTER_VALIDATE_INT); break;
            case 'float': $valid = false !== filter_var($value, FILTER_VALIDATE_FLOAT); break;
            case 'ip':    $valid = false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6); break;
            case 'ipv4':  $valid = false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4); break;
            case 'ipv6':  $valid = false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6); break;
            case 'email': $valid = false !== filter_var($value, FILTER_VALIDATE_EMAIL); break;
            case 'url':   $valid = false !== filter_var($value, FILTER_VALIDATE_URL); break;
            default:
                if (is_string($filter) && strpos($filter, '/') === 0)
                    $valid = preg_match($filter, $value) > 0;
                else if (is_callable($filter)) {
                    $filtered = call_user_func($filter, $value);
                    if (is_bool($filtered)) $valid = $filtered; else $value = $filtered;
                } else trigger_error(sprintf('Invalid filter: %s', $filter), E_USER_WARNING);
        }
        if (!$valid) return false;
    }
    return $value !== $original ? $value : true;
}
function not($filter) {
    if (is_callable($filter)) return function($value) use ($filter) {
        $value = $filter($value);
        if ($value !== null && is_bool($value)) return !$value;
        return;
    };
    return is_bool($filter) ? !$filter : null;
}
function equal($exact, $strict = true) {
    $compare = $exact instanceof field ? $exact->value : $exact;
    return function($value) use ($compare, $strict) {
        return $strict ? $value === $compare : $value == $compare;
    };
}
function length($min, $max = null, $charset = 'UTF-8') {
    return function($value) use ($min, $max, $charset) {
        $value = $value instanceof field ? $value->value : $value;
        $len = mb_strlen($value, $charset);
        return $len >= $min && $len <= ($max == null ? $min : $max);
    };
}
function minlength($min, $charset = 'UTF-8') {
    return function($value) use ($min, $charset) {
        $value = $value instanceof field ? $value->value : $value;
        return mb_strlen($value, $charset) >= $min;
    };
}
function maxlength($max, $charset = 'UTF-8') {
    return function($value) use ($max, $charset) {
        $value = $value instanceof field ? $value->value : $value;
        return mb_strlen($value, $charset) <= $max;
    };
}
function between($min, $max) {
    return function($value) use ($min, $max) {
        $value = $value instanceof field ? $value->value : $value;
        return $value >= $min && $value <= $max;
    };
}
function minvalue($min) {
    return function($value) use ($min) {
        $value = $value instanceof field ? $value->value : $value;
        return $value >= $min;
    };
}
function maxvalue($max) {
    return function($value) use ($max) {
        $value = $value instanceof field ? $value->value : $value;
        return $value <= $max;
    };
}
function choice() {
    $choices = func_get_args();
    return function($value) use ($choices) {
        $value = $value instanceof field ? $value->value : $value;
        return in_array($value, $choices);
    };
}
function specialchars($quote = ENT_NOQUOTES, $charset = 'UTF-8', $double = true) {
    return function($value) use ($quote, $charset, $double) {
        $value = $value instanceof field ? $value->value : $value;
        return htmlspecialchars($value, $quote, $charset, $double);
    };
}
function slug($str, $delimiter = '-') {
    $qtd = preg_quote($delimiter);
    $str = preg_replace('/\p{Mn}/u', '', normalizer_normalize($str, Normalizer::FORM_KD));
    $str = preg_replace('[\W]', $delimiter, $str);
    $str = preg_replace("/[{$qtd}]{2,}/", $delimiter, $str);
    return strtolower(trim($str, $delimiter));
}
function date_from_format($format = 'Y-m-d', $timezone = null) {
    return function($value) use ($format, $timezone) {
        $date = $timezone instanceof DateTimeZone ? date_create_from_format($format, $value, $timezone) : date_create_from_format($format, $value);
        return $date !== false ? $date : null;
    };
}
// Form
class form {
    public $valid = true;
    function __construct($args = null) {
        if ($args == null) return;
        foreach ($args as $name => $value) $this->$name = $value;
    }
    function __get($name) {
        if (!isset($this->$name)) $this->$name = new field($name);
        return $this->$name;
    }
    function __set($name, $value) {
        $this->$name = new field($name, $value);
    }
    function validate() {
        foreach($this as $field) {
            if ($field instanceof field && !$field->valid)
                return $this->valid = false;
        }
        return $this->valid = true;
    }
    function data() {
        $args = func_num_args() > 0;
        if ($args) $args = func_get_args();
        $data = array();
        foreach($this as $field)
            if ($field instanceof field && $field->valid && ($args === false || in_array($field->name, $args, true)))
                $data[$field->name] = $field->value;
        return $data;
    }
}
class field {
    public $name, $value, $original, $valid;
    function __construct($name, $value = null) {
        $this->name = $name;
        $this->value = $this->original = $value;
        $this->valid = true;
    }
    function filter() {
        $filters = func_get_args();
        foreach ($filters as $filter) {
            $filtered = filter($this->value, $filter);
            if ($filtered === true) continue;
            if ($filtered === false) {
                $this->valid = false;
                break;
            }
            $this->value = $filtered;
        }
        return $this;
    }
    function __toString() {
        return strval($this->value);
    }
}
// Shutdown Function
register_shutdown_function(function() {
    if (!defined('SID') || !isset($_SESSION['web.php:flash'])) return;
    $flash =& $_SESSION['web.php:flash'];
    foreach($flash as $key => $hops) {
        if ($hops === 0)  unset($_SESSION[$key], $flash[$key]);
        else $flash[$key]--;
    }
    if (count($flash) === 0) unset($flash);
});
