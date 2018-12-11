<?php
/*
 * step = 0  - Import categories & terms
 * step = 10 - Import pages
 * step = 25 - Import posts
 * step = 55 - Import menu items
 * step = 85 - Import theme options, setup mega menu
 * step = 90 - Import widget settings, widget logics, setup homepage * blog pages, do other things
 *
 * item_index is the index of item to start import in each step
 *
 *
 *
 * 
 */
class cactus_demo_pack_default extends cactus_demo_content{

	function __construct($base_uri, $base_dir){
		parent::__construct($base_uri, $base_dir);
		
		$this->name = 'default';
		$this->home_page = 'Homepage'; // title of the page which is set to Home Page
		$this->heading = esc_html__('Entertainment', 'cactus');
        $this->url = 'http://videopro.cactusthemes.com/entertainment/';
	}
	
	public function do_import($step = 0, $index = 0, $option_only = 0){
		$progress = parent::do_import($step, $index, $option_only);
		
		return $progress;
	}
	
	// override
	// return -1 if nothing needs to be done
	function do_others($step, $index){
		if($step == 10 || $step == 90){
			// we need to import plugin settings in advance (when $step = 10), before import posts (because there are custom post types)
			// then we make sure that we import plugin settings also at $step = 90 because users may choose to import options only
			$video_settings = file_get_contents(dirname(__FILE__) . '/data/video-settings.txt');
			$channel_settings = file_get_contents(dirname(__FILE__) . '/data/channel-settings.txt');
			$playlist_settings = file_get_contents(dirname(__FILE__) . '/data/playlist-settings.txt');
			update_option('ct_video_settings', $video_settings);
			update_option('ct_channel_settings', $channel_settings);
			update_option('ct_playlist_settings', $playlist_settings);
		} elseif($step == 0){
			// import metadata
			$cat_meta = file_get_contents(dirname(__FILE__) . '/data/category-meta.txt');
			$metas = unserialize($cat_meta);
			foreach($metas as $cat_slug => $data){
				$cat = get_term_by('slug', $cat_slug, 'category');
				if($cat){
					foreach($data as $key => $val){
						update_option($key . $cat->term_id, $val);
					}
				}
			}
			
			$cat_meta = file_get_contents(dirname(__FILE__) . '/data/video-series-meta.txt');
			$metas = unserialize($cat_meta);
			foreach($metas as $cat_slug => $data){
				$cat = get_term_by('slug', $cat_slug, 'video-series');
				if($cat){
					foreach($data as $key => $val){
						update_option($key . $cat->term_id, $val);
					}
				}
			}
		}
		
		return -1;
	}
	
	// override
	// return total of items in step 
	public function count_other_steps($step){
		return 0;
	}
	
	/**
	 * Process post metadata
	 *
	 */
	public function import_post_meta($post_id, $key, $value, $post_type = ''){
		$serialize_field = false;
		
		switch($key){
			case 'actor_id':
				// assign Posts to Actors, Channels and Playlists
				$serialize_field = true;
				$arr = array('actor_id' => 'ct_actor');
				
				break;
			case 'channel_id':
				// assign Posts to Actors, Channels and Playlists
				$serialize_field = true;
				$arr = array('playlist_id' => 'ct_playlist');
				
				break;
			case 'playlist_id':
				// assign Posts to Actors, Channels and Playlists
				$arr = array('channel_id' => 'ct_channel');
				$serialize_field = true;
				
				break;
			case 'playlist_channel_id':
				// assign Playlists to Channels
				$arr = array('playlist_channel_id' => 'ct_channel');
				$serialize_field = true;
				
				break;
			case 'tm_multi_link':
			case 'custom_review':
			case '_vc_post_settings':
				update_post_meta($post_id, $key, unserialize($value));
				break;
			case 'video_series_id':
				break;
			case 'video_series_slug':
				if($post_type == 'vseries_post'){
					// get random series
					$s = get_term_by('slug', $value, 'video-series');
					if($s){
						$id = $s->term_id;
						update_post_meta($post_id, 'video_series_id', $id);
						update_post_meta($post_id, 'video_series_slug', $value);
					}
					
					break;
				}
			default:
				update_post_meta( $post_id, $key, $value );
				break;
		}
		
		if($serialize_field){
			foreach($arr as $k => $post_type){
					$count = 5;
					$the_query = new WP_Query(array('post_type' => $post_type, 'posts_per_page' => $count, 'orderby' => 'rand'));
					if($the_query->have_posts()){
						$posts = $the_query->posts;
						$ids = array();
						foreach($posts as $post){
							array_push($ids, $post->ID);
						}
						
						update_post_meta($post_id, $k, $ids);
					}
				}
		}
		
		return true;
	}
	
	/**
	 * Override parent class
	 */
	public function configure_widget_options($widget_settings, $widget_index_mapping){
		cactus_import_widget_settings::set_widget_options('left-sidebar', 1, 'cactusthemes_style', 'style-4');
		cactus_import_widget_settings::set_widget_options('left-sidebar', 1, 'cactusthemes', 'small-padding');
		
		cactus_import_widget_settings::set_widget_options('right-sidebar', 4, 'cactusthemes_style', 'style-3');
		cactus_import_widget_settings::set_widget_options('right-sidebar', 4, 'cactusthemes', 'dark-div');
	}
    
    /**
     * Get page content and fields of a sample home
     *
     * @params
     *      $home - string - home slug
     */
    public function get_sample_home($home){
        $homes = array(
                'home-page-v1' => 
                      array (
                        'template' => 'page-templates/front-page.php',
                        'fields' => 
                        array (
                          '_edit_last' => '1',
                          '_wp_page_template' => 'page-templates/front-page.php',
                          '_wpb_vc_js_status' => 'false',
                          '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                          'page_sidebar' => '0',
                          'front_popular_conditions' => 'latest',
                          'popular_time_range' => 'all',
                          'popular_style' => 'layout_1',
                          'enable_popular_posts_button' => 'off',
                          'fr_page_content' => 'page_content',
                          'fr_page_order_by' => 'date',
                          'fr_page_post_count' => '9',
                          '_wpb_shortcodes_custom_css' => '.vc_custom_1464767998183{margin-top: 30px !important;margin-bottom: 30px !important;}.vc_custom_1464771326567{margin-top: 40px !important;margin-bottom: 40px !important;}',
                          'main_layout' => 'fullwidth',
                          'header_schema' => 'dark',
                          'main_navi_layout' => 'inline',
                          'main_navi_schema' => 'dark',
                          'factory_shortcodes_assets' => 'a:0:{}',
                          'main_navi_width' => 'full',
                          'search_box_expandable' => 'on',
                        ),
                        'title' => 'Home Page V1',
                        'content' => 'homes/home-page-v1.txt',
                      ),
                'home-page-v2' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1466580581920{margin-top: 60px !important;}.vc_custom_1466580588728{margin-top: 60px !important;}',
                      'page_sidebar' => 'right',
                      'main_layout' => 'boxed',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'front_page_bg' => 'a:6:{s:16:"background-color";s:7:"#E0E0E0";s:17:"background-repeat";s:0:"";s:21:"background-attachment";s:0:"";s:19:"background-position";s:0:"";s:15:"background-size";s:0:"";s:16:"background-image";s:0:"";}',
                      'factory_shortcodes_assets' => 'a:0:{}',
                      'main_navi_width' => 'full',
                      'header_background' => 'a:6:{s:16:"background-color";s:7:"#3c3c3c";s:17:"background-repeat";s:0:"";s:21:"background-attachment";s:0:"";s:19:"background-position";s:0:"";s:15:"background-size";s:0:"";s:16:"background-image";s:0:"";}',
                      'search_box_expandable' => 'on',
                    ),
                    'title' => 'Home Page V2',
                    'content' => 'homes/home-page-v2.txt',
                  ),
                'home-page-v3' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      'page_sidebar' => 'full',
                      'main_layout' => 'wide',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'factory_shortcodes_assets' => 'a:0:{}',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}',
                      'main_navi_width' => 'full',
                      'search_box_expandable' => 'on',
                    ),
                    'title' => 'Home Page V3',
                    'content' => 'homes/home-page-v3.txt',
                  ),
                'home-page-v4' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1470457709594{margin-top: 40px !important;}',
                      'page_sidebar' => 'right',
                      'main_layout' => 'wide',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'factory_shortcodes_assets' => 'a:0:{}',
                      'main_navi_width' => 'full',
                    ),
                    'title' => 'Home Page V4',
                    'content' => 'homes/home-page-v4.txt',
                  ),
                  'home-page-v5' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1466563983427{margin-top: 40px !important;}',
                      'page_sidebar' => 'both',
                      'main_layout' => 'fullwidth',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'inline',
                      'main_navi_schema' => 'dark',
                      'factory_shortcodes_assets' => 'a:0:{}',
                      'main_navi_width' => 'full',
                      'search_box_expandable' => 'off',
                    ),
                    'title' => 'Home Page V5',
                    'content' => 'homes/home-page-v5.txt',
                  ),
                  'home-page-v6' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      'factory_shortcodes_assets' => 'a:0:{}',
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '6',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1466563983427{margin-top: 40px !important;}',
                      'page_sidebar' => 'both',
                      'main_layout' => 'fullwidth',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'main_navi_width' => 'full',
                    ),
                    'title' => 'Home Page V6',
                    'content' => 'homes/home-page-v6.txt',
                  ),
                'home-page-v7' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      'factory_shortcodes_assets' => 'a:0:{}',
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1466563983427{margin-top: 40px !important;}',
                      'main_layout' => 'fullwidth',
                      'page_sidebar' => 'both',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'main_navi_width' => 'full',
                    ),
                    'title' => 'Home Page V7',
                    'content' => 'homes/home-page-v7.txt',
                  ),
                  'home-page-v8' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      'factory_shortcodes_assets' => 'a:0:{}',
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1470457709594{margin-top: 40px !important;}',
                      'main_layout' => 'wide',
                      'page_sidebar' => 'right',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'main_navi_width' => 'full',
                    ),
                    'title' => 'Home Page V8',
                    'content' => 'homes/home-page-v8.txt',
                  ),
                  'home-page-v9' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      'factory_shortcodes_assets' => 'a:0:{}',
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'false',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}',
                      'main_layout' => 'boxed',
                      'page_sidebar' => 'full',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'main_navi_width' => 'full',
                    ),
                    'title' => 'Home Page V9',
                    'content' => 'homes/home-page-v9.txt',
                  ),
                  'home-page-v10' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      'factory_shortcodes_assets' => 'a:0:{}',
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}.vc_custom_1466563983427{margin-top: 40px !important;}',
                      'main_layout' => 'boxed',
                      'page_sidebar' => 'right',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'main_navi_width' => 'full',
                    ),
                    'title' => 'Home Page V10',
                    'content' => 'homes/home-page-v10.txt',
                  ),
                'homepage-game-version' => 
                  array (
                    'template' => 'page-templates/front-page.php',
                    'fields' => 
                    array (
                      '_vc_post_settings' => 'a:1:{s:10:"vc_grid_id";a:0:{}}',
                      '_edit_last' => '3',
                      '_wp_page_template' => 'page-templates/front-page.php',
                      '_wpb_vc_js_status' => 'true',
                      'page_sidebar' => 'full',
                      'main_layout' => 'boxed',
                      'header_schema' => 'dark',
                      'main_navi_layout' => 'separeted',
                      'main_navi_schema' => 'dark',
                      'front_page_logo' => 'http://videopro.cactusthemes.com/v1/wp-content/uploads/2016/06/Videopro-logo-game-1X-1.png',
                      'front_page_bg' => 'a:6:{s:16:"background-color";s:7:"#d8d8d8";s:17:"background-repeat";s:9:"no-repeat";s:21:"background-attachment";s:5:"fixed";s:19:"background-position";s:13:"center center";s:15:"background-size";s:0:"";s:16:"background-image";s:89:"http://videopro.cactusthemes.com/v1/wp-content/uploads/2016/06/game-background-images.jpg";}',
                      'factory_shortcodes_assets' => 'a:0:{}',
                      '_wpb_post_custom_css' => '/*main color 1*/
                .bg-m-color-1, .btn-default.bt-style-1:visited:not(:hover), button.bt-style-1:visited:not(:hover), input[type=button].bt-style-1:visited:not(:hover), input[type=submit].bt-style-1:visited:not(:hover), .btn-default.subscribe, .cactus-nav-style-3 .cactus-only-main-menu .cactus-main-menu > ul > li:hover > a:after, .cactus-nav-style-5 .cactus-open-left-sidebar.right-logo.cactus-main-menu > ul > li > a.active > span, .ct-sub-w-title, .slider-toolbar-carousel .cactus-listing-config.style-2 .cactus-post-item.active .entry-content:before, .cactus-post-format-playlist .cactus-post-item.active:after, .channel-menu .channel-menu-item.active a:after, .easy-tab .tabs > li.active > a:after, .body-content .vc_tta.vc_general .vc_tta-tab.vc_active:after, .submitModal .textwidget .wpcf7 input[type="submit"]:not(:hover), .comming-soon-wrapper .wpcf7-form input[type="submit"]:not(:hover), #body-wrap .comming-soon-wrapper .gform_wrapper .gform_footer input.button:not(:hover), #body-wrap .comming-soon-wrapper .gform_wrapper .gform_footer input[type=submit]:not(:hover), .ct-shortcode-sliderv3.sliderv10 .slick-dots > li > button:hover, .ct-shortcode-sliderv3.sliderv10 .slick-dots > li.slick-active > button, .tab-control a.active:after, .ct-shortcode-sliderv3.sliderv8 .cactus-post-item.active:after, .btn-default.bt-style-1:not(:hover), button.bt-style-1:not(:hover), input[type=button].bt-style-1:not(:hover), input[type=submit].bt-style-1:not(:hover), .btn-default.bt-style-1:visited:not(:hover), button.bt-style-1:visited:not(:hover), input[type=button].bt-style-1:visited:not(:hover), input[type=submit].bt-style-1:visited:not(:hover), .cactus-nav-style-3 .cactus-only-main-menu .cactus-main-menu > ul > li.current-menu-ancestor > a:after, .cactus-nav-style-3 .cactus-only-main-menu .cactus-main-menu > ul > li.current-menu-item > a:after, .cactus-nav-style-3 .cactus-only-main-menu .cactus-main-menu > ul > li:hover > a:after {
                    background-color: #0a9e01 !important;
                }
                .m-color-1, body .wpcf7-not-valid-tip, body .wpcf7-response-output.wpcf7-validation-errors, body .wpcf7-response-output.wpcf7-mail-sent-ok, #body-wrap .gform_wrapper .validation_message, #body-wrap .gform_wrapper div.validation_error {
                    color: #0a9e01 !important;
                }
                .border-m-color-1, .cactus-main-menu > ul > li > ul li:first-child, .slider-toolbar-carousel .cactus-listing-config.style-2 .cactus-post-item.active .entry-content .picture-content > a:before {
                    border-color: #0a9e01 !important;
                }
                .svg-loading svg path, .svg-loading svg rect {
                    fill: #0a9e01 !important;
                }
                /*main color 1*/',
                      '_cs_replacements' => 'a:1:{s:13:"right-sidebar";s:4:"cs-1";}',
                      '_wpb_shortcodes_custom_css' => '.vc_custom_1466563983427{margin-top: 40px !important;}',
                      'main_navi_width' => 'full',
                      'front_page_logo_retina' => 'http://videopro.cactusthemes.com/v1/wp-content/uploads/2016/06/Videopro-logo-game-2X.png',
                    ),
                    'title' => 'Home Page Game',
                    'content' => 'homes/homepage-game-version.txt',
                  )
            );
            
        if(isset($homes[$home])){
            return $homes[$home];
        }
        
        return false;
    }
}