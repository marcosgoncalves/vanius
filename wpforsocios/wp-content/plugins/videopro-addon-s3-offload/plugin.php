<?php
/**
 *  Plugin Name: VideoPro - WP Offload S3 Lite Add-on
 *  Description: Support upload video to Amazon S3 in Videopro theme, using with WP Offload S3 Lite Plugin (https://wordpress.org/plugins/amazon-s3-and-cloudfront/)
 *  Author: CactusThemes
 *  Author URI: https://www.cactusthemes.com
 *  Version: 1.0.0
 *  Text Domain: videopro
 * @since 1.0.0
 */

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

//plugin is activated
if (is_plugin_active('amazon-web-services/amazon-web-services.php')
    && is_plugin_active('amazon-s3-and-cloudfront/wordpress-s3.php')
) {

    add_action('videopro_after_post_submission', 'videopro_update_as3cf_video_post_url', 10, 2);

    function videopro_update_as3cf_video_post_url($post_id, $posted_data)
    {
        global $as3cf;
        if (isset($posted_data['attachment_id'])) {
            $attachment_id = $posted_data['attachment_id'];

            $file_path = get_attached_file($attachment_id, true);

            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));
            $as3cf_url = $as3cf->get_attachment_url($attachment_id);

            update_post_meta($post_id, 'tm_video_file', $as3cf_url);
        }
    }
}