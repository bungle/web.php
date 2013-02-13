<?php
namespace sqlite;
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
        $sql .= is_int($pragma) ? "PRAGMA {$value};\n" : "PRAGMA {$pragma}={$value};\n";
    }
    $sqlite->exec($sql);
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
