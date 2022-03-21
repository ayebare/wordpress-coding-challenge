<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );

		foreach ( [ 'post', 'page' ] as $post_type ) {
			add_action( "save_post_{$post_type}", [ $this, 'refresh_tag_foo_cat_baz_posts' ], 10, 2 );
		}
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {
		$post_types      = get_post_types( [ 'public' => true ], 'objects' );
		$class_name      = isset( $attributes['className'] ) ? $attributes['className'] : '';
		$current_post_id = ! empty ( $block->context['postId'] ) && absint( $block->context['postId'] ) ? $block->context['postId'] : get_post()->ID;

		ob_start();

		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2>Post Counts</h2>
			<ul>
				<?php
				foreach ( $post_types as $post_type_slug => $post_type_object ) :
					$post_count_object = wp_count_posts( $post_type_slug );
					$post_count = isset( $post_count_object->publish ) ? $post_count_object->publish : 'Null';

					?>
					<li><?php echo 'There are ' . $post_count . ' ' .
					               $post_type_object->labels->name . '.'; ?></li>
				<?php endforeach; ?>
			</ul>

			<p><?php echo 'The current post ID is ' . esc_html( $current_post_id ) . '.'; ?></p>

			<?php
			$posts = $this->get_tag_foo_cat_baz_posts( 6 ); // Fetch 6 post titles in case one of them belongs to the current post id and it gets filtered out.

			// If the current post ID is part of the cached posts, remove it.
			if ( isset ( $posts[ $current_post_id ] ) ) {
				unset( $posts[ $current_post_id ] );
			}

			if ( ! empty( $posts ) ) :
				?>
				<h2>5 posts with the tag of foo and the category of baz</h2>
				<ul>
					<?php
					$post_count = 0; // count the posts displayed, up to 5

					foreach ( $posts as $post_title ) :
						if ( $post_count === 5 ) {
							break;
						}

						?>
						<li><?php echo esc_html( $post_title ); ?></li>
						<?php

						$post_count ++;
					endforeach;

					?>
				</ul>
			<?php endif;
			?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Retrieve 5 post titles with foo tag and baz category
	 *
	 * @param bool $force_refresh Whether to force the cache to be refreshed. Default false.
	 *
	 * @return array Array of post titles matched to ids of posts with foo tag and baz category
	 */
	public function get_tag_foo_cat_baz_posts( $post_count, $force_refresh = false ) {
		// Check for xwp_tag_foo_cat_baz_posts key in the 'block_posts' group.
		$posts = wp_cache_get( 'xwp_tag_foo_cat_baz_posts', 'block_posts' );

		// If nothing is found, build the object.
		if ( true === $force_refresh || false === $posts ) {
			$posts = [];

			$posts_query = new WP_Query( array(
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'any',
				'no_found_rows'  => true,
				'posts_per_page' => $post_count,
				'date_query'     => array(
					array(
						'hour'    => 9,
						'compare' => '>=',
					),
					array(
						'hour'    => 17,
						'compare' => '<=',
					),
				),
				'tag'            => 'foo',
				'category_name'  => 'baz',
			) );

			if ( ! is_wp_error( $posts_query ) && $posts_query->have_posts() ) {
				while ( $posts_query->have_posts() ) :
					$posts_query->the_post();
					$posts[ get_the_ID() ] = get_the_title();
				endwhile;
				wp_reset_postdata();

				// Keep array of ID->post_title in cache
				wp_cache_set( 'xwp_tag_foo_cat_baz_posts', $posts, 'block_posts' );
			}
		}

		return $posts;
	}

	/**
	 * Prime the cache for 5 post titles with foo tag and baz category
	 *
	 * If the post is not of type post or page don't update the cache.
	 * If the post wasn't published between the 9th and 17th hour, don't update the cache.
	 *
	 * @param int $post_ID Post ID.
	 * @param WP_Post $post The post Object
	 */
	public function refresh_tag_foo_cat_baz_posts( $post_ID, $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		$post_publish_time = get_post_time( 'U', false, $post );
		$post_publish_hour = date( 'H', $post_publish_time );

		if ( $post_publish_hour < 9 || $post_publish_hour > 17 ) {
			return;
		}

		// Force the cache refresh for 5 post titles with foo tag and baz category.
		$this->get_tag_foo_cat_baz_posts( 6 );
	}
}
