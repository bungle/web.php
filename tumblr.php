<?php
class tumblr {
    function __construct($host, $key = null, $secret = null, $token = null, $token_secret = null) {
        $this->host = $host;
        $this->key = $key;
        if ($secret != null) {
            $oauth = new OAuth($key, $secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
            if ($token != null && $token_secret != null) $oauth->setToken($token, $token_secret);
            $this->oauth = $oauth;
        }
    }
    function request_token($callback_url) {
        return $this->oauth->getRequestToken('http://www.tumblr.com/oauth/request_token', $callback_url);
    }
    function access_token($request_token, $request_secret, $verifier_token) {
        $this->oauth->setToken($request_token, $request_secret);
        return $this->oauth->getAccessToken('http://www.tumblr.com/oauth/access_token', null, $verifier_token);
    }
    function info() {
        return file_get_contents("http://api.tumblr.com/v2/blog/{$this->host}/info?api_key={$this->key}");
    }
    function avatar($size = 64, $fetch_content = false) {
        $url = "http://api.tumblr.com/v2/blog/{$this->host}/avatar/{$size}";
        if ($fetch_content) return file_get_contents($url);
        $headers = get_headers($url, 1);
        return $headers['Location'];    
    }
    function followers($limit = 20, $offset = 0) {
        return $this->fetch("http://api.tumblr.com/v2/blog/{$this->host}/followers?limit={$limit}&offset={$offset}");
    }
    function posts($args = null) {
        $url ="http://api.tumblr.com/v2/blog/{$this->host}/posts";
        $type = isset($args['type']) ? $args['type'] : null;
        unset($args['type']);
        if ($type != null) $url .= "/{$type}";
        $url .= "?api_key={$this->key}";
        if (is_array($args)) $args = http_build_query($args);
        if (iconv_strlen($args) > 0) $url .= "&{$args}";
        return file_get_contents($url);
    }
    function queue() {
        return $this->fetch("http://api.tumblr.com/v2/blog/{$this->host}/posts/queue");
    }
    function drafts() {
        return $this->fetch("http://api.tumblr.com/v2/blog/{$this->host}/posts/draft");
    }
    function submissions() {
        return $this->fetch("http://api.tumblr.com/v2/blog/{$this->host}/posts/submission");
    }
    function post($args) {
        return $this->fetch("http://api.tumblr.com/v2/blog/{$this->host}/post", $args, OAUTH_HTTP_METHOD_POST);
    }
    function edit($args) {
        return $this->fetch("http://api.tumblr.com/v2/blog/{$this->host}/post/edit", $args, OAUTH_HTTP_METHOD_POST);
    }
    function reblog($reblog_key, $comment = null, $id = null) {
        $args = array('reblog_key' => $reblog_key);
        if ($comment != null) $args['comment'] = $comment;
        if ($id != null) $args['id'] = $id;
        return $this->fetch("http://api.tumblr.com/v2/blog/{$this->host}/post/reblog", $args, OAUTH_HTTP_METHOD_POST);
    }
    function delete($id) {
        return $this->fetch("http://api.tumblr.com/v2/blog/{$this->host}/post/delete", array('id' => $id), OAUTH_HTTP_METHOD_POST);
    }
    function user() {
        return $this->fetch('http://api.tumblr.com/v2/user/info', array(), OAUTH_HTTP_METHOD_POST);
    }
    function dashboard() {
        return $this->fetch('http://api.tumblr.com/v2/user/dashboard');
    }
    function likes($limit = 20, $offset = 0) {
        return $this->fetch('http://api.tumblr.com/v2/user/likes', array('limit' => $limit, 'offset' => $offset));
    }
    function following($limit = 20, $offset = 0) {
        return $this->fetch('http://api.tumblr.com/v2/user/following', array('limit' => $limit, 'offset' => $offset));
    }
    function follow($url) {
        return $this->fetch('http://api.tumblr.com/v2/user/follow', array('url' => $url), OAUTH_HTTP_METHOD_POST);
    }
    function unfollow($url) {
        return $this->fetch('http://api.tumblr.com/v2/user/unfollow', array('url' => $url), OAUTH_HTTP_METHOD_POST);
    }
    private function fetch($url, $params = array(), $method = OAUTH_HTTP_METHOD_GET) {
        $this->oauth->fetch($url, $params, $method);
        return $this->oauth->getLastResponse();
    }
}