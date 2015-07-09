<?php
function TDRD_get_address() {
    return TDRD_get_protocol() . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}


function TDRD_get_protocol() {
    $protocol = 'http';
    if (isset($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) == "on") {
        $protocol .= "s";
    }

    return $protocol;
}
add_action('init','TDRD_redirect_me',1);
function TDRD_redirect_me()
{
    $user_request = str_ireplace(get_option('home'),'',TDRD_get_address());
    $user_request = rtrim($user_request,'/');
    $do_redirect='';
    //echo $redirects = get_option('301_redirects');
    global $wpdb;
    $tabel_name = $wpdb->prefix . 'tdrd_redirection';
    $select_url_data = "SELECT * FROM $tabel_name";
    $stored_url_resultset = $wpdb->get_results($select_url_data, ARRAY_A);
    //print_r($stored_url_resultset);
    //exit();
    foreach ($stored_url_resultset as $redirect) {
        $stored = $redirect['old_url'];
        $destination = $redirect['new_url'];
        if(urldecode($user_request) == rtrim($stored,'/')) {
                // simple comparison redirect
            $do_redirect = $destination;
        }
        if ($do_redirect !== '' && trim($do_redirect,'/') !== trim($user_request,'/')) {
                if (strpos($do_redirect,'/') === 0){
                        $do_redirect = home_url().$do_redirect;
                }
                header ('HTTP/1.1 301 Moved Permanently');
                header ('Location: ' . $do_redirect);
                exit();
        }
    }
}