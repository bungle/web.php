<?php
namespace openid {
    function auth($url, array $params = array()) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array('Accept: application/xrds+xml')
        ));
        $oid = curl_exec($ch);
        curl_close($ch);
        $url = simplexml_load_string($oid)->XRD->Service->URI;
        $needed = array(
            'openid.mode' => 'checkid_setup',
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select'
        );
        $params = array_merge($params, $needed);
        $qs = parse_url($url, PHP_URL_QUERY);
        $url .= isset($qs) ? '&' : '?';
        $url .= http_build_query($params);
        redirect($url);
    }
    function check($url) {
        $data = str_replace('openid.mode=id_res', 'openid.mode=check_authentication', $_SERVER['QUERY_STRING']);
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => $data
        ));
        $oid = curl_exec($ch);
        curl_close($ch);
        return strpos($oid, 'is_valid:true') === 0;
    }
}