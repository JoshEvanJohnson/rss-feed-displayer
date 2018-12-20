<?php
/*
Plugin Name: RSS Feed Displayer
Plugin URI: https://github.com/JoshEvanJohnson/rss-feed-displayer/
Description: A WordPress plugin built to allow a clean display of an RSS feed in both widget and short-code.
Version: 0.1.0
Author: JoshEvanJohnson
Author URI: https://joshuaevanjohnson.com/
Text Domain: rss-feed-displayer
*/

/**
 * Core class used to implement a RSS widget.
 *
 * @since 2.8.0
 *
 * @see WP_Widget
 */
class widget_rss_feed_displayer extends WP_Widget {

	/**
	 * Sets up a new RSS widget instance.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$widget_ops = array(
			'description' => __( 'Display entries from any RSS or Atom feed.' ),
			'customize_selective_refresh' => true,
		);
		$control_ops = array( 'width' => 400, 'height' => 200 );
		parent::__construct( 'rss_feed_displayer', __( 'RSS Feed Displayer' ), $widget_ops, $control_ops );
	}

	/**
	 * Outputs the content for the current RSS widget instance.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current RSS widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( isset($instance['error']) && $instance['error'] )
			return;

		$url = ! empty( $instance['url'] ) ? $instance['url'] : '';
		while ( stristr($url, 'http') != $url )
			$url = substr($url, 1);

		if ( empty($url) )
			return;

		// self-url destruction sequence
		if ( in_array( untrailingslashit( $url ), array( site_url(), home_url() ) ) )
			return;

		$rss = fetch_feed($url);
		$title = $instance['title'];
		$desc = '';
		$link = '';

		if ( ! is_wp_error($rss) ) {
			$desc = esc_attr(strip_tags(@html_entity_decode($rss->get_description(), ENT_QUOTES, get_option('blog_charset'))));
			if ( empty($title) )
				$title = strip_tags( $rss->get_title() );
			$link = strip_tags( $rss->get_permalink() );
			while ( stristr($link, 'http') != $link )
				$link = substr($link, 1);
		}

		if ( empty( $title ) ) {
			$title = ! empty( $desc ) ? $desc : __( 'Unknown Feed' );
		}

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$url = strip_tags( $url );
		$icon = includes_url( 'images/rss.png' );
		if ( $title )
			$title = '<a class="rsswidget" href="' . esc_url( $url ) . '"><img class="rss-widget-icon" style="border:0" width="14" height="14" src="' . esc_url( $icon ) . '" alt="RSS" /></a> <a class="rsswidget" href="' . esc_url( $link ) . '">'. esc_html( $title ) . '</a>';

		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
		self::rss_output( $rss, $instance );
		echo $args['after_widget'];

		if ( ! is_wp_error($rss) )
			$rss->__destruct();
		unset($rss);
	}

	/**
	 * Handles updating settings for the current RSS widget instance.
	 *
	 * @since 0.1.0
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$testurl = ( isset( $new_instance['url'] ) && ( !isset( $old_instance['url'] ) || ( $new_instance['url'] != $old_instance['url'] ) ) );
		return self::widget_rss_feed_displayer_process( $new_instance, $testurl );
	}

	/**
	 * Outputs the settings form for the RSS widget.
	 *
	 * @since 0.1.0
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		if ( empty( $instance ) ) {
			$instance = array( 'title' => '', 'url' => '', 'items' => 10, 'error' => false, 'show_summary' => 0, 'show_author' => 0, 'show_date' => 0 );
		}
		$instance['number'] = $this->number;

		self::widget_rss_feed_displayer_form( $instance );
	}

	/**
	 * Outputs the actual rss content.
	 *
	 * @since 0.1.0
	 *
	 * @param array $instance Current settings.
	 */
  function rss_output( $rss, $args = array() ) {
      if ( is_string( $rss ) ) {
          $rss = fetch_feed($rss);
      } elseif ( is_array($rss) && isset($rss['url']) ) {
          $args = $rss;
          $rss = fetch_feed($rss['url']);
      } elseif ( !is_object($rss) ) {
          return;
      }

      if ( is_wp_error($rss) ) {
          if ( is_admin() || current_user_can('manage_options') )
              echo '<p><strong>' . __( 'RSS Error:' ) . '</strong> ' . $rss->get_error_message() . '</p>';
          return;
      }

      $default_args = array( 'show_author' => 0, 'show_date' => 0, 'show_summary' => 0, 'items' => 0 );
      $args = wp_parse_args( $args, $default_args );

      $items = (int) $args['items'];
      if ( $items < 1 || 20 < $items )
          $items = 10;
      $show_summary  = (int) $args['show_summary'];
      $show_author   = (int) $args['show_author'];
      $show_date     = (int) $args['show_date'];

      if ( !$rss->get_item_quantity() ) {
          echo '<ul><li>' . __( 'An error has occurred, which probably means the feed is down. Try again later.' ) . '</li></ul>';
          $rss->__destruct();
          unset($rss);
          return;
      }

      echo '<div class="vc_row wpb_row vc_inner vc_row-fluid">';

      //echo '<ul>';
      foreach ( $rss->get_items( 0, $items ) as $item ) {
          $link = $item->get_link();
          while ( stristr( $link, 'http' ) != $link ) {
              $link = substr( $link, 1 );
          }
          $link = esc_url( strip_tags( $link ) );

          $title = esc_html( trim( strip_tags( $item->get_title() ) ) );
          if ( empty( $title ) ) {
              $title = __( 'Untitled' );
          }

          $desc = @html_entity_decode( $item->get_description(), ENT_QUOTES, get_option( 'blog_charset' ) );

          if($item->get_description() == ''){
            $final_image = '<img src="" alt="no description found" title="no description found" />';
          } else {
            preg_match_all('/<img[^>]+>/i', $item->get_description(), $image);
            if(count($image) !== 0){
              if(count($image[0]) == 0){
                $final_image = '<img src="" alt="no image found after preg_match_all second" title="no image found after preg_match_all second" />';
              } else {
                $final_image = $image[0][0];
              }
            } else {
              $final_image = '<img src="" alt="no image found after preg_match_all first" title="no image found after preg_match_all first" />';
            }
            // use this to get all the image attributes
            // preg_match_all('/(alt|title|src)=("[^"]*")/i',$image, $image_tags);
          }

          // strip the style tag from the image
          $final_image = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $final_image);

          $desc = esc_attr( wp_trim_words( $desc, 20, ' [&hellip;]' ) );
          $summary = '';
          if ( $show_summary ) {
              $summary = $desc;

              // Change existing [...] to [&hellip;].
              if ( '[...]' == substr( $summary, -5 ) ) {
                  $summary = substr( $summary, 0, -5 ) . '[&hellip;]';
              }

              $summary = esc_html( $summary );
          }

          $date = '';
          if ( $show_date ) {
              $date = $item->get_date( 'U' );

              if ( $date ) {
                  $date = ' <span class="rss-date">' . date_i18n( get_option( 'date_format' ), $date ) . '</span>';
              }
          }

          $author = '';
          if ( $show_author ) {
              $author = $item->get_author();
              if ( is_object($author) ) {
                  $author = $author->get_name();
                  $author = ' <cite>' . esc_html( strip_tags( $author ) ) . '</cite>';
              }
          }

          $final_image = '<div class="stm_infobox__image">' . $final_image . '</div>';
          echo '  <div class="wpb_column vc_column_container vc_col-sm-4 pearl_column_inner_'.wp_generate_uuid4().'">';
          echo '    <div class="vc_column-inner ">';
          echo '      <div class="wpb_wrapper">';

          if ( $link == '' ) {
              echo "<span class='stm_infobox stm_infobox_style_8'>$title{$date}{$summary}{$author}</span>";
          } elseif ( $show_summary ) {
              echo "<a class='stm_infobox stm_infobox_style_8' href='$link' title='Learn More'>{$final_image}<div class='clearfix'><div class='stm_infobox__content'><p class='sub'>{$date}{$summary}{$author}</p>
              <span class='hidden'>Learn More</span></div></div></a>";
          } else {
              echo "<a class='stm_infobox stm_infobox_style_8' href='$link' title='Learn More'>{$final_image}<div class='clearfix'><div class='stm_infobox__content'><p><strong>{$title}</strong></p><p class='sub'>{$date}{$author}</p>
              <span class='hidden'>Learn More</span></div></div></a>";
          }
          echo '</div></div></div>';
      }
      echo '</div>';
      $rss->__destruct();
      unset($rss);
  }

  function widget_rss_feed_displayer_process( $widget_rss, $check_feed = true ) {
      $items = (int) $widget_rss['items'];
      if ( $items < 1 || 20 < $items )
          $items = 10;
      $url           = esc_url_raw( strip_tags( $widget_rss['url'] ) );
      $title         = isset( $widget_rss['title'] ) ? trim( strip_tags( $widget_rss['title'] ) ) : '';
      $show_summary  = isset( $widget_rss['show_summary'] ) ? (int) $widget_rss['show_summary'] : 0;
      $show_author   = isset( $widget_rss['show_author'] ) ? (int) $widget_rss['show_author'] :0;
      $show_date     = isset( $widget_rss['show_date'] ) ? (int) $widget_rss['show_date'] : 0;

      if ( $check_feed ) {
          $rss = fetch_feed($url);
          $error = false;
          $link = '';
          if ( is_wp_error($rss) ) {
              $error = $rss->get_error_message();
          } else {
              $link = esc_url(strip_tags($rss->get_permalink()));
              while ( stristr($link, 'http') != $link )
                  $link = substr($link, 1);

              $rss->__destruct();
              unset($rss);
          }
      }

      return compact( 'title', 'url', 'link', 'items', 'error', 'show_summary', 'show_author', 'show_date' );
  }
  function widget_rss_feed_displayer_form( $args, $inputs = null ) {
      $default_inputs = array( 'url' => true, 'title' => true, 'items' => true, 'show_summary' => true, 'show_author' => true, 'show_date' => true );
      $inputs = wp_parse_args( $inputs, $default_inputs );

      $args['title'] = isset( $args['title'] ) ? $args['title'] : '';
      $args['url'] = isset( $args['url'] ) ? $args['url'] : '';
      $args['items'] = isset( $args['items'] ) ? (int) $args['items'] : 0;

      if ( $args['items'] < 1 || 20 < $args['items'] ) {
          $args['items'] = 10;
      }

      $args['show_summary']   = isset( $args['show_summary'] ) ? (int) $args['show_summary'] : (int) $inputs['show_summary'];
      $args['show_author']    = isset( $args['show_author'] ) ? (int) $args['show_author'] : (int) $inputs['show_author'];
      $args['show_date']      = isset( $args['show_date'] ) ? (int) $args['show_date'] : (int) $inputs['show_date'];

      if ( ! empty( $args['error'] ) ) {
          echo '<p class="widget-error"><strong>' . __( 'RSS Error:' ) . '</strong> ' . $args['error'] . '</p>';
      }

      $esc_number = esc_attr( $args['number'] );
      if ( $inputs['url'] ) :
  ?>
      <p><label for="<?php echo $this->get_field_id('url'); ?>"><?php _e( 'Enter the RSS feed URL here:' ); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('url'); ?>" name="<?php echo $this->get_field_name('url'); ?>" type="text" value="<?php echo esc_url( $args['url'] ); ?>" /></p>
  <?php endif; if ( $inputs['title'] ) : ?>
      <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Give the feed a title (optional):' ); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $args['title'] ); ?>" /></p>
  <?php endif; if ( $inputs['items'] ) : ?>
      <p><label for="<?php echo $this->get_field_id('items'); ?>"><?php _e( 'How many items would you like to display?' ); ?></label>
      <select id="<?php echo $this->get_field_id('items'); ?>" name="<?php echo $this->get_field_name('items'); ?>">
      <?php
      for ( $i = 1; $i <= 20; ++$i ) {
          echo "<option value='$i' " . selected( $args['items'], $i, false ) . ">$i</option>";
      }
      ?>
      </select></p>
  <?php endif; if ( $inputs['show_summary'] ) : ?>
      <p><input id="<?php echo $this->get_field_id('show_summary'); ?>" name="<?php echo $this->get_field_name('show_summary'); ?>" type="checkbox" value="1" <?php checked( $args['show_summary'] ); ?> />
      <label for="<?php echo $this->get_field_id('show_summary'); ?>"><?php _e( 'Display item content?' ); ?></label></p>
  <?php endif; if ( $inputs['show_author'] ) : ?>
      <p><input id="<?php echo $this->get_field_id('show_author'); ?>" name="<?php echo $this->get_field_name('show_author'); ?>" type="checkbox" value="1" <?php checked( $args['show_author'] ); ?> />
      <label for="<?php echo $this->get_field_id('show_author'); ?>"><?php _e( 'Display item author if available?' ); ?></label></p>
  <?php endif; if ( $inputs['show_date'] ) : ?>
      <p><input id="<?php echo $this->get_field_id('show_date'); ?>" name="<?php echo $this->get_field_name('show_date'); ?>" type="checkbox" value="1" <?php checked( $args['show_date'] ); ?>/>
      <label for="<?php echo $this->get_field_id('show_date'); ?>"><?php _e( 'Display item date?' ); ?></label></p>
  <?php
      endif;
      foreach ( array_keys($default_inputs) as $input ) :
          if ( 'hidden' === $inputs[$input] ) :
              $id = str_replace( '_', '-', $input );
  ?>
      <input type="hidden" id="<?php echo $this->get_field_id(esc_attr( $id )); ?>" name="<?php echo $this->get_field_name(esc_attr( $id )); ?>]" value="<?php echo esc_attr( $args[ $input ] ); ?>" />
  <?php
          endif;
      endforeach;
  }
}

function rss_feed_displayer_init(){
    register_widget('widget_rss_feed_displayer');
}
add_action('widgets_init', 'rss_feed_displayer_init');


function rss_feed_displayer($atts) {
    global $wp_widget_factory;

    $args = array('widget_id'=>'99999');

    extract(
      shortcode_atts(
        array(
          'title' => '',
          'url' => '',
          'items' => 10,
          'error' => false,
          'show_summary' => 0,
          'show_author' => 0,
          'show_date' => 0
        ),
        $atts
      )
    );

    $widget_name = 'widget_rss_feed_displayer';

    if (!is_a($wp_widget_factory->widgets[$widget_name], 'WP_Widget')):
        $wp_class = 'WP_Widget_'.ucwords(strtolower($class));

        if (!is_a($wp_widget_factory->widgets[$wp_class], 'WP_Widget')):
            return '<p>'.sprintf(__("%s: Widget class not found. Make sure this widget exists and the class name is correct"),'<strong>'.$class.'</strong>').'</p>';
        else:
            $class = $wp_class;
        endif;
    endif;

    ob_start();
    the_widget($widget_name, $atts, $args);
    $output = ob_get_contents();
    ob_end_clean();

    return $output;

}
add_shortcode('rss_feed_displayer','rss_feed_displayer');
