<?php
/*
Plugin Name: Category Posts Widget
Plugin URI: https://github.com/Automattic/wp-category-posts-widget
Description: Adds a widget that can configurably display posts via category. Forked from https://github.com/jlao/wp-category-posts-widget & https://wordpress.org/plugins/category-posts/
Author: James Lao
Contributors: Automattic
Version: 3.4.1-Automattic
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

class CategoryPosts extends WP_Widget {

	function __construct() {
		parent::WP_Widget( false, $name = 'Category Posts' );
	}

	/**
	 * Displays category posts widget on blog.
	 */
	function widget( $args, $instance ) {
		global $post;
		$post_old = $post; // Save the post object.

		$sizes = get_option( 'jlao_cat_post_thumb_sizes' );

		// If not title, use the name of the category.
		if( ! $instance[ 'title' ] ) {
			$category_info = get_category( $instance[ 'cat' ] );
			$instance[ 'title' ] = $category_info->name;
		}

		$valid_sort_orders = array(
			'date',
			'title',
			'comment_count',
			'rand',
		);

		if ( in_array( $instance['sort_by'], $valid_sort_orders ) ) {
			$sort_by = $instance['sort_by'];
			$sort_order = ( isset( $instance['asc_sort_order'] ) && $instance['asc_sort_order'] ) ? 'ASC' : 'DESC';
		} else {
			// by default, display latest first
			$sort_by = 'date';
			$sort_order = 'DESC';
		}

		$arg_string = 'showposts=' . $instance[ 'num' ] .
			'&cat=' . $instance[ 'cat' ] .
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
		$new_excerpt_length = create_function( '$length', "return " . $instance[ 'excerpt_length' ] . ';' );
		if ( $instance[ 'excerpt_length' ] > 0 ) {
			add_filter('excerpt_length', $new_excerpt_length);
		}

		$output .= wp_kses_post( $args['before_widget'] );

		// Widget title
		$output .= wp_kses_post( $args['before_title'] );
		if( $instance[ 'title_link' ] ) {
			$output .= '<a href="' . esc_url( get_category_link( $instance[ 'cat' ] ) ) . '">' . esc_html( $instance[ 'title' ] ) . '</a>';
		}
		else {
			$output .= esc_html( $instance[ 'title' ] );
		}

		$output .= wp_kses_post( $args['after_title'] );

		// Post list
		$output .= "<ul>\n";

		while ( $cat_posts->have_posts() ) {
			$cat_posts->the_post();
			$output .= '<li class="cat-post-item"><a class="post-title" href="' . esc_url( get_the_permalink() ) .
					'" rel="bookmark" title="Permanent link to ' . esc_html( the_title_attribute( 'echo=0' ) ) . '">' . esc_html( get_the_title() ) . '</a>';

			if (
				function_exists('the_post_thumbnail') &&
				current_theme_supports("post-thumbnails") &&
				$instance[ 'thumb' ] &&
				has_post_thumbnail()
			) {
				$output .= '<a href="' . esc_url( get_the_permalink() ) . '" title="' . esc_attr( get_the_title_attribute() ) . '">' .
					esc_html( get_the_post_thumbnail( 'cat_post_thumb_size' . $this->id ) ) . '</a>';
			}

			if ( $instance['date'] ) {
				$output .= '<p class="post-date">' . esc_html( get_the_time("j M Y") ) . '</p>';
			}

			if ( $instance['excerpt'] ) {
				$output .= wp_kses_post( get_the_excerpt() );
			}

			if ( $instance['comment_num'] ) {
				$output .= '<p class="comment-num">(';
					// safer clone of core comments_number() function
					$number = get_comments_number();
					if ( $number > 1 ) {
						$comment_output = str_replace( '%', number_format_i18n( $number ), __( '% Comments' ) );
					}
					elseif ( $number == 0 ) {
						$comment_output = __( 'No Comments' );
					}
					else {
						$comment_output = __( '1 Comment' );
					}
					$output .= esc_html( apply_filters( 'comments_number', $comment_output, $number ) );
				$output .= ')</p>';
			}
			$output .= '</li>';
		}

		$output .= "</ul>\n";

		$output .= wp_kses_post( $args['after_widget'] );

		remove_filter( 'excerpt_length', $new_excerpt_length );

		$post = $post_old; // Restore the post object.

		$output = wp_kses_post( $output );
		echo $output;

		$save_cache = apply_filters( 'category_posts_widget_save_cache', $use_cache );
		if ( $save_cache ) {
			// Max is limited by the liftetime of the nonce in get_cache_key
			$cache_expires = apply_filters( 'category_posts_widget_cache_expires', 30 * MINUTE_IN_SECONDS );

			if ( empty( $cache_key ) ) {
				$cache_key = self::get_cache_key();
			}

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
			$sizes[$this->id] = array( $new_instance['thumb_w'], $new_instance['thumb_h'] );
			update_option( 'jlao_cat_post_thumb_sizes', $sizes );
		}

		do_action( 'update_widget_category_posts' );

		return $new_instance;
	}

	/**
	 * The configuration form.
	 */
	function form( $instance ) {
	?>
	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("title") );
		?>"><?php esc_html_e( 'Title:' ); ?>
		<input class="widefat" id="<?php
			echo esc_attr( $this->get_field_id( 'title' ) );
		?>" name="<?php
			echo esc_attr( $this->get_field_name( 'title' ) );
		?>" type="text" value="<?php
			echo esc_attr( $instance[ 'title'] );
		?>" />
	</label>
	</p>

	<p>
	<label>
		<?php esc_html_e( 'Category:' ); ?>
		<?php wp_dropdown_categories(
			array(
				'name' => $this->get_field_name( 'cat' ),
				'selected' => $instance[ 'cat' ],
			)
		); ?>
	</label>
	</p>

	<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'num' ) ); ?>">
		<?php esc_html_e( 'Number of posts to show' ); ?>:
		<input type="number" style="text-align: center; width: 20%; margin-left: 5px" id="<?php
			echo esc_attr( $this->get_field_id( 'num' ) );
		?>" name="<?php
			echo esc_attr( $this->get_field_name( 'num' ) );
		?>" type="text" value="<?php
			echo absint( $instance[ 'num' ] );
		?>" size='3' />
	</label>
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id( 'sort_by' ) );
	?>">
	<?php esc_html_e( 'Sort by' ); ?>:
	<select id="<?php
	echo esc_attr( $this->get_field_id("sort_by") );
	?>" name="<?php
	echo esc_attr( $this->get_field_name("sort_by") );
	?>">
	<option value="date"<?php selected( $instance[ 'sort_by' ], "date" ); ?>><?php
		esc_html_e( 'Date' );
	?></option>
	<option value="title"<?php selected( $instance[ 'sort_by' ], "title" ); ?>><?php
		esc_html_e( 'Title' );
	?></option>
	<option value="comment_count"<?php selected( $instance[ 'sort_by' ], "comment_count" ); ?>><?php
		esc_html_e( 'Number of comments' );
	?></option>
	<option value="rand"<?php selected( $instance[ 'sort_by' ], "rand" ); ?>><?php
		esc_html_e( 'Random' );
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
		<?php checked( (bool) $instance[ 'asc_sort_order' ], true ); ?> />
		<?php esc_html_e( 'Reverse sort order (ascending)' ); ?>
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
		?>"<?php checked( (bool) $instance[ 'title_link' ], true ); ?> />
		<?php esc_html_e( 'Make widget title link' ); ?>
	</label>
	</p>

	<p>
	<label for="<?php
			echo esc_attr( $this->get_field_id("excerpt") ); ?>">
		<input type="checkbox" class="checkbox" id="<?php
			echo esc_attr( $this->get_field_id("excerpt") );
		?>" name="<?php
			echo esc_attr( $this->get_field_name("excerpt") );
		?>"<?php checked( (bool) $instance[ 'excerpt' ], true ); ?> />
		<?php esc_html_e( 'Show post excerpt' ); ?>
	</label>
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("excerpt_length") ); ?>">
		<?php esc_html_e( 'Excerpt length (in words):' ); ?>
	</label>
	<input style="text-align: center; width: 20%; margin-left: 5px" type="number" id="<?php
		echo esc_attr( $this->get_field_id("excerpt_length") ); ?>" name="<?php
		echo esc_attr( $this->get_field_name("excerpt_length") ); ?>" value="<?php
		echo esc_attr( $instance[ 'excerpt_length' ] ); ?>" size="3" />
	</p>

	<p>
	<label for="<?php
		echo esc_attr( $this->get_field_id("comment_num") );
	?>">
		<input type="checkbox" class="checkbox" id="<?php
			echo esc_attr( $this->get_field_id("comment_num") );
		?>" name="<?php
			echo esc_attr( $this->get_field_name("comment_num") );
		?>"<?php checked( (bool) $instance[ 'comment_num' ], true ); ?> />
		<?php esc_html_e( 'Show number of comments' ); ?>
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
	?>"<?php checked( (bool) $instance[ 'date' ], true ); ?> />
		<?php esc_html_e( 'Show post date' ); ?>
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
		?>"<?php checked( (bool) $instance[ 'thumb' ], true ); ?> />
		<?php esc_html_e( 'Show post thumbnail' ); ?>
	</label>
	</p>
	<p>
	<label>
		<?php esc_html_e('Thumbnail dimensions'); ?>:<br />
		<label for="<?php
			echo esc_attr( $this->get_field_id("thumb_w") ); ?>">
			<?php esc_html_e( 'Width:' ); ?>
			<input class="widefat" style="width:20%; margin-left: 5px;" type="number" id="<?php
				echo esc_attr( $this->get_field_id("thumb_w") );
			?>" name="<?php
				echo esc_attr( $this->get_field_name("thumb_w") );
			?>" value="<?php
				echo esc_attr( $instance[ 'thumb_w' ] );
			?>" />
		</label>

		<label style="margin-left: 10px;" for="<?php
			echo esc_attr( $this->get_field_id("thumb_h") ); ?>">
			<?php esc_html_e( 'Height:' ); ?>
			<input class="widefat" style="width:20%; margin-left: 5px;" type="number" id="<?php
				echo esc_attr( $this->get_field_id("thumb_h") );
			?>" name="<?php
				echo esc_attr( $this->get_field_name("thumb_h") );
			?>" value="<?php
				echo esc_attr( $instance[ 'thumb_h' ] ); ?>" />
		</label>
	</label>
	</p>
	<?php endif;
	}

	function flush_cache() {
		$cache_key = self::get_cache_key();
		wp_cache_delete( $cache_key, 'widget' );
	}

	static function get_cache_key() {
		$key = 'category_posts_widget-' . get_current_blog_id() . '-';
		$key .= wp_create_nonce( $key );
		return $key;
	}

}

add_action( 'widgets_init', create_function('', 'return register_widget("CategoryPosts");') );

// Invalidate our cache on certain events
$flush_cache_callable = array( 'CategoryPosts', 'flush_cache' );
add_action( 'save_post',                             $flush_cache_callable );
add_action( 'update_widget_category_posts',          $flush_cache_callable );
add_action( 'deleted_post',                          $flush_cache_callable );
add_action( 'wp_update_comment_count',               $flush_cache_callable );
