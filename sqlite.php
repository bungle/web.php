<?php
namespace sqlite;
class blob {
    public $data;
    function __construct($data = null) {
        $this->data = $data;
    }
}
function connect($filename = null, $flags = SQLITE3_OPEN_READWRITE) {
    static $sqlite = null;
    if ($sqlite == null && $filename != null) {
        $sqlite = new \SQLite3($filename, $flags);
        $sqlite->busyTimeout(10000);
        register_shutdown_function(function($sqlite) { $sqlite->close(); }, $sqlite);
    }
    return $sqlite;
}
function prepare() {
    $count = func_num_args();
    if ($count === 0) return false;
    if ($count === 1) return connect()->prepare(func_get_arg(0));
    $params = func_get_args();
    $query = array_shift($args);
    $st = connect()->prepare($query);
    if ($st === false) return false;
    return bindValues($st, $params);
}
function bindValues(\SQLite3Stmt $st, array $params) {
    $count = count($params);
    for($i = 0; $i < $count;) {
        $param = $params[$i];
        if (is_int($param)) {
            $st->bindValue(++$i, $param, SQLITE3_INTEGER);
        } elseif (is_bool($param)) {
            $param = $param ? 1 : 0;
            $st->bindValue(++$i, $param, SQLITE3_INTEGER);
        } elseif (is_float($param)) {
            $st->bindValue(++$i, $param, SQLITE3_FLOAT);
        } elseif (is_string($param)) {
            $st->bindValue(++$i, $param, SQLITE3_TEXT);
        } elseif ($param == null) {
            $st->bindValue(++$i, $param, SQLITE3_NULL);
        } elseif ($param instanceof blob) {
            $st->bindValue(++$i, $param->data, $param->data == null ? SQLITE_NULL : SQLITE3_BLOB);
        } elseif ($param instanceof \DateTime) {
            $param = $param->format('Y-m-d\TH:i:s');
            $st->bindValue(++$i, $param, SQLITE3_TEXT);
        } else {
            $st->bindValue(++$i, $param);
        }
    }
    return $st;
}
function exec() {
    $count = func_num_args();
    if ($count === 0) return false;
    if ($count === 1) return connect()->exec(func_get_arg(0));
    $params = func_get_args();
    $query = array_shift($params);
    return execStatement($query, $params);
}
function execStatement($query, $params) {
    $st = prepare($query);
    if ($st === false) return false;
    $rs = bindValues($st, $params)->execute();
    if ($rs === false) {
        $st->close();
        return false;
    }
    $changes = connect()->changes();
    $rs->finalize();
    $st->close();
    return $changes;
}
function query() {
    $params = func_get_args();
    $count = count($params);
    $filter = false;
    if ($params[$count - 1] instanceof \Closure) {
        $filter = array_pop($params);
        $count--;
    }
    if ($count === 0) return false;
    if ($count === 1) {
        $rs = connect()->query($params[0]);
        if ($rs === false) return false;
        $rows = rows($rs, $filter);
        $rs->finalize();
        return $rows;        
    }
    $query = array_shift($params);
    return queryStatement($query, $params, $filter);
}
function queryStatement($query, $params = array(), $filter = false) {
    $st = prepare($query);
    if ($st === false) return false;
    $rs = bindValues($st, $params)->execute();
    if ($rs === false) {
        $st->close();
        return false;
    }
    $rows = rows($rs, $filter);
    $rs->finalize();
    $st->close();
    return $rows;
}
function singleValue() {
    $count = func_num_args();
    if ($count === 0) return false;
    if ($count === 1) return connect()->querySingle(func_get_arg(0), false);
    $params = func_get_args();
    $query = array_shift($params);
    return singleStatement($query, $params);
}
function singleRow() {
    $count = func_num_args();
    if ($count === 0) return false;
    if ($count === 1) return connect()->querySingle(func_get_arg(0), true);
    $params = func_get_args();
    $query = array_shift($params);
    return singleStatement($query, $params, true);
}
function singleStatement($query, $params, $entire_row = false) {
    $st = prepare($query);
    if ($st === false) return false;
    $rs = bindValues($st, $params)->execute();
    if ($rs === false) {
        $st->close();
        return false;
    }
    $row = $entire_row ? $rs->fetchArray(SQLITE3_ASSOC) : $rs->fetchArray(SQLITE3_NUM);
    $rs->finalize();
    $st->close();
    if ($entire_row || $row === false) return $row;
    return $row[0];
}
function escape($value) {
    return connect()->escapeString($value);
}
function rows($rs, $filter = false) {
    $rows = array();
    if ($filter === false) {
        while ($row = $rs->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;
    } else {
        while ($row = $rs->fetchArray(SQLITE3_ASSOC)) {
            $frow = $filter($row, $rows);
            if ($frow === null) continue;
            $rows[] = $frow;
        }
    }
    return $rows;
}

function update($table, $values, $id = null) {
    $sql  = "UPDATE {$table} SET " . implode(' = ?, ', array_keys($values));
    $values = array_values($values);
    if ($id == null) {
        $sql .= ' = ?';
    } else {
        $sql .= ' = ? WHERE id = ?';
        $values[] = $id;
    }
    return execStatement($sql, $values);
}

function insert($table, $values) {
    $sql  = "INSERT INTO {$table} (" . implode(', ', array_keys($values)) . ') VALUES (' . substr(str_repeat(', ?', count($values)), 2) . ')';
    return execStatement($sql, array_values($values));
}
