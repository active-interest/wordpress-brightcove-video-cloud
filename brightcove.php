<?php
/**
 * @package Brightcove Video Cloud for WordPress
 * @version 2.0
 */
/*
Plugin Name: Brightcove Video Cloud for WordPress
Plugin URL: http://github.com/active-interest/wordpress-brightcove-video-cloud
Description: An easy to use plugin that inserts Brightcove Video into your Wordpress site. 
Author: Brightcove, David Higgins <dhiggins@aimmedia.com>
Version: 2.0
Author URI: http://github.com/active-interest/
*/

if(!defined( 'ABSPATH' ))  die(); // We should not be loading this outside of wordpress
if(!defined( 'BCVC_VERSION_NUM' )) define( 'BCVC_VERSION_NUM', '0.1.1' );
if(!defined( 'BCVC_NAME' )) define( 'BCVC_NAME', basename(__FILE__, '.php') );
if(!defined( 'BCVC_CPT' )) define( 'BCVC_CPT', 'boat-review' );
if(!defined( 'BCVC_FILTER_FILE' )) define( 'BCVC_FILTER_FILE', __FILE__);
if(!defined( 'BCVC_PLUGIN_PATH' )) define( 'BCVC_PLUGIN_PATH', dirname( __FILE__ ) );

if(is_admin()) {
  require 'admin/brightcove_admin.php';
}

new BrightCoveVideoCloud;

class BrightCoveVideoCloud {

  static $options = array();

  public function __construct() {
    add_action('init', array($this, 'init'));
    add_shortcode('brightcove', array($this, 'shortcode'));
  }

  public function configure() {

    static::$options = Array(
      'playerID' => null, 
      'defaultHeight' => null, 
      'defaultWidth' => null, 
      'defaultKeyPlaylist' => null, 
      'defaultHeightPlaylist' => null, 
      'defaultWidthPlaylist' => null,
      'defaultSet' => null, 
      'defaultSetErrorMessage' => null, 
      'defaultsSection' => null, 
      'loadingImg' => null, 
      'publisherID' => null
    );

    //Publisher ID 
    static::$options['publisherID'] = get_option('bc_pub_id');

    //Player ID for single videos
    static::$options['playerID'] = get_option('bc_player_id');

    //Default height & width for single video players
    static::$options['defaultHeight'] = get_option('bc_default_height');

    if (static::$options['defaultHeight'] == '') {
      static::$options['defaultHeight']='270';
    }

    static::$options['defaultWidth']=get_option('bc_default_width');

    if (static::$options['defaultWidth'] == '') {
      static::$options['defaultWidth']='480';
    }

    //Player ID for playlists
    static::$options['playerKeyPlaylist']=get_option('bc_player_key_playlist');

    //Default height & width for playlist players
    static::$options['defaultHeightPlaylist']=get_option('bc_default_height_playlist');

    if (static::$options['defaultHeightPlaylist'] == '') {
      static::$options['defaultHeightPlaylist']='400';
    }

    static::$options['defaultWidthPlaylist']=get_option('bc_default_width_playlist');

    if (static::$options['defaultWidthPlaylist'] == '') {
      static::$options['defaultWidthPlaylist']='940';
    }

    //Checks to see if both those variables are set
    if (static::$options['playerID'] == '' || static::$options['playerKeyPlaylist'] == '' || static::$options['publisherID'] == '') {
      static::$options['defaultSet']=false;
    } else  {
      static::$options['defaultSet']=true;
    }

    if ( current_user_can('manage_options') ) {
      static::$options['defaultSetErrorMessage'] = 
        "<div class='hidden error' id='defaults-not-set' data-defaultsSet='".static::$options['defaultSet']."'>
        You have not set up your defaults for this plugin. Please click on the link to set your defaults.
          <a target='_top' href='admin.php?page=brightcove_menu'>Brightcove Settings</a>
      </div>";
    } else  {
      static::$options['defaultSetErrorMessage'] = 
        "<div class='hidden error' id='defaults-not-set' data-defaultsSet='".static::$options['defaultSet']."'>
          You have not set up your defaults for the Brightcove plugin. Please contact your site administrator to set these defaults.
        </div>";  
    }

    static::$options['defaultsSection'] = 
      "<div class='defaults'>
      <input type='hidden' id='bc-default-player' name='bc-default-player' value='".static::$options['playerID']."' >
      <input type='hidden' id='bc-default-width' name='bc-default-width' value='".static::$options['defaultWidth']."' >
      <input type='hidden' id='bc-default-height' name='bc-default-height' value='".static::$options['defaultHeight']."' >
      <input type='hidden' id='bc-default-player-playlist-key' name='bc-default-player-playlist-key' value='".static::$options['playerKeyPlaylist']."' >
      <input type='hidden' id='bc-default-width-playlist' name='bc-default-width-playlist' value='".static::$options['defaultWidthPlaylist']."' >
      <input type='hidden' id='bc-default-height-playlist' name='bc-default-height-playlist' value='".static::$options['defaultHeightPlaylist']."' >
      </div>";

    static::$options['loadingImg'] = "<img class='loading-img' src='/wp-includes/js/thickbox/loadingAnimation.gif' />";
  }

  public function init() {
    $this->configure();
    add_action('wp_enqueue_scripts', array($this, 'add_all_scripts'));
    add_action('wp_enqueue_scripts', array($this, 'add_all_admin_scripts'));
    add_filter('media_upload_tabs', array($this, 'media_menu'));
    add_action('media_upload_brightcove', array($this, 'menu_handle'));
    add_action('media_upload_brightcove_api', array($this, 'brightcove_api_menu_handle'));
  }

  /************************Upload Media Tab ***************************/
  public function media_menu($tabs) {
  	//TODO Check for isset or empty instead
   
  	if (get_option('bc_api_key') != NULL or get_option('bc_api_key') != '') {
      	$tabs['brightcove_api']='Brightcove'; 
  	} else {
     		$tabs['brightcove']='Brightcove';
     	}
     	return $tabs;
  }


  public function add_all_admin_scripts(){
  	wp_enqueue_script('media-upload');
  	$brightcoveStyleUrl = plugins_url('brightcove.css', BCVC_FILTER_FILE);
  	wp_register_style('brightcoveStyleSheets', $brightcoveStyleUrl);
  	wp_enqueue_style( 'brightcoveStyleSheets');
  }

  public function menu_handle() {
  	//TODO check to see what $errors is being used for
  	//TODO check to see if parameters can be passed in here
  	//if not then have bc_media_upload_form call function
  	return wp_iframe('bc_media_upload_form');
  }

  public function brightcove_api_menu_handle() {
    	return wp_iframe(array($this, 'bc_media_api_upload_form'));
  }

  //Adds all the scripts nessesary for plugin to work
  public function add_all_scripts() {
    wp_enqueue_script('jquery-ui');
    wp_enqueue_script('jquery-ui-tabs');

    wp_register_style( 'brightcove-jquery-ui', plugins_url('jquery-ui.css', BCVC_FILTER_FILE), null, BCVC_VERSION_NUM, 'screen');
    wp_enqueue_style( 'brightcove-jquery-ui');

  	$this->add_bcove_scripts(); 
  	$this->add_jquery_scripts();
  	$this->add_validation_scripts();
  	$this->add_dynamic_brightcove_api_script();
  }

  public function add_bcove_scripts() {	
  	wp_deregister_script( 'bcove-script' );
  	$varbsbs = plugins_url('brightcove-experience.js',BCVC_FILTER_FILE);
  	wp_register_script( 'bcove-script', $varbsbs);
  	wp_enqueue_script( 'bcove-script' );
  }

  public function add_jquery_scripts() {
    /*
  	wp_deregister_script('bcove-jquery');
  	$varbj = plugins_url('jquery.min.js',BCVC_FILTER_FILE);
  	wp_register_script( 'bcove-jquery', $varbj);
  	wp_enqueue_script( 'bcove-jquery' );
    */

    /*
  	wp_deregister_script('bcove-jquery-ui-core');
  	$varbjuc = plugins_url('jquery-ui.min.js',BCVC_FILTER_FILE);
  	wp_register_script( 'bcove-jquery-ui-core', $varbjuc);
  	wp_enqueue_script( 'bcove-jquery-ui-core' );
    */

    wp_enqueue_script('jquery');
  }

  public function add_validation_scripts() {
  	wp_deregister_script('jqueryPlaceholder');
  	$varjp = plugins_url('jQueryPlaceholder/jQueryPlaceholder.js',BCVC_FILTER_FILE);
  	wp_register_script( 'jqueryPlaceholder', $varjp);
  	wp_enqueue_script( 'jqueryPlaceholder');

  	wp_deregister_script('jquery-validate');
  	$varjv = plugins_url('jQueryValidation/jquery.validate.min.js',BCVC_FILTER_FILE);
  	wp_register_script( 'jquery-validate', $varjv);
  	wp_enqueue_script( 'jquery-validate' );

  	wp_deregister_script('jquery-validate-additional');
  	$varjva = plugins_url('jQueryValidation/additional-methods.min.js',BCVC_FILTER_FILE);
  	wp_register_script( 'jquery-validate-additional', $varjva);
  	wp_enqueue_script( 'jquery-validate-additional' );
  }


  public function add_dynamic_brightcove_api_script() {	
  	wp_deregister_script( 'dynamic_brightcove_script' );
  	$vardbas = plugins_url('dynamic_brightcove.js',BCVC_FILTER_FILE);
  	wp_register_script( 'dynamic_brightcove_script', $vardbas);
  	wp_enqueue_script( 'dynamic_brightcove_script' );
  }

  public function set_shortcode_button ($playlistOrVideo, $buttonText) {
  	if ($playlistOrVideo == 'playlist') {
  		$id='playlist-shortcode-button';
  	} else {
  		$id='video-shortcode-button';
  	}
    ?>
  	<div class='media-item no-border insert-button-container'>
      <button disabled='disabled' id='<?php echo $id; ?>' class='aligncenter button'/><?php echo $buttonText; ?></button>
    </div>
    <?php
	} 

  //TODO Pass in as map
  function add_player_settings($playlistOrVideo, $buttonText) { 
  	if ($playlistOrVideo == 'playlist') {
  		$setting = '-playlist';
  		$height = static::$options['defaultHeightPlaylist'];
  		$width = static::$options['defaultWidthPlaylist'];
  		/*$player = static::$options['playerIDPlaylist'];*/
  		$playerKey = static::$options['playerKeyPlaylist'];
  		$id='playlist-settings';
  		$class='playlist-hide';
  		$playerHTML=
        "<tr class='bc-width-row'>
    			<th valign='top' scope='row' class='label'>
						<span class=;alignleft;>
							<label for=bcPlaylistKey'>Playlist Key</label>
						</span>
						<span class='alignright'></span>
					</th>
					<td>
						<input class='player-data' type='text' name='bcPlaylistKey' id='bc-player-playlist-key' placeholder='Default is $playerKey ' />
					</td>
				</tr>";
  	} else {
  		$setting = '';
  		$height = static::$options['defaultHeight'];
  		$width = static::$options['defaultWidth'];
  		$player = static::$options['playerID'];
  		$id='video-settings';
  		$class='video-hide';
  		$playerKey='';
  		$playerHTML=
        "<tr class='bc-player-row'>
    			<th valign='top' scope='row' class='label'>
  					<span class='alignleft'>
							<label for='bcPlayer'>Player ID:</label>
						</span>
						<span class='alignright'></span>
					</th>
					<td>
						<input class='digits player-data' type='text' name='bcPlayer' id='bc-player-$setting ?>' placeholder='Default ID is $player'/>
					</td>
				</tr>";
  	}
  	?>
  	<form class='<?php echo $class;?>' id='<?php echo $id; ?>'>
      <table>
        <tbody>
        <?php echo $playerHTML; ?>
        <tr class='bc-width-row'>
          <th valign='top' scope='row' class='label'>
            <span class="alignleft"><label for="bcWidth">Width:</label></span>
            <span class="alignright"></span>
          </th>
          <td>
           <input class='digits player-data' type='text' name='bcWidth' id='bc-width<?php echo $setting; ?>' placeholder='Default is <?php echo $width; ?> px' />
          </td>
        </tr>
        <tr class='bc-height-row'>
          <th valign='top' scope='row' class='label'>
            <span class="alignleft"><label for="bcHeight">Height:</label></span>
            <span class="alignright"></span>
          </th>
          <td>
           <input class='digits player-data'  type='text' name='bcHeight' id='bc-height<?php echo $setting; ?>' placeholder='Default is <?php echo $height; ?> px' />
          </td>
        </tr>
        </tbody>
      </table>
      <?php $this->set_shortcode_button($playlistOrVideo, $buttonText); ?>
    </form> 
    <?php
  }

  public function add_preview_area ($playlistOrVideo) {
  	if ($playlistOrVideo == 'playlist') {
  		$id='dynamic-bc-placeholder-playlist';
  		$class='playlist-hide';
  		$otherClass='playlist';
  	} else {
  		$id='dynamic-bc-placeholder-video';
  		$class='video-hide';
  		$otherClass='video';
  	}
    ?>
  	<div class='<?php echo $class; ?> media-item no-border player-preview preview-container hidden'>
      <h3 class='preview-header'>Video Preview</h3>
      <table>
        <tbody>
          <tr>
            <td>
				<div class='alignleft'>
					<h4 id='bc-title-<?php echo $otherClass; ?>' class='bc-title'></h4>
					<p id='bc-description-<?php echo $otherClass; ?>' class='bc-description'></p>
					<div id="<?php echo $id; ?>"></div>
				</div>
				<div class='alignleft'>
				</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php
  }

  public function bc_media_upload_form () {
	 media_upload_header();
	 add_all_scripts();
  ?>
  <div class="bc-container">
  	<?php
  		echo static::$options['defaultSetErrorMessage']; 
  		echo static::$options['defaultsSection'];
  		echo static::$options['loadingImg'];
  	?>

  	<div class='no-error'>
	    <div id='tabs'>
	      <ul>
	        <li ><a class='video-tab' href="#tabs-1">Videos</a></li>
	        <li><a class='playlist-tab' href="#tabs-2">Playlists</a></li>
	      </ul>
        <div class='tab clearfix video-tab' id='tabs-1'>
	        <div class='media-item no-border'>
	          <form id='validate-video'>
	            <table>
	              <tbody>
	                <tr>
	                  <th valign='top' scope='row' class='label'>
	                    <span class="alignleft"><label for="bc-video">Video:</label></span>
	                    <span class="alignright"></span>
	                  </th>
	                  <td>
	                    <input class='id-field player-data' placeholder='Video ID' aria-required="true" type='text' name='bcVideo' id='bc-video' placeholder='Video ID or URL'>
	                  </td>
	                </tr>
	                <tr>
	                  <th valign='top' scope='row' class='label'>
	                  </th>
	                  <td class='bc-check'>
	                     <input class='player-data alignleft' type='checkbox' name='bc-video-ref' id='bc-video-ref' />
	                     <span class="alignleft"><label for='bc-video-ref'>This is a reference ID, not a video ID </label></span>
	                  </td>
	                </tr>
	              </tbody>
	            </table>
	          </form>
	        </div>
	      </div>
	      <div class='tab clearfix playlist-tab' id='tabs-2'>
          <div class='media-item no-border'>
	          <form id='validate-playlist'>
	            <table> 
	              <tbody>
	                <tr>
	                  <th valign='top' scope='row' class='label' >
	                    <span class="alignleft"><label for="bcPlaylist">Playlist:</label></span>
	                    <span class="alignright"></span>
	                  </th>
	                  <td>
	                   <input class='id-field player-data' type='text' name='bcPlaylist' id='bc-playlist' placeholder='Playlist ID(s) separated by commas or spaces' />
	                  </td>
	                </tr>
	                <tr>
	                  <th valign='top' scope='row' class='label'></th>
	                  <td class='bc-check'>
	                   <input class='alignleft player-data' type='checkbox' name='bc-playlist-ref' id='bc-playlist-ref'/>
	                   <span class="alignleft"><label for='bc-playlist-ref'>These are reference IDs, not playlist IDs </label></span>
	                  </td>
	                </tr>
	              </tbody>
	            </table>
	          </form>
	        </div>
	      </div>
	    </div><!-- End of tabs --> 
	    <div id='bc-error' class='hidden error'>An error has occured, please check to make sure that you have a valid video or playlist ID</div>
      <?php
      //TODO pass in map of defaults
    	add_player_settings('video', 'Insert Shortcode');
      ?> 
      <?php
    	add_preview_area('video');
    	add_player_settings('playlist', 'Insert Shortcode');
    	add_preview_area('playlist');
    ?>
    </div> 
    <?php	
  }

  public function add_mapi_script() {
  	wp_deregister_script( 'mapi_script' );
  	wp_register_script( 'mapi_script', plugins_url('/bc-mapi.js', BCVC_FILTER_FILE));
  	wp_enqueue_script( 'mapi_script' );
  }

  public function bc_media_api_upload_form () {
  	media_upload_header();
  	$this->add_all_scripts();
  	$this->add_mapi_script();
  	$apiKey = get_option('bc_api_key');
    ?>
  	<div class="bc-container">
    	<?php
  		echo static::$options['defaultSetErrorMessage']; 
  		echo static::$options['defaultsSection'];
  		echo static::$options['loadingImg'];
    	?>
      <input type='hidden' id='bc-api-key' name='bc-api-key' value='<?php echo $apiKey; ?>' />
      <div class='no-error'>
      	<div id='tabs-api' class="tabs-api">
      		<ul>
      			<li><a class='video-tab-api' href="#tabs-1">Videos</a></li>
      			<li><a class='playlist-tab-api' href="#tabs-2">Playlists</a></li>
      		</ul>
      		<div id='tabs-1' class='tabs clearfix video-tabs'>
      			<form class='clearfix' id='search-form'>
      				<div class='alignleft'>
      				  <input placeholder=' Search by name, description, tag or custom field' id='bc-search-field' type='text'>
      				</div>
      				<div class='alignright'>
      				  <button class='button' type='submit' id='bc-search'>Search</button>
      				</div>
      			</form>
      			<div class='bc-video-search clearfix' id='bc-video-search-video'></div>
      			<?php $this->add_player_settings('video', 'Insert Video'); ?>
    		</div>
    		<div id='tabs-2' class='tabs clearfix playlist-tab'>
    			<div class='bc-video-search clearfix' id='bc-video-search-playlist'></div>
    			<?php $this->add_player_settings('playlist', 'Insert Playlists');?>
    		</div>
    	</div>
    </div>
  	<?php	
  }

  public function shortcode($atts) {
    $html='';
    $width=0;
    $height=0;

    if (isset($atts['width'])) { 
      $width = $atts['width'];
    } else  {
      $width = get_option('bc_default_width');
    } 
    if (isset($width)) {
      //$width = 480;
      }

      if (isset($atts['height'])) { 
        $height = $atts['height'];
      } else  {
        $height = get_option('bc_default_height');
      }
      if (isset($height)) {
         //$height= 270;
    }
    $html = '
          <div style="display:none"></div>
          <object id="'.rand().'" class="BrightcoveExperience">
          <param name="bgcolor" value="#FFFFFF" />
          <param name="wmode" value="transparent" />
          <param name="width" value="' . $width . '" />
          <param name="height"  value="'. $height .'" />';
          
    if (isset($atts['playerid'])) {   
        $html = $html . '<param name="playerID" value="'.$atts['playerid'].'" />';
    }

    if (isset($atts['playerkey'])) {   
        $html = $html . '<param name="playerKey" value="'.$atts['playerkey'].'"/>';
    }
    $html = $html .' <param name="isVid" value="true" />
            <param name="isUI" value="true" />
            <param name="dynamicStreaming" value="true" />';

    if (isset($atts['videoid'])) { 
        $html = $html . '<param name="@videoPlayer" value="'.$atts['videoid'].'" />';
    }
    
    if (isset($atts['playlistid'])) {   
        $html = $html . '<param name="@playlistTabs" value="'.$atts['playlistid'].'" />';
      $html = $html . '<param name="@videoList" value="'.$atts['playlistid'].'" />';
      $html = $html . '<param name="@playlistCombo" value="'.$atts['playlistid'].'" />';
    } 
    
    $html = $html . '</object><script type="text/javascript">brightcove.createExperiences();</script>';

    return $html;
  }
}

?>