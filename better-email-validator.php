<?php
/**
 * Plugin Name: Better Email Validator
 * Plugin URI: https://garridodiaz.com/category/wp/
 * Description:  Email address validation for WordPress registration and form submissions.
 * Version: 1.1
 * Author: Chema
 * Author URI: https://garridodiaz.com
 * License: GPL2
 */

defined('ABSPATH') or die('Slow down cowboy');


class BEVP_BetterEmailValidatorPlugin
{

    const MAIN_FILE = __FILE__;

    public function __construct()
    {
        // Initialize the plugin by adding hooks and actions
        add_filter('is_email', [$this, 'EmailValidator'], 10, 2);
        add_filter('plugin_row_meta', [$this, 'addPluginRowMeta'], 10, 2);
    }

    // Define the custom email validation function
    public function EmailValidator($result, $email)
    {
        return self::check($email) === TRUE ? TRUE : FALSE;
    }

    /**
     * Verify email format, DNS, and banned emails
     * @param  string $email 
     * @return mixed   bool true if correct / string
     */
    public static function check($email)
    {
        // Get the email to check up, clean it
        $email = filter_var($email, FILTER_SANITIZE_STRING);

        // 1 - Check valid email format using RFC 822
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) 
            return 'No valid email format';
            
        // Get email domain to work in next checks
        $email_domain = preg_replace('/^[^@]++@/', '', $email);

        // 2 - Check valid domain name
        if (filter_var($email_domain, FILTER_VALIDATE_DOMAIN) === FALSE) 
            return 'No valid domain format';

        // 3 - Check if it's from banned domains.
        $banned_domains = self::get_banned_domains();
        if (isset($banned_domains[$email_domain]) AND $banned_domains[$email_domain] !== null) {
            return 'Banned domain ' . $email_domain;
        }
              
        // 4 - Check DNS for MX records
        if ((bool) checkdnsrr($email_domain, 'MX') == FALSE)
            return 'DNS MX not found for domain '.$email_domain;

        // 5 - Wow, actually a real email! Congrats ;)
        return TRUE;
    }

    /**
     * Gets the array of not allowed domains for emails, reads from JSON stores file for 1 week
     * @return array 
     * @see banned domains https://github.com/ivolo/disposable-email-domains/blob/master/index.json
     * @return array
     */
    private static function get_banned_domains()
    {
        $banned_domains = [];
       
        $file = plugin_dir_path(self::MAIN_FILE).'banned-domains.php';

        if (!file_exists($file) OR (file_exists($file) AND filemtime($file) < strtotime('-1 month'))) {
            // If the file doesn't exist or is older than a month, regenerate it
            $banned_domains = wp_remote_get("https://rawgit.com/ivolo/disposable-email-domains/master/index.json");

            // we could read the CDN file
            if ( is_array( $banned_domains ) && ! is_wp_error( $banned_domains ) ) 
            {
                $banned_domains = json_decode($banned_domains['body'], true);

                //error reading the JSON file
                if ($banned_domains === null AND json_last_error() !== JSON_ERROR_NONE) 
                    return [];

                // Use array_filter with an inline closure function to remove invalid domains, just in case....
                $banned_domains = array_filter($banned_domains, function ($domain) {
                    return filter_var($domain, FILTER_VALIDATE_DOMAIN) !== false;
                }, ARRAY_FILTER_USE_KEY);

              
                // Store the banned domains as an associative array with domains as keys
                $banned_domains = array_fill_keys(array_keys(array_flip($banned_domains)), 0);

                file_put_contents($file, '<?php if ( ! defined( "ABSPATH" ) ) exit;  return ' . var_export($banned_domains, true) . ';', LOCK_EX);
            }
        }
        else// Load the domains from the cached PHP file
            $banned_domains = include($file);
        
        return $banned_domains;
    }


    /**
     * Add links to settings and sponsorship in plugin row meta.
     *
     * @param array $plugin_meta The existing plugin meta.
     * @param string $plugin_file The plugin file path.
     * @return array Modified plugin meta with added links.
     */
    public function addPluginRowMeta($plugin_meta, $plugin_file)
    {
        if (plugin_basename(self::MAIN_FILE) !== $plugin_file) {
            return $plugin_meta;
        }

        $settings_page_url = admin_url('options-general.php?page=bbpl-settings');

        $plugin_meta[] = sprintf(
            '<a href="%1$s"><span class="dashicons dashicons-star-filled" aria-hidden="true" style="font-size:14px;line-height:1.3"></span>%2$s</a>',
            'https://paypal.me/chema/10EUR',
            esc_html_x('Sponsor', 'verb', 'better-video')
        );

        return $plugin_meta;
    }
}

// Initialize the plugin
new BEVP_BetterEmailValidatorPlugin();


?>
