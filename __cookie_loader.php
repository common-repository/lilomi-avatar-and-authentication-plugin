<?php

$lilomi_options = get_option('lilomi_options');
$lilomi_api_key = $lilomi_options['lilomi_api_key'];
$lilomi_secret_key = $lilomi_options['lilomi_secret_key'];

if (isset($_COOKIE['lilomi_' . $lilomi_api_key])) {
    $lilomi_cookie = $_COOKIE['lilomi_' . $lilomi_api_key];
    $lilomi_cookie_parts = preg_split("/\|/", $lilomi_cookie);
    $lilomi_cookie_data = array();
    $lilomi_id = null;
    $lilomi_username = null;

    //// Process the data in the cookie and determine validity
    if ($lilomi_cookie != 'disabled') {
        for ($i = 0; $i < count($lilomi_cookie_parts); $i++) {
            $part = preg_split('/=/', $lilomi_cookie_parts[$i]);
            $lilomi_cookie_data[$part[0]] = $part[1];
        }

        ksort($lilomi_cookie_data);
        $signature = $lilomi_cookie_data['signature'];

        unset($lilomi_cookie_data['signature']);

        $base_string = '';
        foreach ($lilomi_cookie_data as $key => $value) {
            $base_string .= $key . '=' . $value;
        }
        $base_string .= $lilomi_secret_key;

        if (md5($base_string) != $signature) {
            // Signature was not valid
            setCookie('lilomi_' . $lilomi_api_key, 'disabled', null, '/');
            wp_die("Lil'Omi authorization failed. Invalid verification signature.");
        } else {
            $lilomi_id = $lilomi_cookie_data['user_id'];
            $lilomi_username = $lilomi_cookie_data['username'];
        }
    }
}

?>