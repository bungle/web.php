<?php
namespace crypt;

function encrypt($value, $key = null, $cipher = null, $mode = null, $block = null, $charset = 'UTF-8') {
    $key    = $key    ?: (defined('MCRYPT_KEY')    ? MCRYPT_KEY    : '5h6h2u8559x8IU92G31PcR87NjnJ4KKa');
    $cipher = $cipher ?: (defined('MCRYPT_CIPHER') ? MCRYPT_CIPHER : MCRYPT_RIJNDAEL_256);
    $mode   = $mode   ?: (defined('MCRYPT_MODE')   ? MCRYPT_MODE   : MCRYPT_MODE_CBC);
    $block  = $block  ?: (defined('MCRYPT_BLOCK')  ? MCRYPT_BLOCK  : 32);
    $iv     = mcrypt_create_iv(mcrypt_get_iv_size($cipher, $mode), MCRYPT_DEV_URANDOM);
    $pad    = $block - (mb_strlen($value, $charset) % $block);
    $value  = mcrypt_encrypt($cipher, $key, $value . str_repeat(chr($pad), $pad), $mode, $iv);
    return base64_encode("{$iv}{$value}");
}
function decrypt($value, $key = null, $cipher = null, $mode = null, $block = null, $charset = 'UTF-8') {
    $key    = $key    ?: (defined('MCRYPT_KEY')    ? MCRYPT_KEY    : '5h6h2u8559x8IU92G31PcR87NjnJ4KKa');
    $cipher = $cipher ?: (defined('MCRYPT_CIPHER') ? MCRYPT_CIPHER : MCRYPT_RIJNDAEL_256);
    $mode   = $mode   ?: (defined('MCRYPT_MODE')   ? MCRYPT_MODE   : MCRYPT_MODE_CBC);
    $block  = $block  ?: (defined('MCRYPT_BLOCK')  ? MCRYPT_BLOCK  : 32);
    $value  = base64_decode($value);
    $size   = mcrypt_get_iv_size($cipher, $mode);
    $iv     = substr($value, 0, $size);
    $value  = mcrypt_decrypt($cipher, $key, substr($value, $size), $mode, $iv);
    $pad    = ord(mb_substr($value, -1, 1, $charset));
    if ($pad and $pad < $block) {
        if (preg_match('/'.chr($pad).'{'.$pad.'}$/', $value)) {
            return mb_substr($value, 0, mb_strlen($value, $charset) - $pad, $charset);
        }
    }
    return false;
}
