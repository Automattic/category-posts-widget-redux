<?php
/*
Plugin Name: Category Posts Widget
Plugin URI: https://github.com/Automattic/category-posts-widget-redux
Description: Adds a widget that can configurably display posts via category.
 Forked from https://github.com/jlao/wp-category-posts-widget & https://wordpress.org/plugins/category-posts/
Author: James Lao
Contributors: Automattic
Version: 3.3p-a8c1
Author URI: http://jameslao.com/
*/

// Register thumbnail sizes.
if ( function_exists( 'add_image_size' ) ) {
	$sizes = get_option( 'jlao_cat_post_thumb_sizes' );
	if ( $sizes ) {
		foreach ( $sizes as $id=>$size ) {
			add_image_size( 'cat_post_thumb_size' . $id, $size[0], $size[1], true );
		}
	}
}

class WP_Category_Posts_Widget_Redux extends WP_Widget {
	const BASE_ID = 'wp-category-posts-widget';
	const CLASS_NAME = 'wp_cat_posts';
	const DISPLAY_NAME = 'Category Posts';
	const TEXT_DOMAIN = 'wp-category-posts-widget';
	const VERSION = '3.3p-a8c1';

	function __construct() {
		$arguments = array(
			'classname' => self::CLASS_NAME,
			'description' => esc_html__( 'Configurably display posts via category.', self::TEXT_DOMAIN ),
		);

		parent::__construct(
			self::BASE_ID,
			self::DISPLAY_NAME,
			$arguments
		);
	}

	/**
	 * Displays category posts widget on blog.
	 */
	function widget( $args, $instance ) {
		global $post;
		$post_old = $post; // Save the post object.

		$sizes = get_option( 'jlao_cat_post_thumb_sizes' );

		// avoid PHP notices
		$title = isset( $instance[ 'title'] ) ? $instance[ 'title'] : '';
		$cat = isset( $instance[ 'cat' ] ) ? $instance[ 'cat' ] : false;
		$num = isset( $instance[ 'num' ] ) ? $instance[ 'num' ] : 0;
		$sort_by = isset( $instance[ 'sort_by' ] ) ? $instance[ 'sort_by' ] : null;
		$asc_sort_order = isset( $instance[ 'asc_sort_order' ] ) ? $instance[ 'asc_sort_order' ] : null;
		$title_link = isset( $instance[ 'title_link' ] ) ? $instance[ 'title_link' ] : null;
		$excerpt = isset( $instance[ 'excerpt' ] ) ? $instance[ 'excerpt' ] : null;
		$excerpt_length = isset( $instance[ 'excerpt_length' ] ) ? $instance[ 'excerpt_length' ] : null;
		$comment_num = isset( $instance[ 'comment_num' ] ) ? $instance[ 'comment_num' ] : null;
		$date = isset( $instance[ 'date' ] ) ? $instance[ 'date' ] : null;
		$thumb = isset( $instance[ 'thumb' ] ) ? $instance[ 'thumb' ] : null;
		$thumb_w = isset( $instance[ 'thumb_w' ] ) ? $instance[ 'thumb_w' ] : null;
		$thumb_h = isset( $instance[ 'thumb_h' ] ) ? $instance[ 'thumb_h' ] : null;

		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$before_title = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title = isset( $args['after_title'] ) ? $args['after_title'] : '';
		$after_widget = isset( $args['after_widget'] ) ? $args['after_widget'] : '';

		// If not title, use the name of the category.
		if( ! $title ) {
			$category_info = get_category( $cat );
			$title = $category_info->name;
		}

		$valid_sort_orders = array(
			'date',
			'title',
			'comment_count',
			'rand',
		);

		if ( in_array( $sort_by, $valid_sort_orders ) ) {
			$sort_order = $asc_sort_order ? 'ASC' : 'DESC';
		} else {
			// by default, display latest first
			$sort_by = 'date';
			$sort_order = 'DESC';
		}

		$arg_string = 'showposts=' . $num .
			'&cat=' . $cat .
			'&orderby=' . $sort_by .
			'&order=' . $sort_order;

		$use_cache = apply_filters( 'category_posts_widget_use_cache', true );
		if ( $use_cache ) {
			$cache_key = self::get_cache_key();
			$cached_widget_contents = wp_cache_get( $cache_key, 'widget' );
			if ( ! empty( $cached_widget_contents ) ) {

				// Cache Hit!  Print the cached contents and clean up
				echo wp_kses_post( $cached_widget_contents );
				$post = $post_old;
				return;
			}
		}

		// Cache miss. Build the content of the widget.

		$output = '';

		// Get array of post info.
		$cat_posts = new WP_Query( $arg_string );

		// Excerpt length filter
		if ( $excerpt_length > 0 ) {
			add_filter( 'excerpt_length', array( $this, 'excerpt_length_filter' ) );
		}

		$output .= wp_kses_post( $before_widget );

		// Widget title
		$output .= wp_kses_post( $before_title );
		if( $title_link ) {
			$output .= '<a href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $title ) . '</a>';
		}
		else {
			$output .= esc_html( $title );
		}

		$output .= wp_kses_post( $after_title );

		// Post list
		$output .= "<ul>\n";

		while ( $cat_posts->have_posts() ) {
			$cat_posts->the_post();
			$output .= '<li class="cat-post-item"><a class="post-title" href="' . esc_url( get_the_permalink() ) .
					'" rel="bookmark" title="Permanent link to ' . esc_html( the_title_attribute( 'echo=0' ) ) . '">' . esc_html( get_the_title() ) . '</a>';

			if (
				function_exists('the_post_thumbnail') &&
				current_theme_supports("post-thumbnails") &&
				$thumb &&
				has_post_thumbnail()
			) {
				$output .= '<a href="' . esc_url( get_the_permalink() ) . '" title="' . esc_attr( get_the_title_attribute() ) . '">' .
					esc_html( get_the_post_thumbnail( 'cat_post_thumb_size' . $this->id ) ) . '</a>';
			}

			if ( $date ) {
				$output .= '<p class="post-date">' . esc_html( get_the_time("j M Y") ) . '</p>';
			}

			if ( $excerpt ) {
				$output .= '<div class="cat-posts-widget-excerpt">' . wp_kses_post( get_the_excerpt() ) . '</div>';
			}

			if ( $comment_num ) {
				$comment_output = '';

					// safer clone of core comments_number() function
					$number = get_comments_number();

					if ( $number == 0 ) {
						// By default, suppress the "No Comments" label when none are present
						if ( apply_filters( 'category_posts_widget_show_no_comments', false ) ) {
							$comment_output = __( 'No Comments', self::TEXT_DOMAIN );
						}
					}
					else {
						$formatted_number = number_format_i18n( $number );
						$comment_output = sprintf( _nx(
							'1 Comment',
							'%s Comments',
							$formatted_number,
							'Label for the number (integer) of comments on a particular post',
							self::TEXT_DOMAIN
						), $formatted_number );
					}

					$comment_output = apply_filters( 'category_posts_widget_comment_output', $comment_output, $number );

					if ( ! empty( $comment_output ) ) {
						$output .= '<p class="comment-num">(<span>';
						$output .= esc_html( $comment_output );
						$output .= '</span>)</p>';
					}
			}
			$output .= '</li>';
		}

		$output .= "</ul>\n";

		$output .= wp_kses_post( $after_widget );

		remove_filter( 'excerpt_length', array( $this, 'excerpt_length_filter' ) );

		$post = $post_old; // Restore the post object.

		$output = wp_kses_post( $output );
		echo $output;

		$save_cache = apply_filters( 'category_posts_widget_save_cache', $use_cache );
		if ( $save_cache ) {
			if ( empty( $cache_key ) ) {
				$cache_key = self::get_cache_key();
			}

			$save_blocked = wp_cache_get( $cache_key . '-save_blocked', 'widget' );
			if ( $save_blocked ) {
				return;
			}

			// Max is limited by the liftetime of the nonce in get_cache_key
			$cache_expires = apply_filters( 'category_posts_widget_cache_expires', 30 * MINUTE_IN_SECONDS );

			wp_cache_set( $cache_key, $output, 'widget', $cache_expires );
		}
	}

	/**
	 * Form processing... Dead simple.
	 */
	function update( $new_instance, $old_instance ) {
		/**
		 * Save the thumbnail dimensions outside so we can
		 * register the sizes easily. We have to do this
		 * because the sizes must registered beforehand
		 * in order for WP to hard crop images (this in
		 * turn is because WP only hard crops on upload).
		 * The code inside the widget is executed only when
		 * the widget is shown so we register the sizes
		 * outside of the widget class.
		 */

		if ( function_exists( 'the_post_thumbnail' ) ) {
			$sizes = get_option('jlao_cat_post_thumb_sizes');
			if ( !$sizes ) {
				$sizes = array();
			}
			$thumb_w = isset( $new_instance[ 'thumb_w' ] ) ? $new_instance[ 'thumb_w' ] : null;
			$thumb_h = isset( $new_instance[ 'thumb_h' ] ) ? $new_instance[ 'thumb_h' ] : null;
			$sizes[$this->id] = array( $thumb_w, $thumb_h );
			update_option( 'jlao_cat_post_thumb_sizes', $sizes );
		}

		return $new_instance;
	}

	/**
	 * The configuration form.
	 */
	function form( $instance ) {
		// avoid PHP notices
		$title = isset( $instance[ 'title'] ) ? $instance[ 'title'] : '';
		$cat = isset( $instance[ 'cat' ] ) ? $instance[ 'cat' ] : false;
		$num = isset( $instance[ 'num' ] ) ? $instance[ 'num' ] : 0;
		$sort_by = isset( $instance[ 'sort_by' ] ) ? $instance[ 'sort_by' ] : null;
		$asc_sort_order = isset( $instance[ 'asc_sort_order' ] ) ? $instance[ 'asc_sort_order' ] : null;
		$title_link = isset( $instance[ 'title_link' ] ) ? $instance[ 'title_link' ] : null;
		$excerpt = isset( $instance[ 'excerpt' ] ) ? $instance[ 'excerpt' ] : null;
		$excerpt_length = isset( $instance[ 'excerpt_length' ] ) ? $instance[ 'excerpt_length' ] : null;
		$comment_num = isset( $instance[ 'comment_num' ] ) ? $instance[ 'comment_num' ] : null;
		$date = isset( $instance[ 'date' ] ) ? $instance[ 'date' ] : null;
		$thumb = isset( $instance[ 'thumb' ] ) ? $instance[ 'thumb' ] : null;
		$thumb_w = isset( $instance[ 'thumb_w' ] ) ? $instance[ 'thumb_w' ] : null;
		$thumb_h = isset( $instance[ 'thumb_h' ] ) ? $instance[ 'thumb_h' ] : null;
	?>
	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("title") );
		?>"><?php esc_html_e( 'Title:', self::TEXT_DOMAIN ); ?>
		<input class="widefat" id="<?php
			echo esc_attr( $this->get_field_id( 'title' ) );
		?>" name="<?php
			echo esc_attr( $this->get_field_name( 'title' ) );
		?>" type="text" value="<?php
			echo esc_attr( $title );
		?>" />
	</label>
	</p>

	<p>
	<label>
		<?php esc_html_e( 'Category:', self::TEXT_DOMAIN ); ?>
		<?php wp_dropdown_categories(
			array(
				'name' => esc_attr( $this->get_field_name( 'cat' ) ),
				'selected' => (int) $cat,
			)
		); ?>
	</label>
	</p>

	<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'num' ) ); ?>">
		<?php esc_html_e( 'Number of posts to show', self::TEXT_DOMAIN ); ?>:
		<input type="number" min="0" style="text-align: center; width: 20%; margin-left: 5px" id="<?php
			echo esc_attr( $this->get_field_id( 'num' ) );
		?>" name="<?php
			echo esc_attr( $this->get_field_name( 'num' ) );
		?>" type="text" value="<?php
			echo absint( $num );
		?>" size='3' />
	</label>
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id( 'sort_by' ) );
	?>">
	<?php esc_html_e( 'Sort by', self::TEXT_DOMAIN ); ?>:
	<select id="<?php
	echo esc_attr( $this->get_field_id("sort_by") );
	?>" name="<?php
	echo esc_attr( $this->get_field_name("sort_by") );
	?>">
	<option value="date"<?php selected( $sort_by, "date" ); ?>><?php
		esc_html_e( 'Date', self::TEXT_DOMAIN );
	?></option>
	<option value="title"<?php selected( $sort_by, "title" ); ?>><?php
		esc_html_e( 'Title', self::TEXT_DOMAIN );
	?></option>
	<option value="comment_count"<?php selected( $sort_by, "comment_count" ); ?>><?php
		esc_html_e( 'Number of comments', self::TEXT_DOMAIN );
	?></option>
	<option value="rand"<?php selected( $sort_by, "rand" ); ?>><?php
		esc_html_e( 'Random', self::TEXT_DOMAIN );
	?></option>
	</select>
	</label>
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("asc_sort_order") );
	?>">
	<input type="checkbox" class="checkbox"
	id="<?php
		echo esc_attr( $this->get_field_id("asc_sort_order") );
	?>"
	name="<?php
		echo esc_attr( $this->get_field_name("asc_sort_order") );
	?>"
		<?php checked( (bool) $asc_sort_order, true ); ?> />
		<?php esc_html_e( 'Reverse sort order (ascending)', self::TEXT_DOMAIN ); ?>
	</label>
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("title_link") );
	?>">
		<input type="checkbox" class="checkbox" id="<?php
			echo esc_attr( $this->get_field_id("title_link") );
		?>" name="<?php
			echo esc_attr( $this->get_field_name("title_link") );
		?>"<?php checked( (bool) $title_link, true ); ?> />
		<?php esc_html_e( 'Make widget title link', self::TEXT_DOMAIN ); ?>
	</label>
	</p>

	<p>
	<label for="<?php
			echo esc_attr( $this->get_field_id("excerpt") ); ?>">
		<input type="checkbox" class="checkbox" id="<?php
			echo esc_attr( $this->get_field_id("excerpt") );
		?>" name="<?php
			echo esc_attr( $this->get_field_name("excerpt") );
		?>"<?php checked( (bool) $excerpt, true ); ?> />
		<?php esc_html_e( 'Show post excerpt', self::TEXT_DOMAIN ); ?>
	</label>
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("excerpt_length") ); ?>">
		<?php esc_html_e( 'Excerpt length (in words):', self::TEXT_DOMAIN ); ?>
	</label>
	<input style="text-align: center; width: 20%; margin-left: 5px" type="number" min="0" id="<?php
		echo esc_attr( $this->get_field_id("excerpt_length") ); ?>" name="<?php
		echo esc_attr( $this->get_field_name("excerpt_length") ); ?>" value="<?php
		echo esc_attr( $excerpt_length ); ?>" size="3" />
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("comment_num") );
	?>">
		<input type="checkbox" class="checkbox" id="<?php
			echo esc_attr( $this->get_field_id("comment_num") );
		?>" name="<?php
			echo esc_attr( $this->get_field_name("comment_num") );
		?>"<?php checked( (bool) $comment_num, true ); ?> />
		<?php esc_html_e( 'Show number of comments', self::TEXT_DOMAIN ); ?>
	</label>
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("date") );
	?>">
		<input type="checkbox" class="checkbox" id="<?php
			echo esc_attr( $this->get_field_id("date") );
	?>" name="<?php
		echo esc_attr( $this->get_field_name("date") );
	?>"<?php checked( (bool) $date, true ); ?> />
		<?php esc_html_e( 'Show post date', self::TEXT_DOMAIN ); ?>
	</label>
	</p>

	<?php if ( function_exists('the_post_thumbnail') && current_theme_supports("post-thumbnails") ) : ?>
	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("thumb") ); ?>">
		<input type="checkbox" class="checkbox" id="<?php
			echo esc_attr( $this->get_field_id("thumb") );
		?>" name="<?php
			echo esc_attr( $this->get_field_name("thumb") );
		?>"<?php checked( (bool) $thumb, true ); ?> />
		<?php esc_html_e( 'Show post thumbnail', self::TEXT_DOMAIN ); ?>
	</label>
	</p>
	<p>
	<label>
		<?php esc_html_e( 'Thumbnail dimensions', self::TEXT_DOMAIN ); ?>:<br />
		<label for="<?php
			echo esc_attr( $this->get_field_id("thumb_w") ); ?>">
			<?php esc_html_e( 'Width:', self::TEXT_DOMAIN ); ?>
			<input class="widefat" style="width:20%; margin-left: 5px;" type="number" min="0" id="<?php
				echo esc_attr( $this->get_field_id("thumb_w") );
			?>" name="<?php
				echo esc_attr( $this->get_field_name("thumb_w") );
			?>" value="<?php
				echo esc_attr( $thumb_w );
			?>" />
		</label>

		<label style="margin-left: 10px;" for="<?php
			echo esc_attr( $this->get_field_id("thumb_h") ); ?>">
			<?php esc_html_e( 'Height:', self::TEXT_DOMAIN ); ?>
			<input class="widefat" style="width:20%; margin-left: 5px;" type="number" min="0" id="<?php
				echo esc_attr( $this->get_field_id("thumb_h") );
			?>" name="<?php
				echo esc_attr( $this->get_field_name("thumb_h") );
			?>" value="<?php
				echo esc_attr( $thumb_h ); ?>" />
		</label>
	</label>
	</p>
	<?php endif;
	}

	function flush_cache() {
		$use_cache = apply_filters( 'category_posts_widget_use_cache', true );
		if ( ! $use_cache ) {
			return;
		}

		$cache_key = self::get_cache_key();

		// Block updating the cache for an arbitrary amount of time to give the delete opportunity to propagate
		$block_save_cache_seconds = (int) apply_filters( 'category_posts_widget_block_save_cache_seconds', 10 );
		if ( $block_save_cache_seconds > 0 ) {
			wp_cache_set( $cache_key . '-save_blocked', 1, 'widget', $block_save_cache_seconds );
		}

		wp_cache_delete( $cache_key, 'widget' );
	}

	function excerpt_length_filter( $length ) {
		$settings = $this->get_settings();
		if ( ! isset( $settings[$this->number] ) || ! isset( $settings[$this->number]['excerpt_length'] ) ) {
			return $length;
		}

		$excerpt_length = absint( $settings[$this->number]['excerpt_length'] );
		return $excerpt_length;
	}

	static function get_cache_key() {
		$key = 'category_posts_widget-' . self::VERSION . '-' . get_current_blog_id() . '-';
		$key .= wp_create_nonce( $key );
		return $key;
	}

	static function widgets_init() {
		return register_widget( 'WP_Category_Posts_Widget_Redux' );
	}

}

add_action( 'widgets_init', array( 'WP_Category_Posts_Widget_Redux', 'widgets_init' ) );

// Invalidate our cache on certain events
$flush_cache_callable = array( 'WP_Category_Posts_Widget_Redux', 'flush_cache' );
$update_option_action = 'update_option_widget_' . WP_Category_Posts_Widget_Redux::BASE_ID;
add_action( 'edit_post',                             $flush_cache_callable );
add_action( 'deleted_post',                          $flush_cache_callable );
add_action( 'deleted_comment',                       $flush_cache_callable );
add_action( $update_option_action,                   $flush_cache_callable );
