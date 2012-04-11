<?php
class postmark {
    function attach($path_or_content, $name = null, $mime = null) {
        if ($mime == null) {
            $fnfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = is_file($path_or_content) ?
                finfo_file($fnfo,   $path_or_content) or 'application/octet-stream' :
                finfo_buffer($fnfo, $path_or_content) or 'text/plain';
            finfo_close($fnfo);
        }
        if ($name == null) $name = is_file($path_or_content) ? basename($path_or_content) : 'attachment.txt';
        $attachment = new stdClass;
        $attachment->Name = $name;
        $attachment->ContentType = $mime;
        $attachment->Content = base64_encode($isFile ? file_get_contents($path_or_content) : $path_or_content);
        if (!isset($this->Attachments)) $this->Attachments = array();
        $this->Attachments[] = $attachment;
    }
    function send($api_key = 'POSTMARK_API_TEST') {
        $ch = curl_init('http://api.postmarkapp.com/email');
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($this),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                "X-Postmark-Server-Token: {$api_key}"
        )));
        echo json_encode($this);
        $ret = curl_exec($ch);
        curl_close($ch);
        return json_decode($ret);
    }
}
