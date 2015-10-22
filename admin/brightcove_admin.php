<?php 

new BrightCoveVideoCloudAdmin;

class BrightCoveVideoCloudAdmin {
  public static $messages = array();
  public $API_BASE = 'http://api.brightcove.com/services/library';

  public function __construct() {
    /* Sets up an admin notice notifying the user that they have not registered their brightcove settings */
    add_action('admin_notices', array($this, 'brightcove_settings_notice'));
    add_action('admin_menu', array($this, 'brightcove_menu'));
    add_action('admin_init', array($this, 'register_brightcove_settings'));

    add_action('save_post', array($this, 'save_post'), 10, 3);
  }

  public static function add_message($message, $status = 'updated') {
    static::$messages[] = array('msg'=>$message, 'status'=>$status);
  }

  public static function get_messages() {
    return static::$messages;
  }

  public function save_post($post_id, $post, $update) {
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( !current_user_can( 'edit_posts' ) ) return;

    if(!has_post_thumbnail($post)) {
      $pattern = get_shortcode_regex();
      if (   preg_match_all( '/'. $pattern .'/s', $post->post_content, $matches )
          && array_key_exists( 2, $matches )
          && in_array( BCVC_SHORTCODE, $matches[2] ) )
      {
          // shortcode is being used
        $params = explode(' ', $matches[3][0]);
        $options = array();
        foreach($params as $param) {
          if(strpos($param, '=') !== false) {
            list($k, $v) = explode('=',$param);
            $options[$k] = $v;
          }
        }
        if(!empty($options['featured']) && $options['featured']) {
          //if(empty($options['video_id'])) return $post_id;
          // //api.brightcove.com/services/library?command=find_video_by_id&video_id=2790007957001&video_fields=id%2Cname%2CshortDescription%2ClinkURL%2ClinkText%2Ctags%2CvideoStillURL%2CvideoStill%2CthumbnailURL%2Cthumbnail&media_delivery=default&callback=BCL.onSearchResponse&token=ZY4Ls9Hq6LCBgleGDTaFRDLWWBC8uoXQun0xEEXtlDUHBDPZBCOzbw..
          $url = $this->API_BASE;
          $query = http_build_query(array(
            'command' => 'find_video_by_id',
            'video_id' => $options['videoID'],
            'video_fields' => 'id,shortDescription,linkURL,linkText,tags,videoStillURL,videoStill,thumbnailURL,thumbnail',
            'media_delivery' => 'default',
            'token' => get_option('bc_api_key'),
          ));

          $url .= '?' . $query;
          $response = wp_remote_get($url, array('timeout'=>10));
          if(is_wp_error($response)) { echo '<pre>ERROR: '; var_dump($response); echo '<pre>'; exit; }
          if(!empty($response['body'])) {
            $video = json_decode($response['body'], true);
            if($video !== null && !empty($video['videoStillURL'])) {
              $videoStillURL = $video['videoStillURL'];
              $image_id = $this->sideload_image($videoStillURL, $post_id, !empty($video['shortDescription']) ? $video['shortDescription'] : '');
              if(is_wp_error($image_id)) { echo '<pre>ERROR: '; var_dump($image_id); echo '<pre>'; exit; }
              set_post_thumbnail($post_id, $image_id);
            }
          }
        }
      }
    }
  }

  private function sideload_image($file, $post_id, $desc) {
    if ( ! empty( $file ) ) {
      // Set variables for storage, fix file filename for query strings.
      preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
      $file_array = array();
      $file_array['name'] = basename( $matches[0] );

      // Download file to temp location.
      $file_array['tmp_name'] = download_url( $file );

      // If error storing temporarily, return the error.
      if ( is_wp_error( $file_array['tmp_name'] ) ) {
        return $file_array['tmp_name'];
      }

      // Do the validation and storage stuff.
      $id = media_handle_sideload( $file_array, $post_id, $desc );

      // If error storing permanently, unlink.
      if ( is_wp_error( $id ) ) {
        @unlink( $file_array['tmp_name'] );
        return $id;
      }
      return $id;
    }
  }

  /*Checks to see if defaults are set, displays error if not set*/
  public function brightcove_settings_notice() {
    if (BrightCoveVideoCloud::$options['defaultSet'] == false) {
      if (current_user_can('manage_options')) {
        echo "<div class='error'><p>You have not entered your settings for the Brightcove Plugin. Please set them up at <a href='admin.php?page=brightcove_menu'>Brightcove Settings</a></p></div>";
      } else {
        echo "<div class='error'><p>  You have not set up your defaults for the Brightcove plugin. Please contact your site administrator to set these defaults.</p></div>";
      } 
    }
    
    if(!empty(static::$messages)) {
      foreach(static::get_messages() as $msg) {
        echo "<div class='{$msg['status']}><p>{$msg['msg']}</div>";        
      }
    }
  }

  public function brightcove_menu() {
    wp_deregister_script( 'brightcove_admin_script' );
    $myBrightcoveAdminScript = plugins_url('admin/brightcove_admin.js', BCVC_FILTER_FILE);
    wp_register_script( 'brightcove_admin_script', $myBrightcoveAdminScript);
    wp_enqueue_script( 'brightcove_admin_script');

    $myBrightcoveMenuStyle = plugins_url('admin/brightcove_admin.css',BCVC_FILTER_FILE);
    wp_register_style( 'brightcove_menu_style', $myBrightcoveMenuStyle);
    wp_enqueue_style( 'brightcove_menu_style' );
    add_menu_page(__('Brightcove Settings'), __('Brightcove'), 'manage_options', 'brightcove_menu', array($this, 'brightcove_menu_render'), plugins_url('/admin/bc_icon.png',BCVC_FILTER_FILE)); 

    wp_deregister_script('jQueryValidate');
    $myBrightcoveJQValidate = plugins_url('jQueryValidation/jquery.validate.min.js',BCVC_FILTER_FILE);
    wp_register_script( 'jQueryValidate',$myBrightcoveJQValidate);
    wp_enqueue_script( 'jQueryValidate' );

    wp_deregister_script('jQueryValidateAddional');
    $myBrightcoveJQAdditionalMethods = plugins_url('jQueryValidation/additional-methods.min.js',BCVC_FILTER_FILE);
    wp_register_script( 'jQueryValidateAddional', $myBrightcoveJQAdditionalMethods);
    wp_enqueue_script( 'jQueryValidateAddional');

    wp_deregister_script('jqueryPlaceholder');
    $myBrightcoveJQPlaceholder = plugins_url('jQueryPlaceholder/jQueryPlaceholder.js', BCVC_FILTER_FILE);
    wp_register_script( 'jqueryPlaceholder', $myBrightcoveJQPlaceholder);
    wp_enqueue_script( 'jqueryPlaceholder');
  }

  public function brightcove_menu_render() {
    $playerID = get_option('bc_player_id');
    $playerKey_playlist = get_option('bc_player_key_playlist'); 

    $publisherID = get_option('bc_pub_id');

    if (isset($_GET['settings-updated'])) {
      $isTrue = $_GET['settings-updated'];
      if ($isTrue == true) {
        echo '<div class="updated"><p> Your settings have been saved </p></div>';
      }
    }
    include(BCVC_PLUGIN_PATH . '/templates/admin/menu.php');
  }

  public function register_brightcove_settings() { // whitelist options
    register_setting( 'brightcove-settings-group', 'bc_pub_id' );
    register_setting( 'brightcove-settings-group', 'bc_player_id' );
    register_setting( 'brightcove-settings-group', 'bc_player_key_playlist' );
    register_setting( 'brightcove-settings-group', 'bc_api_key' );
    register_setting( 'brightcove-settings-group', 'bc_default_height' );
    register_setting( 'brightcove-settings-group', 'bc_default_width' );
    register_setting( 'brightcove-settings-group', 'bc_default_height_playlist' );
    register_setting( 'brightcove-settings-group', 'bc_default_width_playlist' );
    register_setting( 'brightcove-settings-group', 'bc_default_featured' );
  }
}