<?php
/*
 * Plugin Name: Agile Bench
 * Version: 0.5
 * Plugin URI: http://github.com/agilebench/agilebench-wordpress-widget
 * Description: This plugin can either display an entire backlog, the current iteration or a story entry field.
 * Author: Mark Mansour
 * Author URI: http://agilebench.com/
 * Notes: We're not PHP or WordPress developers.  If you've got feedback we'd
 *        love to hear it!<
 */

// define('AGILEBENCH_HOST', "http://127.0.0.1:3000");
define('AGILEBENCH_HOST', "http://agilebench.com");

// From: http://wezfurlong.org/blog/2006/nov/http-post-from-php-without-curl
function do_post_request($url, $data, $optional_headers)
{
  return do_request($url, $data, $option_headers, "POST");
}

function do_request($url, $data, $optional_headers = null, $method = "GET")
{
  $params = array('http' => array('method' => $method,
                                  'content' => $data,
                                  'ignore_errors' => true));

  if ($optional_headers !== null) {
    $params['http']['header'] = $optional_headers;
  }

  $ctx = stream_context_create($params);
  $fp = @fopen($url, 'rb', false, $ctx);

  if (!$fp) {
    throw new Exception("Problem with $url, $php_errormsg");
  }

  $response = @stream_get_contents($fp);
  $return_code = @explode(' ', $http_response_header[0]);
  $return_code = (int)$return_code[1];

  // echo var_dump($return_code);

  if ($response === false) {
    throw new Exception("Problem reading data from $url, $php_errormsg");
  }

  return array($return_code, $response);
}

class AgileBenchWidget extends WP_Widget
{
  /**
   * Declares the AgileBenchWidget class.
   *
   */
  function AgileBenchWidget() {
    $widget_ops = array('classname' => 'agile_bench_widget', 'description' => __( "Add and List stories from your Agile Bench project") );
    $control_ops = array('width' => 300, 'height' => 300);
    $this->WP_Widget('agilebench', __('Agile Bench'), $widget_ops, $control_ops);
  }

  /**
   * Displays the Widget
   *
   */
  function widget($args, $instance){
    extract($args);
    $api_token = apply_filters('api_token', empty($instance['api_token']) ? '&nbsp;' : $instance['api_token']);
    $project_id = apply_filters('project_id', empty($instance['api_token']) ? '&nbsp;' : $instance['project_id']);
    $widget_type = apply_filters('widget_type', empty($instance['widget_type']) ? '&nbsp;' : $instance['widget_type']);

    require_once(ABSPATH."/wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php");

    switch($widget_type) {
      case "add_stories":
        $this->add_stories($api_token, $project_id);
        break;
      case "show_current_iteration":
        $this->show_current_iteration($api_token, $project_id);
        break;
      case "show_backlog":
        $this->show_backlog($api_token, $project_id);
        break;
    }
  }

  function add_stories($api_token, $project_id) {
    ?>
    <?php echo $before_widget ?>
    <?php echo $before_title
      . "Add Stories"
      . $after_title; ?>

    <?php
  }

  function show_backlog($api_token, $project_id) {
    $this->show_iteration($api_token, $project_id, AGILEBENCH_HOST . "/api/v1/projects/" . $project_id . "/backlog");
  }

  function show_current_iteration($api_token, $project_id) {
    $this->show_iteration($api_token, $project_id, AGILEBENCH_HOST . "/api/v1/projects/" . $project_id . "/current_iteration");
  }

  function show_iteration($api_token, $project_id, $uri) {
    $jsObj = new Moxiecode_JSON();
    $response = do_request($uri,
                           nil,
                           'X-AgileBench-Token: ' . $api_token . "\r\n" .
                           "Content-Type: application/json\r\n");
    $return_code = $response[0];
    $ab_data = $response[1];

    //decodes supplied JSON to a PHP array
    $json_array = $jsObj->decode($ab_data);

    if($return_code >= 400 && $return_code < 500) {
      echo $json_array["error"];
      return;
    }

    $title = $json_array["iteration"]["title"];

    echo $before_widget;
    echo $before_title
      . $title
      . $after_title;

    // PHP 5
    foreach ($json_array["iteration"]["stories"] as &$story) {
  ?>
      <ul class="agile_bench_story">
        <li>
          <span class="story_label">[<?php echo $story["label"] ?>]</span>
          <span class="story_type <?php echo $story["story_type"] ?>"><?php echo $story["story_type"] ?></span>
          <a href="<?php _e($story["uri"]) ?>" class="story_title"><?php echo $story["title"] ?></a>
          <span class="story_assigned"><?php echo $story["assigned"] ?></span>
          <span class="story_estimate"><?php echo $story["estimate"] ?></span>
        </li>
      </ul>

  <?php
    }
  ?>

      <p><a href="<?php _e(AGILEBENCH_HOST) ?>/projects/<?php _e($project_id) ?>" class="link_to_agile_bench">View Project at Agile Bench</a></p>

  <?php
    echo $after_widget;
    // echo var_dump($json_array);
  }

  /**
   * Saves the widgets settings.
   *
   */
  function update($new_instance, $old_instance){
    $instance = $old_instance;
    $instance['api_token'] = strip_tags($new_instance['api_token']);
    $instance['project_id'] = strip_tags($new_instance['project_id']);

    if ( in_array( $new_instance['widget_type'], array( 'add_stories', 'show_current_iteration', 'show_backlog' ) ) ) {
      $instance['widget_type'] = $new_instance['widget_type'];
    } else {
      $instance['widget_type'] = 'show_backlog';
    }

    return $instance;
  }

  /**
   * Creates the edit form for the widget.
   *
   */
  function form($instance){
    //Defaults
    $instance = wp_parse_args( (array) $instance, array( 'widget_type' => 'show_backlog', 'api_token' => '', 'exclude' => '') );
    $api_token = esc_attr( $instance['api_token'] );
    $project_id = esc_attr( $instance['project_id'] );

  ?>
      <p>
        <label for="<?php echo $this->get_field_id('api_token'); ?>"><?php _e('API Token:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('api_token'); ?>" name="<?php echo $this->get_field_name('api_token'); ?>" type="text" value="<?php echo $api_token; ?>" />
        <small><?php _e( 'Your API Token is available within your account settings' ); ?></small>
      </p>

      <p>
        <label for="<?php echo $this->get_field_id('project_id'); ?>"><?php _e('Project Id:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('project_id'); ?>" name="<?php echo $this->get_field_name('project_id'); ?>" type="text" value="<?php echo $project_id; ?>" />
        <small><?php _e( 'Your Project Id is found in your Agile Bench URL - http://agilebench.com/projects/<strong>YOUR_PROJECT_ID</strong>' ); ?></small>
      </p>
      <p>
        <label for="<?php echo $this->get_field_id('widget_type'); ?>"><?php _e( 'Widget Type:' ); ?></label>
        <select name="<?php echo $this->get_field_name('widget_type'); ?>" id="<?php echo $this->get_field_id('widget_type'); ?>" class="widefat">
<?php
//         <option value="add_stories"<?php selected( $instance['widget_type'], 'add_stories' ); ?>><?php _e('Add Stories'); ?></option>
?>
          <option value="show_current_iteration"<?php selected( $instance['widget_type'], 'show_current_iteration' ); ?>><?php _e('Show Current Iteration'); ?></option>
          <option value="show_backlog"<?php selected( $instance['widget_type'], 'show_backlog' ); ?>><?php _e( 'Show Backlog' ); ?></option>
        </select>
      </p>

  <?php
  }
}// END class

/**
  * Register Hello World widget.
  *
  * Calls 'widgets_init' action after the Hello World widget has been registered.
  */
  function AgileBenchInit() {
    register_widget('AgileBenchWidget');
  }

  add_action('widgets_init', 'AgileBenchInit');
?>
