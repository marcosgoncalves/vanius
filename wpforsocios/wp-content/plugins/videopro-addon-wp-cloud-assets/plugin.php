<?php
/**
 *  Plugin Name: VideoPro - WP Cloud Assets Add-on
 *  Description: Support upload video to cloud in Videopro theme, using with WP Cloud Assets Plugin (https://codecanyon.net/item/wp-cloud-upload-media-files-to-cdn-hosting/20016696?ref=cactusthemes)
 *  Author: CactusThemes
 *  Author URI: https://www.cactusthemes.com
 *  Version: 1.1
 *  Text Domain: videopro
 */

/**
 * since 1.1, requuire WP Cloud Assets plugin 1.2.1
 **/

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

//check if plugin is activated
if (is_plugin_active('wp-cloud-assets/wp-cloud-assets.php')) {

    if (!class_exists('videoproArrayToXml')) {
        require_once(plugin_dir_path(__FILE__) . '/classes/videoproArrayToXml.php');
    }
    require_once(plugin_dir_path(__FILE__) . '/classes/upload/amazon-upload.php');
    require_once(plugin_dir_path(__FILE__) . '/classes/upload/dropbox-upload.php');
    require_once(plugin_dir_path(__FILE__) . '/classes/upload/vimeo-upload.php');

    function videopro_wpca_addon_scripts()
    {
        if (is_admin()) {
            wp_enqueue_script('videopro-child-theme-js', plugin_dir_url(__FILE__) . '/assets/js/script.js', array('jquery'), '', true);
            wp_enqueue_script('videopro-cloud-amazon', plugin_dir_url(__FILE__) . '/assets/js/amazon.js', array('jquery'), '', true);
            wp_enqueue_script('videopro-cloud-dropbox', plugin_dir_url(__FILE__) . '/assets/js/dropbox.js', array('jquery'), '', true);
            wp_enqueue_script('videopro-cloud-vimeo', plugin_dir_url(__FILE__) . '/assets/js/vimeo.js', array('jquery'), '', true);
            wp_enqueue_style('videopro-child-theme-css', plugin_dir_url(__FILE__) . '/assets/css/admin.css');

            wp_localize_script('videopro-child-theme-js', 'videopro_child_cloud', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'home_url' => get_home_url(),
                'plupload_init' => array(
                    'runtimes' => 'html5,flash,silverlight,html4',
                    'browse_button' => '',
                    'container' => 'add-images-ui',
                    'drop_element' => 'add-images-ui',
                    'file_data_name' => 'wp-cloud-images-upload',
                    'multiple_queues' => true,
                    'max_file_size' => wp_max_upload_size() . 'b',
                    'prevent_duplicates' => true,
                    'url' => admin_url('admin-ajax.php'),
                    'flash_swf_url' => includes_url('js/plupload/plupload.flash.swf'),
                    'silverlight_xap_url' => includes_url('js/plupload/plupload.silverlight.xap'),
                    //'filters' => array(array('title' => esc_html__('Allowed Files'), 'extensions' => '*')),
                    'filters' => array(
                        'mime_types' => array(array('title' => esc_html__('Allowed Files', 'cactus'), 'extensions' => 'mov,avi,mpg,flv,mp4,wmv')),
                    ),
                    'multipart' => true,
                    'urlstream_upload' => true,
                    'multi_selection' => false,
                    'multipart_params' => array(
                        'post_id' => get_the_ID(),
                        '_wpnonce' => wp_create_nonce('wp-cloud-post-form'),
                        'action' => 'wp-cloud_plupload_action'
                    ),
                )
            ));
        }
    }

    add_action('admin_enqueue_scripts', 'videopro_wpca_addon_scripts');

    function videopro_upload_post_video_to_amazonS3($post_id, $bucket, $attachment_id)
    {
        global $videoPro_cloud_amazon_upload;

        $data = $videoPro_cloud_amazon_upload->wp_cloud_amazonS3_action($bucket, $post_id);

        if (is_array($data) && isset($data['ObjectURL'])) {
            //$uploads = wp_upload_dir();
            //unlink($uploads['path'] . '/' . $data['fileName']);
            wp_delete_attachment($attachment_id);
            update_post_meta($post_id, 'tm_video_file', $data['ObjectURL']);
        }
    }

    function videopro_upload_post_video_to_dropbox($post_id, $attachment_id)
    {
        global $videoPro_cloud_dropbox_upload;

        $data = $videoPro_cloud_dropbox_upload->wp_cloud_dropbox_upload($post_id);

        if (is_array($data) && isset($data['fileURL'])) {
            wp_delete_attachment($attachment_id);
            update_post_meta($post_id, 'tm_video_file', $data['fileURL']);
        }
    }

    function videopro_upload_post_video_to_vimeo($post_id, $attachment_id)
    {
        global $videoPro_cloud_vimeo_upload;

        $data = $videoPro_cloud_vimeo_upload->wp_cloud_vimeo_upload($post_id);

        if (is_array($data) && isset($data['fileURL'])) {
            wp_delete_attachment($attachment_id);
            update_post_meta($post_id, 'tm_video_url', $data['fileURL']);
            delete_post_meta($post_id, 'tm_video_file');
        }
    }

    if (!function_exists('videopro_xml_to_array')) {
        function videopro_xml_to_array($xmlstring)
        {
            $xml = simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);
            return $array;
        }
    }

    if (!function_exists('videopro_in_array_r')) {
        function videopro_in_array_r($needle, $haystack, $strict = false)
        {
            foreach ($haystack as $item) {
                if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && videopro_in_array_r($needle, $item, $strict))) {
                    return true;
                }
            }
            return false;
        }
    }

    function videopro_upload_to_cloud_cvs_optionpage_xml_file_filter($xmlstring)
    {
        $array_data = videopro_xml_to_array($xmlstring);

        global $wpstylish_wp_cloud;
        $available_host = $wpstylish_wp_cloud->get_available_host();
        if (!empty($available_host)) {
            if (isset($available_host['google'])) unset($available_host['google']);
            if (isset($available_host['imgur'])) unset($available_host['imgur']);
        }
        if (!empty($available_host)) {
            $options = array();
            $options[] = array(
                '@attributes' => array(
                    'value' => esc_attr('none'),
                    'text' => esc_html__('None', 'cactus')
                )
            );

            foreach ($available_host as $host) {
                $options[] = array(
                    '@attributes' => array(
                        'value' => esc_attr($host['value']),
                        'text' => esc_html__($host['text'], 'cactus')
                    )
                );
            }

            global $videoPro_cloud_amazon_upload;
            $buckets = array();
            $get_amazon_buckets = $videoPro_cloud_amazon_upload->amazon_get_all_buckets();
            if (!empty($get_amazon_buckets)) {
                foreach ($get_amazon_buckets['Buckets'] as $bucket) {
                    $buckets[] = array(
                        '@attributes' => array(
                            'value' => $bucket['Name'],
                            'text' => $bucket['Name']
                        )
                    );
                }
            }

            $array_data['tab'][] = array(
                '@attributes' => array(
                    'label' => 'Upload video to Cloud server',
                    'icon' => 'upload',
                ),
                'group' => array(
                    'fields' => array(
                        array(
                            '@attributes' => array(
                                'label' => 'Select cloud to upload video'
                            ),
                            'option' => array(
                                '@attributes' => array(
                                    'id' => 'cloud_to_upload',
                                    'type' => 'select',
                                    'default' => 'none',
                                    'tooltip' => 'Cloud to upload',
                                ),
                                'select' => $options
                            ),
                            'description' => 'Select your cloud to upload video, example: AmazonS3, Dropbox, Vimeo,... (Note that this functionality only be used with plugin WP Cloud Assets)',
                        ),
                        array(
                            '@attributes' => array(
                                'label' => 'Select AmazonS3 Bucket'
                            ),
                            'option' => array(
                                '@attributes' => array(
                                    'id' => 'amazon_bucket',
                                    'type' => 'select',
                                    'default' => '',
                                    'tooltip' => 'Amazon Bucket',
                                ),
                                'select' => $buckets
                            ),
                            'description' => 'Select AmazonS3 Bucket, if you want to upload video to AmazonS3.',
                            'condition' => array(
                                '@attributes' => array(
                                    'expression' => 'cloud_to_upload:is(amazon)',
                                )
                            )
                        ),
                        array(
                            '@attributes' => array(
                                'label' => 'Where to upload video via Front-end submission'
                            ),
                            'option' => array(
                                '@attributes' => array(
                                    'id' => 'frontend_submission_upload_type',
                                    'type' => 'select',
                                    'default' => '0',
                                    'tooltip' => '',
                                ),
                                'select' => array(
                                    array(
                                        '@attributes' => array(
                                            'value' => '0',
                                            'text' => 'Upload to your server (self-hosted)',
                                        )
                                    ),
                                    array(
                                        '@attributes' => array(
                                            'value' => '1',
                                            'text' => 'Upload to selected cloud',
                                        )
                                    ),
                                )
                            ),
                            'description' => 'Select place to upload video via Frontend submission. Note that it might takes longer when you choose to upload video to cloud server',
                        ),

                        array(
                            '@attributes' => array(
                                'label' => 'Upload video when create new post'
                            ),
                            'option' => array(
                                '@attributes' => array(
                                    'id' => 'upload_video_for_new_post',
                                    'type' => 'select',
                                    'default' => '0',
                                    'tooltip' => '',
                                ),
                                'select' => array(
                                    array(
                                        '@attributes' => array(
                                            'value' => '0',
                                            'text' => 'Always upload to your selected cloud as setting',
                                        )
                                    ),
                                    array(
                                        '@attributes' => array(
                                            'value' => '1',
                                            'text' => 'Select cloud to upload manually',
                                        )
                                    ),
                                )
                            ),
                            'description' => 'Choose type of uploading video to cloud when create new video post.',
                        ),
                    ),
                )
            );

            $xmlstring = videoproArrayToXml::convert($array_data, 'options');
            return $xmlstring;
        }

        return $xmlstring;
    }

    add_filter('ct_video_settings_optionpage_xml_file', 'videopro_upload_to_cloud_cvs_optionpage_xml_file_filter');

    function videopro_upload_video_to_cloud_from_fronted_submission($post_id, $posted_data)
    {
        $frontend_submission_upload_type = osp_get('ct_video_settings', 'frontend_submission_upload_type');
        if ($frontend_submission_upload_type == '1') {
            $cloud_to_upload = osp_get('ct_video_settings', 'cloud_to_upload');
            $amazon_bucket   = osp_get('ct_video_settings', 'amazon_bucket');
            $attachment_id   = isset( $posted_data['attachment_id'] ) ? $posted_data['attachment_id'] : '';

            if( empty( $attachment_id ) ){
                return;
            }

            switch ($cloud_to_upload) {
                case "amazon":
                    videopro_upload_post_video_to_amazonS3($post_id, $amazon_bucket, $attachment_id);
                    break;
                case "dropbox":
                    videopro_upload_post_video_to_dropbox($post_id, $attachment_id);
                    break;
                case "vimeo":
                    videopro_upload_post_video_to_vimeo($post_id, $attachment_id);
                    break;
                default:
                    echo "";
            }
        }
    }

    add_action('videopro_after_post_submission', 'videopro_upload_video_to_cloud_from_fronted_submission', 10, 2);

    function videopro_upload_video_to_cloud_modal()
    { ?>
        <div class="wp-cloud-modal" id="videopro-cloud-gallery-modal" tabindex="-1" role="dialog">
            <input id="upload-type" type="hidden">
            <div class="wp-cloud-modal-dialog wp-cloud-modal-lg unique-modal-lg wp-cloud-imgur-modal-lg"
                 role="document">
                <div class="wp-cloud-modal-content">
                    <div class="wp-cloud-modal-header">
                        <h3 class="wp-cloud-modal-title"><?php esc_html_e('Video Cloud Uploader', 'cactus'); ?></h3>
                    </div>
                    <div id="add-images-ui" class="image-upload">
                        <div id="videopro-gallery-unique-id" class="images-container" data-interval="false">
                            <div id="drag-drop-area">
                                <div class="item demo">
                                    <strong><?php esc_html_e('Drop or Select Videos here', 'cactus'); ?></strong>
                                    </br>
                                    <button class="videopro-cloud-plupload-button">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wp-cloud-modal-body">

                        <div id="videopro-cloud-preview-image">
                            <ul></ul>
                        </div>
                        <div id="videopro-cloud-preview-image-info">
                            <?php
                            global $wpstylish_wp_cloud;
                            $available_host = $wpstylish_wp_cloud->get_available_host();
                            if (!empty($available_host)) {
                                if (isset($available_host['google'])) unset($available_host['google']);
                                if (isset($available_host['imgur'])) unset($available_host['imgur']);
                            }

                            $upload_video_for_new_post = osp_get('ct_video_settings', 'upload_video_for_new_post');
                            if ($upload_video_for_new_post == '1') { ?>
                                <p>
                                    <label for="upload-to"><?php esc_html_e('Upload to', 'cactus'); ?></label>
                                    <br>
                                    <?php
                                    if (empty($available_host)) { ?>
                                        <span class="description">&nbsp;( To upload, you need to add settings <a
                                                    href="<?php echo admin_url('admin.php?page=wp-cloud-settings'); ?>"
                                                    target="_blank">here. Support upload video to AmazonS3, Dropbox, Vimeo.</a>  )</span>
                                    <?php } ?>
                                    <br>
                                    <select id="videopro-cloud-upload-option">
                                        <option value="<?php echo esc_attr('none'); ?>"><?php esc_html_e('None', 'cactus'); ?></option>
                                        <?php if (!empty($available_host)) {
                                            foreach ($available_host as $host) { ?>
                                                <option value="<?php echo esc_attr($host['value']); ?>"><?php esc_html_e($host['text'], 'cactus'); ?></option>
                                            <?php }
                                        } ?>
                                    </select>
                                </p>
                                <p id="videopro-cloud-album-pick">
                                    <label for="videopro-cloud-upload-album"><?php esc_html_e('Album', 'cactus'); ?></label>
                                    <br>
                                    <select id="videopro-cloud-upload-album">
                                    </select>
                                </p>
                            <?php } else {
                                $cloud_to_upload = osp_get('ct_video_settings', 'cloud_to_upload');
                                $amazon_bucket = osp_get('ct_video_settings', 'amazon_bucket');
                                if ($cloud_to_upload && $cloud_to_upload != 'none') { ?>
                                    <p>
                                        <label for="upload-to"><?php esc_html_e('Upload to selected cloud as setting.', 'cactus'); ?></label>
                                        <br>
                                        <select id="videopro-cloud-upload-option" style="display: none">
                                            <option value="<?php echo $cloud_to_upload; ?>"
                                                    selected="selected"><?php echo $cloud_to_upload; ?></option>
                                        </select>
                                    </p>
                                    <p id="videopro-cloud-album-pick" style="display: none">
                                        <select id="videopro-cloud-upload-album">
                                            <option value="<?php echo $amazon_bucket; ?>"
                                                    selected="selected"><?php echo $amazon_bucket; ?></option>
                                        </select>
                                    </p>
                                <?php } else { ?>
                                    <p>
                                        <label for="upload-to"><?php esc_html_e('Please config at least one cloud server to upload video. Support upload video to AmazonS3, Dropbox, Vimeo.', 'cactus'); ?></label>
                                    </p>
                                <?php } ?>
                            <?php } ?>
                            <div class="save-info">
                                <h3>File Info</h3>
                                <p>
                                    <label for="videopro-cloud-upload-name"><?php esc_html_e('Name', 'cactus'); ?></label>
                                    <br>
                                    <input id="videopro-cloud-upload-name" class="large-text">
                                </p>
                                <p>
                                    <label for="videopro-cloud-upload-title"><?php esc_html_e('Title', 'cactus'); ?></label>
                                    <br>
                                    <input id="videopro-cloud-upload-title" class="large-text">
                                </p>
                                <p>
                                    <label for="videopro-cloud-upload-description"><?php esc_html_e('Description', 'cactus'); ?></label>
                                    <br>
                                    <textarea id="videopro-cloud-upload-description"></textarea>
                                </p>
                                <button type="button" id="videopro-cloud-save-image-info" class="btn btn-default"
                                        data-dismiss="modal"><?php esc_html_e('Save', 'cactus'); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="wp-cloud-modal-footer">
                        <button id="videopro-cloud-insert-video-button" type="button"
                                class="btn btn-primary"><?php esc_html_e('Upload and Insert URL', 'cactus'); ?></button>
                        <button type="button" id="videopro-cloud-dismiss-modal" class="btn btn-default"
                                data-dismiss="modal"><?php esc_html_e('Close', 'cactus'); ?></button>
                        <br>
                    </div>
                </div>
            </div>
        </div>
    <?php }

    add_action('admin_footer', 'videopro_upload_video_to_cloud_modal');
}