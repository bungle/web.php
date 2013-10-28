<?php
namespace sqlite;

define('SQLITE3_TX_DEFERRED', 0);
define('SQLITE3_TX_IMMEDIATE', 1);
define('SQLITE3_TX_EXCLUSIVE', 2);

class blob {
    public $data;
    function __construct($data = null) {
        $this->data = $data;
    }
}
function blob($data) {
    return new blob($data);
}
function connect($filename = null, $flags = SQLITE3_OPEN_READWRITE, $busyTimeout = null, $pragmas = array()) {
    static $sqlite;
    if ($sqlite !== null) return $sqlite;
    if ($filename !== null) {
        $sqlite = new \SQLite3($filename, $flags);
    } elseif (defined('SQLITE3_DATABASE')) {
        $sqlite = new \SQLite3(SQLITE3_DATABASE, $flags);
    } else {
        return null;
    }
    register_shutdown_function(function($sqlite) { $sqlite->close(); }, $sqlite);
    if ($busyTimeout !== null) {
        $sqlite->busyTimeout($busyTimeout);
    } elseif (defined('SQLITE3_BUSY_TIMEOUT')) {
        $sqlite->busyTimeout(SQLITE3_BUSY_TIMEOUT);
    }
    if (count($pragmas) === 0) return $sqlite;
    $sql = '';
    foreach($pragmas as $pragma => $value) {
        $sql .= is_int($pragma) ? "PRAGMA {$value};" : "PRAGMA {$pragma}={$value};";
    }
    $sqlite->exec($sql);
    return $sqlite;
}
function tx($func, $mode = null) {
    static $lv = 0;
    $lv++;
    if ($lv === 1) {
        set_error_handler(function($code, $message, $file, $line) {
            throw new \ErrorException($message, $code, 0, $file. $line);
        }, -1);
    }
    try {
        if ($lv === 1) {
            switch($mode) {
                case SQLITE3_TX_DEFERRED:  $tx = exec("BEGIN DEFERRED TRANSACTION")  !== false; break;
                case SQLITE3_TX_IMMEDIATE: $tx = exec("BEGIN IMMEDIATE TRANSACTION") !== false; break;
                case SQLITE3_TX_EXCLUSIVE: $tx = exec("BEGIN EXCLUSIVE TRANSACTION") !== false; break;
                default:                   $tx = exec("BEGIN TRANSACTION")           !== false; break;
            }
            if ($tx === false) throw new \Exception('Unable to begin a transaction.');
            $rt = $func();
            $cm = $rt === false ? \sqlite\exec('ROLLBACK TRANSACTION') !== false
                : \sqlite\exec('COMMIT TRANSACTION')   !== false;
            restore_error_handler();
        } else {
            $tx = exec("SAVEPOINT tx{$lv}");
            if ($tx === false) throw new \Exception('Unable to mark a savepoint.');
            $rt = $func();
            $cm = $rt === false ? \sqlite\exec("ROLLBACK TRANSACTION TO SAVEPOINT tx{$lv}") !== false
                : \sqlite\exec("RELEASE SAVEPOINT tx{$lv}")                  !== false;
        }
        $lv--;
        return $cm ? $rt : false;
    } catch (\Exception $e) {
        if ($lv === 1) {
            \sqlite\exec('ROLLBACK TRANSACTION');
            restore_error_handler();
        } else {
            \sqlite\exec("ROLLBACK TRANSACTION TO SAVEPOINT tx{$lv}");
        }
        $lv--;
        throw($e);
    }
}
function prepare($query, $params = array()) {
    $st = connect()->prepare($query);
    if ($st === false || count($params) === 0) return $st;
    $i = 0;
    if (count($params) === 1 && is_array($params[0])) $params = $params[0];
    foreach($params as $param) {
        if (is_int($param))
            $st->bindValue(++$i, $param, SQLITE3_INTEGER);
        elseif (is_bool($param))
            $st->bindValue(++$i, $param ? 1 : 0, SQLITE3_INTEGER);
        elseif (is_float($param))
            $st->bindValue(++$i, $param, SQLITE3_FLOAT);
        elseif (is_string($param))
            $st->bindValue(++$i, $param, SQLITE3_TEXT);
        elseif ($param === null)
            $st->bindValue(++$i, $param, SQLITE3_NULL);
        elseif ($param instanceof blob)
            $st->bindValue(++$i, $param->data, $param->data === null ? SQLITE3_NULL : SQLITE3_BLOB);
        elseif ($param instanceof \DateTime)
            $st->bindValue(++$i, $param->format('Y-m-d H:i:s'), SQLITE3_TEXT);
        else
            $st->bindValue(++$i, $param);
    }
    return $st;
}
function value() {
    $count = func_num_args();
    if ($count === 0) return false;
    if ($count === 1) return connect()->querySingle(func_get_arg(0), false);
    $params = func_get_args();
    return single(array_shift($params), $params, 'v');
}
function pair() {
    if (func_num_args() === 0) return false;
    $params = func_get_args();
    return single(array_shift($params), $params, 'p');
}
function row() {
    $count = func_num_args();
    if ($count === 0) return false;
    if ($count === 1) return connect()->querySingle(func_get_arg(0), true);
    $params = func_get_args();
    return single(array_shift($params), $params, 'r');
}
function single($query, $params = array(), $type = 'r') {
    $st = prepare($query, $params);
    if ($st === false) return false;
    $rs = $st->execute();
    if ($rs === false) {
        $st->close();
        return false;
    }
    $row = $type === 'r' ? $rs->fetchArray(SQLITE3_ASSOC) : $rs->fetchArray(SQLITE3_NUM);
    $rs->finalize();
    $st->close();
    if ($row === false) return false;
    if ($type === 'v') return $row[0];
    if ($type === 'p') return array($row[0] => $row[1]);
    return $row;
}
function values() {
    if (func_num_args() === 0) return false;
    $params = func_get_args();
    $count = count($params);
    $filter = null;
    if ($params[$count - 1] instanceof \Closure) {
        if ($count === 1) return false;
        $filter = array_pop($params);
    }
    return multi(array_shift($params), $params, 'v', $filter);
}
function pairs() {
    if (func_num_args() === 0) return false;
    $params = func_get_args();
    $count = count($params);
    $filter = null;
    if ($params[$count - 1] instanceof \Closure) {
        if ($count === 1) return false;
        $filter = array_pop($params);
    }
    return multi(array_shift($params), $params, 'p', $filter);
}
function rows() {
    if (func_num_args() === 0) return false;
    $params = func_get_args();
    $count = count($params);
    $filter = null;
    if ($params[$count - 1] instanceof \Closure) {
        if ($count === 1) return false;
        $filter = array_pop($params);
    }
    return multi(array_shift($params), $params, 'r', $filter);
}
function multi($query, $params = array(), $type = 'r', \Closure $filter = null) {
    $st = prepare($query, $params);
    if ($st === false) return false;
    $rs = $st->execute();
    if ($rs === false) {
        $st->close();
        return false;
    }
    $fetch = $type === 'r' ? SQLITE3_ASSOC : SQLITE3_NUM;
    $results = array();
    while ($row = $rs->fetchArray($fetch)) {
        if ($filter !== null) {
            if ($type === 'v') {
                $val = $row[0];
                if ($filter($val, $results) === false) continue;
                $results[] = $val;
            } elseif ($type === 'p') {
                $key = $row[0];
                $val = $row[1];
                if ($filter($key, $val, $results) === false) continue;
                $results[$key] = $val;
            } else {
                if ($filter($row, $results) === false) continue;
                $results[] = $row;
            }
        } else {
            if ($type === 'v') $results[] = $row[0];
            elseif ($type === 'p') $results[$row[0]] = $row[1];
            else $results[] = $row;
        }
    }
    $rs->finalize();
    $st->close();
    return $results;
}
function insert($table, $values, &$id = false) {
    $sql = "INSERT INTO {$table} (" . implode(', ', array_keys($values)) . ') VALUES (' . substr(str_repeat(', ?', count($values)), 2) . ')';
    return modify($sql, array_values($values), $id);
}
function update() {
    $count = func_num_args();
    if ($count < 2) return false;
    $args = func_get_args();
    $table = array_shift($args);
    $values = array_shift($args);
    $sql = "UPDATE {$table} SET " . implode(' = ?, ', array_keys($values)) . ' = ?';
    $values = array_values($values);
    if ($count === 2) return modify($sql, $values);
    $where = array_shift($args);
    if (is_array($where)) {
        $sql .= ' WHERE ';
        $sql .= implode(' = ? AND ', array_keys($where)) . ' = ?';
        return modify($sql, array_merge($values, array_values($where)));
    } elseif (is_int($where)) {
        $sql .= ' WHERE id = ?';
        $values[] = $where;
        return modify($sql, $values);
    } elseif (is_string($where)) {
        $sql .= " WHERE {$where}";
        if ($count > 3) $values = array_merge($values, $args);
        return modify($sql, $values);
    }
    return false;
}
function delete() {
    $count = func_num_args();
    if ($count === 0) return false;
    $args = func_get_args();
    $table = array_shift($args);
    $sql = "DELETE FROM {$table}";
    if ($count === 1) return connect()->exec($sql) !== false ? connect()->changes() : false;
    $where = array_shift($args);
    if (is_array($where)) {
        $sql .= ' WHERE ';
        $sql .= implode(' = ? AND ', array_keys($where)) . ' = ?';
        return modify($sql, array_values($where));
    } elseif (is_int($where)) {
        $sql .= ' WHERE id = ?';
        return modify($sql, array($where));
    } elseif (is_string($where)) {
        $sql .= " WHERE {$where}";
        if ($count === 2) return connect()->exec($sql) !== false ? connect()->changes() : false;
        return modify($sql, $args);
    }
    return false;
}
function exists() {
    $count = func_num_args();
    if ($count === 0) return false;
    $args = func_get_args();
    $table = array_shift($args);
    $sql = "SELECT EXISTS(SELECT 1 FROM {$table}";
    $exists = false;
    if ($count === 1) {
        $exists = value($sql . ')');
    } else {
        $where = array_shift($args);
        if (is_array($where)) {
            $sql .= ' WHERE ';
            $sql .= implode(' = ? AND ', array_keys($where)) . ' = ?)';
            $exists = single($sql, array_values($where), 'v');
        } elseif (is_int($where)) {
            $sql .= ' WHERE id = ?)';
            $exists = value($sql, $where);
        } elseif (is_string($where)) {
            $sql .= " WHERE {$where})";
            $exists = $count === 2 ? value($sql) : single($sql, $args, 'v');
        }
    }
    return $exists !== false && $exists !== 0;
}
function exec() {
    $count = func_num_args();
    if ($count === 0) return false;
    if ($count === 1) return connect()->exec(func_get_arg(0)) ? connect()->changes() : false;
    $params = func_get_args();
    return modify(array_shift($params), $params);
}
function modify($query, $params, &$id = null) {
    $st = prepare($query, $params);
    if ($st === false) return false;
    $rs = $st->execute();
    if ($rs === false) {
        $st->close();
        return false;
    }
    $id = connect()->lastInsertRowID();
    $changes = connect()->changes();
    $rs->finalize();
    $st->close();
    return $changes;
}
