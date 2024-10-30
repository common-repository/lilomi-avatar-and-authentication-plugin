<?php

$searchFile = 'wp-blog-header.php';

for ($i = 0; $i < 10; $i++)
{
    if (file_exists($searchFile)) {
        require_once($searchFile);
        break;
    }
    $searchFile = "../" . $searchFile;
}

function lilomi_Login($lilomi_id, $lilomi_username)
{
    global $lilomi_id, $lilomi_username, $wpdb, $lilomi_api_key;

    //User login
    $user_login = $lilomi_username;

    $trim_user_login = sanitize_user($user_login, true);
    $trim_user_login = apply_filters('pre_user_login', $trim_user_login);
    $trim_user_login = trim($trim_user_login);

    $user_url = 'http://lilomi.com/users/' . $lilomi_username;


    $userdata = array(
        'user_pass' => wp_generate_password(),
        'user_login' => $user_login,
        'display_name' => $user_login,
        'user_url' => $user_url,
        'user_email' => "changeme".rand(1,1000000000)."@example.com"
    );
    
    if (!function_exists('wp_insert_user')) {
        include_once(ABSPATH . WPINC . '/registration.php');
    }
    $wpuid = lilomiuser_to_wpuser($lilomi_id);
    $wpuid = (int)$wpuid;
    
    if (!$wpuid) {
        $name_suffix = 1;
        while (username_exists($trim_user_login)) {
            $trim_user_login = $trim_user_login . $name_suffix;
            $userdata['user_login'] = $trim_user_login;
            $name_suffix += 1;
        }

        $wpuid = wp_insert_user($userdata);

        if (is_int($wpuid)) {
            update_usermeta($wpuid, 'lilomi_id', "$lilomi_id");
        } else {
            setCookie('lilomi_' . $lilomi_api_key, 'disabled', null, '/');
            wp_die('Error when creating user. May have encountered a duplicate email or other issue.');
        }

    }
    else
    {
        /* No name change support for now, to prevent name collisions
        $user_obj = get_userdata($wpuid);

        if ($user_obj->display_name != $user_login || $user_obj->user_url != $user_url) {
            $userdata = array(
                'ID' => $wpuid,
                'display_name' => $user_login,
                'user_url' => $user_url,
            );
            wp_update_user($userdata);

        }

        if ($user_obj->user_login != $trim_user_login) {
            if (!username_exists($trim_user_login)) {
                $q = sprintf("UPDATE %s SET user_login='%s' WHERE ID=%d",
                             $wpdb->users, $user_login_n_suffix, (int)$wpuid);
                if (false !== $wpdb->query($q)) {
                    update_usermeta($wpuid, 'nickname', $user_login);
                }
            } else
            {
                setCookie('lilomi_' . $lilomi_api_key, 'disabled', null, '/');
                wp_die('User name ' . $user_login . ' cannot be added.  It already exists.');
            }
        }
        */
    }

    if (is_int($wpuid)) {
        wp_set_auth_cookie($wpuid, true, false);
        wp_set_current_user($wpuid);
    } else {
        setCookie('lilomi_' . $lilomi_api_key, 'disabled', null, '/');
        wp_die('Failed to log in with Lilomi for some reason :( ');
    }
}

function lilomi_get_user_by_meta($meta_key, $meta_value)
{
    global $wpdb;
    $sql = "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '%s' AND meta_value = '%s'";
    return $wpdb->get_var($wpdb->prepare($sql, $meta_key, $meta_value));
}

function lilomiuser_to_wpuser($lilomi_id)
{
    return lilomi_get_user_by_meta('lilomi_id', $lilomi_id);
}

if ($lilomi_username) {
    lilomi_Login($lilomi_id, $lilomi_username);
}
if(strpos($_POST['redirectTo'], 'wp-login.php')) {
    header("Location: " . get_bloginfo('wpurl', 'raw'));
} else {
    header("Location: " . $_POST['redirectTo']);
}
exit;
?>