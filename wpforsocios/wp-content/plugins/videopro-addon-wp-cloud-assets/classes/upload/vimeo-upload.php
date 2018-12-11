<?php

/**
 *  Version: 1.0.0
 *  Text Domain: WPStylish-wp-cloud
 *  Vimeo API : 3.4
 *  @since 1.0.0
 */
class VideoPro_CLOUD_VIMEO_UPLOAD
{

    public function __construct()
    {

    }

    function wp_cloud_vimeo_upload($post_id)
    {

        $video_file_url = get_post_meta($post_id, 'tm_video_file', true);

        $file_size = $this->curl_get_file_size($video_file_url);

        $path_parts = pathinfo($video_file_url);
        $fileName = $path_parts['basename'];
        $fileType = $path_parts['extension'];

        $file_information = array(
            'name' => !empty( $_POST[ 'post-title' ] ) ? $_POST[ 'post-title' ] : $fileName,
            'description' => !empty( $_POST[ 'description' ] ) ? $_POST[ 'description' ] : ''
        );

        $all_files = array();

        $video_url = $this->vimeo_upload_file($video_file_url, $file_size, $file_information);

        if (!isset($video_url['error'])) {
            $all_files['fileName'] = $fileName;
            $all_files['fileType'] = $fileType;
            $all_files['fileURL']  = $video_url;
        }

        return $all_files;
    }

    function vimeo_upload_file($file_url, $file_size, $file_name)
    {

        $ticket = $this->create_upload_ticket( $file_name );

        if( !empty( $ticket['error'] ) ){
			return "{$ticket['error']} - {$ticket['developer_message']}";
		}

        if ($ticket['user']['upload_quota']['space']['free'] < $file_size) {
            return __('Your account doesn\'t have enough spaces to upload this video', 'WPStylish-wp-cloud');
        }

        $content = array(
            'file_data' => file_get_contents($file_url)
        );

        if( isset( $ticket['upload_link_secure'] ) ){
			$upload_link = $ticket['upload_link_secure'];
		}elseif( isset( $ticket['upload_link'] ) ){
			$upload_link = $ticket['upload_link'];
		}elseif( isset( $ticket['upload']['upload_link'] ) ){
			$upload_link = $ticket['upload']['upload_link'];
		}else{
			return __('Link not found in upload ticket', 'WPStylish-wp-cloud');
		}

        $result = $this->post_curl( $upload_link, array(), $content );

        if( isset( $ticket['link'] ) ){
			return $ticket['link'];
		}

        $re = '/vimeo.com\/([0-9]+)/';
        preg_match($re, $result, $matches);

        if (isset($matches['0'])) {
            return 'https://' . $matches['0'];
        }

        return false;

    }

    function get_user()
    {

        $url = 'https://api.vimeo.com/me/';
        $user = json_decode($this->get_curl($url), true);

        return $user;

    }

    function check_access_token()
    {

        $user = $this->get_user();

        if (isset($user['uri'])) {
            return true;
        }

        return false;

    }

    function user_free_space($user)
    {

        if (isset($user['upload_quota']['space']['free'])) {
            return $user['upload_quota']['space']['free'];
        }

        return false;

    }

    function create_upload_ticket( $file_information = array() )
    {

        $url = 'https://api.vimeo.com/me/videos';

        return json_decode($this->post_curl($url, array(), $file_information), true);

    }

    function post_curl($url, $headers = array(), $content = '')
    {

        return $this->vimeo_build_curl('POST', $url, $headers, $content);

    }

    function get_curl($url, $headers = array(), $content = '')
    {

        return $this->vimeo_build_curl('GET', $url, $headers, $content);

    }

    function vimeo_build_curl($method, $url, $headers = array(), $content = '')
    {

        $token = get_option('wp_cloud_vimeo_token');

        if ($token == false) {
            return false;
        }

        $ch = curl_init();

        if( !isset( $content['file_data'] ) ){
            $header = array_merge( $headers, array(
                "Authorization: Bearer $token",
            ) );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        if( !empty( $content ) ){
            if( isset( $content['file_data'] ) ){
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $content );
			}else{
				curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $content ) );
			}
        }
        $data = curl_exec($ch);

        if($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            echo "cURL error ({$errno}):\n {$error_message}";
            die();
        }

        curl_close($ch);
        return $data;

    }

    function curl_get_file_size($url)
    {
        // Assume failure.
        $result = -1;

        $curl = curl_init($url);

        // Issue a HEAD request and follow any redirects.
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt( $curl, CURLOPT_USERAGENT, get_user_agent_string() );

        $data = curl_exec($curl);
        curl_close($curl);

        if ($data) {
            $content_length = "unknown";
            $status = "unknown";

            if (preg_match("/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches)) {
                $status = (int)$matches[1];
            }

            if (preg_match("/Content-Length: (\d+)/", $data, $matches)) {
                $content_length = (int)$matches[1];
            }

            // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
            if ($status == 200 || ($status > 300 && $status <= 308)) {
                $result = $content_length;
            }
        }

        return $result;
    }
}

$GLOBALS['videoPro_cloud_vimeo_upload'] = new VideoPro_CLOUD_VIMEO_UPLOAD();
