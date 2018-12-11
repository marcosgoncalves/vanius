<?php
defined( 'ABSPATH' ) or die( 'You cannot access this script directly' );

class cactus_demo_importer{
	private $packages = array();
	private $base_dir = '';
	private $base_uri = '';

	function __construct($packages, $base_dir, $base_uri){
		
		$this->packages = $packages;
		$this->base_dir = $base_dir;
		$this->base_uri = $base_uri;

		require_once 'cactus_demo_content.php';
		
		add_action( 'wp_ajax_cactus_import', array($this, 'do_import' ));
        add_action( 'wp_ajax_cactus_install_home', array($this, 'do_install_home' ));
		
		add_action( 'init', array($this,'init'), 1000);
		add_action( 'admin_enqueue_scripts', array($this, 'admin_enqueue_scripts_styles') );
		
		add_action('admin_menu', array($this,'register_admin_menu'));
	}
    
    /**
     * ajax call, to install a pre-defined homepage.
     */
    function do_install_home($name = ''){
        if($name == ''){
            $name = $_POST['name'];
        }
        
        $pack = $this->base_dir . "packages/default/cactus_demo_pack_default.php";
		require_once $pack;
        
        $importer = new cactus_demo_pack_default('', '');
        
        $home = $importer->get_sample_home($name);
        if($home){
            $content = '';
            
            // replace default cat slug with existing categories
            $cats = get_categories(array('hierarchical' => false, 'orderby' => count, 'order' => 'DESC'));
            $replacements = array('{cat-1}' => '', '{cat-2}' => '', '{cat-3}' => '', '{cat-4}' => '', '{cat-5}' => '', '{cat-6}' => '');
            if(count($cats) > 0){
                foreach($replacements as $key => $value){
                    $index = rand(0, count($cats) - 1);
                    $replacements[$key] = $cats[$index]->slug;
                }
            }
            
            
            
            if(file_exists($this->base_dir . "packages/default/data/" . $home['content'])){
                $content = file_get_contents($this->base_dir . "packages/default/data/" . $home['content']);
                
                foreach($replacements as $key => $value){
                    $content = str_replace($key, $value, $content);
                    
                }
                    
            }
            
            $args = array(
                        'post_title' => $home['title'],
                        'post_type' => 'page',
                        'post_status' => 'publish',
                        'post_content' => $content
                    );
            
            $id = wp_insert_post($args);
        
            if($id){
                if($id){
                    if(isset($home['template'])){
                        update_post_meta( $id, '_wp_page_template', $home['template'] );
                    }
                    
                    if(isset($home['fields']) && is_array($home['fields'])){
                        foreach($home['fields'] as $key => $value){
                            if(in_array($key, array('_vc_post_settings', 'front_page_bg', '_cs_replacements'))){
                                update_post_meta( $id, $key, unserialize($value) );
                            } else {
                                update_post_meta( $id, $key, $value );
                            }
                            
                        }
                    }
                    
                    $mapping = cactus_import_widget_settings::import($this->base_uri . "packages/default/data/homes/widget_data.json", $replacements);
                    
                    // replace string in widget logic
                    $post = get_post($id);
                    $replacements2 = array($name => $post->post_name);
                    
                    $importer->import_widget_logic($this->base_uri . "packages/default/data/homes/widget_logic.txt", $mapping, $replacements2);
                }
                
                $result = array('status' => 1, 'url' => get_permalink($id));
            } else {
                $result = array('status' => 0, 'message' => 'Failed :(');
            }
        } else {
            $result = array('status' => 0, 'message' => 'Data not found');
        }
        
        echo json_encode($result);
        wp_die();
    }
	
	function register_admin_menu(){
		$import_page = add_submenu_page('tools.php', 'VideoPro - Export Taxonomy Metadata', 'VideoPro - Export Taxonomy Metadata', 'administrator', 'ct_video_settings_taxonomy', array($this, '_display_export_import_setting_page'));
	}
	
	function _display_export_import_setting_page() {
		ob_start();
?>
	<div class="wrap">
		<h1>VideoPro - Export Taxonomy Metadata</h1>
		<p>Copy content of settings into a text file to export. Paste content of settings into appropriate textbox and click "Import"</p>
		<?php
		
		$cats = get_categories(array('hide_empty' => 0));
		$cat_meta_list = array('cat_sidebar_', 'cat_layout_', 'cat_icon_', 'z_taxonomy_image');
		$cat_data = array();
		foreach($cats as $cat){
			$id = $cat->term_id;
			$slug = $cat->slug;
			$data = array();
			foreach($cat_meta_list as $key){
				$val = get_option($key . $id);
				if($val){
					$data[$key] = $val;
				}
			}
			
			$cat_data[$slug] = $data;
		}
		
		$cats = get_categories(array('taxonomy' => 'video-series', 'hide_empty' => 0));

		$cat_meta_list = array('video_series_release_', 'video_series_creator_', 'video_series_stars_', 'z_taxonomy_image');
		$series_data = array();
		foreach($cats as $cat){
			$id = $cat->term_id;
			$slug = $cat->slug;
			$data = array();
			foreach($cat_meta_list as $key){
				$val = get_option($key . $id);
				if($val){
					$data[$key] = $val;
				}
			}
			
			$series_data[$slug] = $data;
		}
		
		?>
		<table class="tbl-settings">
			<tr>
				<td width="160px" class="header">Category Metadata</td>
				<td><textarea id="category_metadata" cols="100" rows="8"><?php echo serialize($cat_data);?></textarea></td>
				<td>Import (not available yet)</td>
			</tr>
			<tr>
				<td class="header">Video Series Metadata</td>
				<td><textarea id="video_series_metadata" cols="100" rows="8"><?php echo serialize($series_data);?></textarea></td>
				<td>Import (not available yet)</td></tr>
		</table>
	</div>
<?php
		$html = ob_get_contents();
		ob_end_clean();
		echo $html;
	
	}
	
	private function get_base_directory(){
		return $this->base_dir; 
	}
	
	private function get_base_uri(){
		return $this->base_uri; 
	}
	
	function init(){
        //$this->do_install_home('homepage-game-version');
		// init action
		add_action('videopro_import_data_tab', array($this,'get_import_data_tab_content'));
	}
	
	function admin_enqueue_scripts_styles(){
		wp_enqueue_style('cactus-importer-css', $this->get_base_uri() . 'css/admin.css');
		wp_enqueue_script('cactus-importer-js', $this->get_base_uri() . 'js/admin.js', array('jquery'), '', true );
	}
	
	function do_import($package = '', $step = '', $index = ''){
		if(!$package)
			$package = $_POST['pack'];
		
		if(!$step)
			$step = $_POST['step'];
		
		if(!$index)
			$index = $_POST['index'];

		$option_only = $_POST['option_only'];
		
		$pack = $this->get_base_directory() . "packages/$package/cactus_demo_pack_$package.php";

		if(file_exists($pack)){
			require_once $pack;
			
			$pack = "cactus_demo_pack_$package";
			$package = new $pack($this->get_base_uri(), $this->get_base_directory());
			
			$step = $package->do_import($step, $index, $option_only);
			
			echo json_encode($step);
		} else {
			echo array('error' => 1, 'error_message' => esc_html__('cannot find data', 'cactus'));
		}

		die();
	}
	
	/**
	 * echo content for Import Data Tab
	 */
	public function get_import_data_tab_content(){
		if ( !current_user_can( 'manage_options' ) )
		{
			global $current_user;
			$msg = sprintf(esc_html__("I'm sorry, %s I'm afraid I can't do that.",'cactus'), $current_user->display_name);
			echo '<div class="wrap">' . $msg . '</div>';
			return false;
		}
		?>
            <div class="cactus-demo-container">
                <div id="primary">
                    <div class="import-admin-title">
                        <h2><span class="dashicons dashicons-upload"></span><span class="text"><?php esc_html_e('Import Sample Data','cactus');?></span></h2>
                    </div>
                    <div class="cactus-admin-notice">
                        <ul>
                            <li><?php esc_html_e('Make sure you have installed all recommended plugins from WordPress repository (WordPress.org) and built-in plugins which come in the full package','cactus');?>
                            </li>
                            <li><?php esc_html_e('As the Demo has many pages just for listing features, number of menu items is big. In some cases, main navigation will not be imported successfully due to server capacity. Don\'t worry, you can just go to Appearance > Menus to add items or save the menu again','cactus');?></li>
                            <li><?php esc_html_e('The Demo use Widget Logic (plugin) to configure widgets. It is recommended to also install this plugin. If not, just go to Appearance > Widgets to remove widgets you don\'t use','cactus');?></li>
                        </ul>
                    </div>
                    <div class="theme-browser rendered" id="fw-ext-backups-demo-list">
                        <div class="demo-admin-title">
                            <h2><span class="dashicons dashicons-archive"></span><span class="text"><?php esc_html_e('Choose a sample data package','cactus');?></span></h2>
                            <input type="hidden" name="is_submit_import_data_form" value="Y">
                            <input type="hidden" name="site_url" value="<?php echo esc_attr(site_url());?>">
                        </div>
                        <div class="demo-admin-container">
                            <div class="cactus-image-select">
                                <?php
                                $i = 0;
                                foreach($this->packages as $item){
                                    $pack = $this->base_dir . "packages/$item/cactus_demo_pack_$item.php";
                                    require_once $pack;
                                    $pack = "cactus_demo_pack_$item";
                                    $package = new $pack('', '');
                                    ?>
                                    <div class="item" >
                                        <img src="<?php echo $this->base_uri; ?>packages/<?php echo $item;?>/thumbnail.jpg" />
                                        <br>
                                        <p class="data-package-name">
                                            <?php if(isset($package->url)){?>
                                            <a href="<?php echo $package->url;?>" target="_blank" title="<?php esc_html_e('View Demo','cactus');?>">
                                                <?php }?>
                                                <b><?php echo $package->heading; ?></b>
                                                <?php if(isset($package->url)){?>
                                            </a>
                                        <?php }?>
                                        </p>
                                        <p><label><input type="checkbox" id="import-options-<?php echo $item;?>" name="option_only" value="1" /> <?php esc_html_e('Import Options Only','cactus'); ?></label></p>
                                        <p id="import-button-<?php echo $item;?>" class="import-button"><a href="javascript:void(0)" onclick="cactus_importer.do_import('<?php echo $item;?>');"><?php echo esc_html__('Import','cactus');?></a></p>
                                        <div class="progress-bar animate" id="import-progress-<?php echo $item;?>"><span class="inner" style="width:0%"><span><!-- --></span></span></div>
                                        <?php if(isset($package->description)){?>
                                            <div class="description"><?php echo $package->description;?></div>
                                        <?php }?>
                                    </div>
                                <?php $i++;
                                if ($i % 3 == 0) { echo '<hr>';}
                                } ?>
                            </div>
                        </div>

                        <div class="demo-admin-title" style="margin-top: 20px">
                            <h2><span class="dashicons dashicons-admin-home"></span><span class="text"><?php esc_html_e('Create Sample Home Pages','cactus');?></span></h2>
                        </div>

                        <div class="demo-admin-container">
                            <div class="cactus-admin-notice">
                                <ul>
                                    <li>Below pre-defined home pages are configured using your existing data. Thus, some of the shortcodes will not work if it lacks of data. For example: mosted Viewed or most Liked conditions will not work if your site does not have Post Views count (using Top 10 plugin) or Like value (using WTI plugin).</li>
                                    <li>All widgets settings will be replaced. So, make sure you backup your widget settings using Widget Settings Import/Export plugin</li>
                                    <li>If you try to install several times, please install <b>Widget Logic</b> plugin so you can see appropriate widgets in different pages</li>
                                </ul>
                            </div>
                            <div class="cactus-image-select">
                                <?php

                                $homes = array(
                                    'home-page-v1' => array(
                                        'title' => 'Home Page V1',
                                        'screenshot' => '1.jpg'
                                    ),
                                    'home-page-v2' => array(
                                        'title' => 'Home Page V2',
                                        'screenshot' => '2.jpg'
                                    ),
                                    'home-page-v3' => array(
                                        'title' => 'Home Page V3',
                                        'screenshot' => '3.jpg'
                                    ),
                                    'home-page-v4' => array(
                                        'title' => 'Home Page V4',
                                        'screenshot' => '4.jpg'
                                    ),
                                    'home-page-v5' => array(
                                        'title' => 'Home Page V5',
                                        'screenshot' => '5.jpg'
                                    ),
                                    'home-page-v6' => array(
                                        'title' => 'Home Page V6',
                                        'screenshot' => '6.jpg'
                                    ),
                                    'home-page-v7' => array(
                                        'title' => 'Home Page V7',
                                        'screenshot' => '7.jpg'
                                    ),
                                    'home-page-v8' => array(
                                        'title' => 'Home Page V8',
                                        'screenshot' => '8.jpg'
                                    ),
                                    'home-page-v9' => array(
                                        'title' => 'Home Page V9',
                                        'screenshot' => '9.jpg'
                                    ),
                                    'home-page-v10' => array(
                                        'title' => 'Home Page V10',
                                        'screenshot' => '10.jpg'
                                    ),
                                    'homepage-game-version' => array(
                                        'title' => 'Home Page V11',
                                        'screenshot' => '11.jpg'
                                    )
                                );
                                $i = 0;
                                foreach($homes as $home => $args){
                                    ?>
                                    <div class="item" >
                                        <img src="<?php echo $this->base_uri; ?>packages/default/<?php echo $args['screenshot'];?>" />
                                        <br>
                                        <p class="data-package-name">
                                            <b><?php echo $args['title']; ?></b>
                                        </p>

                                        <p id="import-button-<?php echo $home;?>" class="import-button"><a href="javascript:void(0)" class="" onclick="cactus_importer.do_import_home('<?php echo $home;?>', this);"><?php echo esc_html__('Create','cactus');?></a></p>
                                    </div>
                                    <?php
                                    $i++;
                                    if ($i % 3 == 0) { echo '<hr>';}
                                } ?>
                            </div>
                        </div>
                    </div>
                    <script>
                        jQuery(document).ready(function(e) {
                            jQuery('.cactus-image-select .item').click(function(){
                                jQuery(this).addClass('selected').siblings().removeClass('selected');
                            });
                        });
                    </script>

                </div>
                <div id="secondary">

                </div>
            </div>
        <?php
	}
}