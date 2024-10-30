<?php

/*
Plugin Name: Lil'Omi Avatar & Authentication System
Plugin URI: http://www.lilomi.com
Description: Lil'Omi is an Avatar and Single Sign On system that adds cross-site Facebook Connect support to your WordPress or Buddypress site.
Version: 1.0
Author: Lilomi Inc.
License: GPL2
*/

// This file will read the user's cookie, authenticate it, and set the $lilomi_id and $cookie variables.
require '__cookie_loader.php';
include("JSON.php");

if (!class_exists('lilomi')) {
    class lilomi
    {
        //This is where the class variables go, don't forget to use @var to tell what they're for
        /**
         * @var string The options string name for this plugin
         */
        var $optionsName = 'lilomi_options';

        var $lilomiPath = '';
        /**
         * @var array $options Stores the options for this plugin
         */
        var $options = array();

        //Class Functions
        /**
         * PHP 4 Compatible Constructor
         */
        function lilomi()
        {
            $this->__construct();
        }

        /**
         * PHP 5 Constructor
         */
        function __construct()
        {

            //Initialize the options
            $this->getOptions();

            // Set the path correctly for development
            if (strpos(get_bloginfo('wpurl', 'raw'), 'local.lilomi.com') === FALSE) {
                $this->lilomiPath = 'http://scenes.lilomi.com';
            } else {
                $this->lilomiPath = 'http://local.lilomi.com';
            }

            if ($this->options['lilomi_owner_email']) {

                add_action('wp_print_scripts', array(&$this, "lilomi_scripts"), 10, 1);
                add_action('login_head', 'wp_enqueue_scripts', 1);
                add_action('login_head', 'wp_print_head_scripts', 2);

                add_action('register_form', array(&$this, "lilomi_login_form"), 10, 1);
                add_action('login_form', array(&$this, "lilomi_login_form"), 10, 1);
                add_action('bp_after_sidebar_login_form', array(&$this, "lilomi_login_form"), 10, 1);
                add_action('comment_form', array(&$this, "lilomi_login_form"), 10, 1);
                add_action('profile_personal_options', array(&$this, "lilomi_profile_options"), 10, 1);
                add_action('clear_auth_cookie', array(&$this, "lilomi_logout"));

                add_filter("get_avatar", array(&$this, 'lilomi_get_avatar'), 10, 4);
                add_filter("bp_core_fetch_avatar", array(&$this, 'lilomi_bp_get_avatar'), 10, 4);


            } else {
                if (!$_POST['lilomi_owner_email']) {
                    add_action('admin_notices', array(&$this, 'lilomi_activation_notice'), 10, 4);
                }
            }
            add_action("admin_menu", array(&$this, "admin_menu_link"));

        }

        function lilomi_activation_notice()
        {
            ?>
        <div id="message" class="updated fade">
            <p style="line-height: 150%"><?php printf(__("<strong>Lil'Omi is almost ready</strong>. You'll need to enter your email on the Lil'Omi <a href='%s'>settings page</a> to complete the activation.", 'buddypress'), admin_url('options-general.php?page=lilomi.php')) ?></p>
        </div>
        <?php

        }

        /* Filter the get avatar function to load the lilomi unless a user has specified another default avatar */
        function lilomi_bp_get_avatar($avatar, $params = '')
        {

            if (empty($params) || (strpos($avatar, 'bp-core/images') === FALSE && strpos($avatar, 'gravatar.com') === FALSE)) {
                return $avatar;
            }
            $lilomi_id = get_usermeta($params['item_id'], 'lilomi_id');
            if ($lilomi_id) {

                return str_replace(preg_replace('/.*src=["|\'](.*?)["|\'].*/i', "$1", $avatar), $this->lilomiPath . '/buddypress/avatars/' . +$lilomi_id, $avatar);
            } else {
                return $avatar;
            }

        }

        /* Filter one other get avatar function to load the lilomi unless a user has specified another default avatar */
        function lilomi_get_avatar($avatar, $id_or_email = '', $size = '32')
        {
            global $comment;

            if (is_object($comment)) {
                $id_or_email = $comment->user_id;
            }

            if (is_object($id_or_email)) {
                $id_or_email = $id_or_email->user_id;
            }

            $lilomi_id = get_usermeta($id_or_email, 'lilomi_id');
            if ($lilomi_id) {
                $avatar = "<img alt='' src='" . $this->lilomiPath . "/buddypress/avatars/{$lilomi_id}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
                return $avatar;
            } else {
                return $avatar;
            }
        }


        // Set the cookie to have a value 'disabled', that way a cookie is still present, but
        // it instructs the site to not display a login form.
        function lilomi_logout()
        {
            $cookie_name = 'lilomi_' . $this->options['lilomi_api_key'];
            setcookie($cookie_name, 'disabled', null, '/');
        }

        /**
         * Retrieves the plugin options from the database.
         * @return array
         */
        function getOptions()
        {
            //Don't forget to set up the default options
            if (!$theOptions = get_option($this->optionsName)) {
                $theOptions = array('default' => 'options');
                update_option($this->optionsName, $theOptions);
            }
            $this->options = $theOptions;
        }

        /**
         * Saves the admin options to the database.
         */
        function saveAdminOptions()
        {
            return update_option($this->optionsName, $this->options);
        }

        /*
         * Render the API key to be used by the javascript plugin
         * when fetching user information. This allows the information to be properly 
         * signed using a corresponding secret key.
         */

        function lilomi_scripts()
        {
            ?>

        <script>
            var lilomi_api_key = '<?php echo $this->options['lilomi_api_key'] ?>';
        </script>
        <script>var lilomi_path = '<?php echo $this->lilomiPath ?>';</script>
        <?php
            if (!is_admin()) {
                wp_register_script('lilomi', $this->lilomiPath . "/javascripts/buddypress.js", array('jquery'));
                wp_enqueue_script('lilomi');
            }
        }

        function lilomi_login_form()
        {

            echo $this->lilomi_markup();
        }


        function lilomi_markup()
        {
            $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $redirectTo = htmlspecialchars($current_url);

            ?>

        <?php if (!is_user_logged_in()) { ?>
        <script>
            jQuery(document).ready(function() {
                jQuery('.lilomi_login_button').click(function() {
                    LilOmi.clearCookie();
                    LilOmi.checkLogin();
                    jQuery('.lilomi_login').show();
                    jQuery('.lilomi_login_button').hide();
                });

                if (LilOmi.getCookie() != 'disabled') {
                    jQuery('.lilomi_login').show();
                    jQuery('.lilomi_login_button').hide();
                    LilOmi.checkLogin();
                } else {
                    jQuery('.lilomi_login_button').show();
                    jQuery('.lilomi_login').hide();
                }
                jQuery('body').append("<form class='lilomi_login_form' style='display:none' name='lilomi_login_form' method='post'" +
                        "action='<?php echo (content_url() . '/plugins/lilomi-avatar-and-authentication-plugin/_process_user.php') ?>'>" +
                        "<input type='hidden' name='redirectTo' value='<?php echo $redirectTo; ?>'></input>" +
                        "</form>");
            });

        </script>

        <div style='padding: 3px;'>
            <p><strong>Facebook Users</strong><br/>Login with a Lil'Omi avatar!</p>
            <input type='button' style='margin: 5px;width:150px' class='button-primary lilomi_login_button'
                   value="Login With Lil'Omi"/>

            <div class='lilomi_login' style='display:none'>
                <iframe frameborder="0" style='border:0px;width:150px;height:25px' scrolling='no'
                        src='<?php echo $this->lilomiPath . "/buddypress/auth/connect_frame?redirect=" . $redirectTo ?>'></iframe>
            </div>

        </div>
        <?php

        }

        }


        /*
        * Determines which login form pieces to display and how depending on the user session
        * Also manages establishing a session by making a query to LilOmi to try to get a session.
        */


        /* Called upon plugin activation. Makes an API call to LilOmi to establish a new api and secret key for this app */
        function setKeys()
        {
            if (!$this->options['lilomi_api_key'] || $this->options['lilomi_api_key'] = "") {

                $out = wp_remote_post($this->lilomiPath . "/buddypress/secret_keys?domain=" . get_bloginfo('wpurl', 'raw'));
                $response = json_decode($out['body'], true);

                $this->options['lilomi_api_key'] = $response['secret_key']['api_key'];
                $this->options['lilomi_secret_key'] = $response['secret_key']['secret_key'];

                if (!$this->options['lilomi_api_key'] || ($this->options['lilomi_api_key'] == "")) {
                    echo print_r($out);
                    wp_die("Failed to set api key :(. Please report this error to support@lilomi.com");
                }

                $this->saveAdminOptions();
            }
        }


        function lilomi_profile_options($user)
        {
            $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $redirectTo = htmlspecialchars($current_url);
            $lilomi_id = get_usermeta($user->ID, 'lilomi_id');
            if ($lilomi_id) {

                ?>
            <table class='form-table'>
                <tr>
                    <th scope='row'>Lil'Omi Avatar</th>
                    <td>
                        <img alt='' src='<?php echo $this->lilomiPath . "/buddypress/avatars/{$lilomi_id}" ?>'
                             class='avatar avatar-50' height='50' width='50'/>
                        <iframe frameborder="0" style='border:0px;width:150px;height:25px' scrolling='no'
                                src='<?php echo $this->lilomiPath . "/buddypress/avatars/edit_frame?redirect=" . $redirectTo ?>'></iframe>
                    </td>
                </tr>
            </table>


            <?php

            }
        }

        /**
         * @desc Adds the options subpanel
         */
        function admin_menu_link()
        {

            //If you change this from add_options_page, MAKE SURE you change the filter_plugin_actions function (below) to
            //reflect the page filename (ie - options-general.php) of the page your plugin is under!
            add_options_page('LilOmi', 'LilOmi', 10, basename(__FILE__), array(&$this, 'admin_options_page'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filter_plugin_actions'), 10, 2);
        }

        /**
         * @desc Adds the Settings link to the plugin activate/deactivate page
         */
        function filter_plugin_actions($links, $file)
        {
            //If your plugin is under a different top-level menu than Settiongs (IE - you changed the function above to something other than add_options_page)
            //Then you're going to want to change options-general.php below to the name of your top-level page
            $settings_link = '<a href="options-general.php?page=' . basename(__FILE__) . '">' . __('Settings') . '</a>';
            array_unshift($links, $settings_link); // before other links

            return $links;
        }

        /**
         * Adds settings/options page
         */
        function admin_options_page()
        {
            ?>
        <div class="wrap">
            <table style="margin-top: 20px; margin-bottom: 5px;">
                <tr valign="middle">

                    <td>
                        <div style="font-size: 18px;">Lil'Omi</div>
                    </td>
                </tr>

            </table>

            <?php
                                                                        if ($_POST['lilomi_set_email']) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'lilomi-update-options')) die('Whoops! There was a problem with the data you posted. Please go back and try again.');


            if (!$this->options['lilomi_api_key'] || $this->options['lilomi_api_key'] == "") {
                $this->setKeys();
            }

            $body = array(
                'owner_email' => $_POST['lilomi_owner_email']
            );
            $url = $this->lilomiPath . "/buddypress/secret_keys/update_email?id=" . $this->options['lilomi_secret_key'] . "&owner_email=" . $_POST['lilomi_owner_email'];
            $request = new WP_Http;
            $result = $request->request($url);
            if (is_wp_error($result)) {
                echo "ERROR: " . $result->get_error_message();
                echo "<br/>Please report this error to support@lilomi.com.";
            }

            if ($result['errors']) {
                echo "fa";
                wp_die($result['errors']);
            }
            $response = json_decode($result['body'], true);

            if ($response['error']) {
                echo '<div class="updated"><p>Error: ' . $response['error'] . '!</p></div>';
            } else {
                $this->options['lilomi_owner_email'] = $response['secret_key']['owner_email'];

                $this->saveAdminOptions();
                echo '<div class="updated"><p>Success! Your changes were successfully saved!</p></div>';
            }
        }
            ?>

            <div style="margin-left: 25px; margin-right: 25px;">

            </div>
            <form method="post" id="lilomi_options">
                <?php wp_nonce_field('lilomi-update-options'); ?>
                <table width="100%" cellspacing="2" cellpadding="5" class="form-table">
                    <tr valign="top">
                        <th><b>Your email<br/></b>(Will be used to contact you if any required updates are released)
                        </th>
                        <td><input type="text" size="60" name="lilomi_owner_email"
                                   value="<?php echo $this->options['lilomi_owner_email'] ?>"></input><br/></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <input type='submit' name='lilomi_set_email' value='Save'/>
                            Have questions or need help? Contact support@lilomi.com
                        </td>
                    </tr>
                </table>
            </form>
            <?php

        }
    } //End Class
} //End if class exists statement

//instantiate the class
if (class_exists('lilomi')) {
    global $lilomi_var;
    $lilomi_var = new lilomi();
}

// Future-friendly json_encode
if (!function_exists('json_encode')) {
    function json_encode($data)
    {
        $json = new Services_JSON();
        return ($json->encode($data));
    }
}

// Future-friendly json_decode
if (!function_exists('json_decode')) {
    function json_decode($data)
    {
        $json = new Services_JSON();
        return ($json->decode($data));
    }
}

?>
