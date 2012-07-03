<?php
namespace sqlite;
class blob {
    public $data;
    function __construct($data = null) {
        $this->data = $data;
    }
}
function connect($filename = null, $flags = SQLITE3_OPEN_READWRITE, $busyTimeout = 10000) {
    static $sqlite = null;
    if ($sqlite === null && $filename !== null) {
        $sqlite = new \SQLite3($filename, $flags);
        $sqlite->busyTimeout($busyTimeout);
        register_shutdown_function(function($sqlite) { $sqlite->close(); }, $sqlite);
    }
    return $sqlite;
}
function prepare($query, $params = array()) {
    $st = connect()->prepare($query);
    if ($st === false || count($params) === 0) return $st;
    $i = 0;
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
            $st->bindValue(++$i, $param->data, $param->data == null ? SQLITE_NULL : SQLITE3_BLOB);
        elseif ($param instanceof \DateTime)
            $st->bindValue(++$i, $param->format('Y-m-d\TH:i:s'), SQLITE3_TEXT);
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
function multi($query, $params = array(), $type = 'r', $filter = null) {
    $st = prepare($query, $params);
    if ($st === false) return false;
    $rs = $st->execute();
    if ($rs === false) {
        $st->close();
        return false;
    }
    $fetch = $type === 'r' ? SQLITE3_ASSOC : SQLITE3_NUM;
    $rows = array();
    while ($row = $rs->fetchArray($fetch)) {
        if ($filter !== null) $row = $filter($row, $rows);
        if ($row === null) continue;
        if ($type === 'v') $rows[] = $row[0];
        elseif ($type === 'p') $rows[$row[0]] = $row[1];
        else $rows[] = $row;
    }
    $rs->finalize();
    $st->close();
    return $rows;
}
function insert($table, $values, &$id = false) {
    $sql  = "INSERT INTO {$table} (" . implode(', ', array_keys($values)) . ') VALUES (' . substr(str_repeat(', ?', count($values)), 2) . ')';
    return modify($sql, array_values($values), $id);
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
    return modify($sql, $values);
}
function delete($table, $id = null) {
    $sql  = "DELETE FROM {$table}";
    if ($id === null) return connect()->exec(func_get_arg(0)) ? connect()->changes() : false;
    $sql .= ' WHERE id = ?';
    return modify($sql, array($id));
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
