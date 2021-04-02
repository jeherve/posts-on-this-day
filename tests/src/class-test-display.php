<?php
/**
 * Tests for Jeherve\Posts_On_This_Day\Display;
 *
 * @package jeherve/posts-on-this-day
 */

namespace Jeherve\Posts_On_This_Day;

use WorDBless\BaseTestCase;

/**
 * Class Test_Blocks
 */
class Test_Display extends BaseTestCase {
	use \Yoast\PHPUnitPolyfills\Polyfills\AssertStringContains;

	/**
	 * Our Display class.
	 *
	 * @var Display
	 */
	protected $display;

	/**
	 * Initialize Display before each test.
	 *
	 * @before
	 */
	public function set_up() {
		parent::setUp();

		$this->display = new Display();
	}

	/**
	 * Test the output of the display_post method.
	 *
	 * @covers Jeherve\Posts_On_This_Day\Display::display_post
	 * @dataProvider get_display_options
	 *
	 * @param int    $post_id         Test post id.
	 * @param array  $widget_settings Array of widget settings.
	 * @param string $output          Single post output.
	 */
	public function test_display_post( $post_id, $widget_settings, $output ) {

		$markup = $this->display->display_post( $post_id, $widget_settings );

		$this->assertStringContainsString( $output, $markup ); // Extra class remains.
	}

	/**
	 * Different widget configuration options.
	 *
	 * Data provider for test_display_post
	 *
	 * @return array
	 */
	public function get_display_options(): array {
		$post_data  = $this->set_up_data();
		$post_id    = $post_data['post_id'];
		$post_title = get_the_title( $post_id );

		return array(
			'With thumbnails'     => array(
				$post_id,
				array(
					'title'           => '',
					'max'             => 10,
					'back'            => 10,
					'show_thumbnails' => true,
					'group_by_year'   => false,
					'post_types'      => array( 'post' ),
				),
				sprintf(
					'<div class="posts_on_this_day__article"><a href="%1$s"><img',
					get_permalink( $post_id )
				),
			),
			'Without thumbnails'  => array(
				$post_id,
				array(
					'title'           => '',
					'max'             => 10,
					'back'            => 10,
					'show_thumbnails' => false,
					'group_by_year'   => false,
					'post_types'      => array( 'post' ),
				),
				'<div class="posts_on_this_day__article"><div class="posts_on_this_day__title">',
			),
			'Group by year'       => array(
				$post_id,
				array(
					'title'           => '',
					'max'             => 10,
					'back'            => 10,
					'show_thumbnails' => true,
					'group_by_year'   => true,
					'post_types'      => array( 'post' ),
				),
				$post_title,
			),
			'Not grouped by year' => array(
				$post_id,
				array(
					'title'           => '',
					'max'             => 10,
					'back'            => 10,
					'show_thumbnails' => true,
					'group_by_year'   => false,
					'post_types'      => array( 'post' ),
				),
				sprintf(
					'%1$s (%2$s)',
					$post_title,
					get_the_date( 'Y', $post_id )
				),
			),
		);
	}

	/**
	 * Create test post and attachment.
	 *
	 * @before
	 *
	 * @return array $post_data Array of the post ID and attachment id used in tests.
	 */
	public function set_up_data(): array {
		// Create test post.
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'My Post Title',
				'post_content' => 'Some content.',
				'post_status'  => 'publish',
				'post_date'    => '2020/03/29',
			)
		);

		/*
		 * Create test attachment,
		 * attach it to our post,
		 * and make it featured image.
		 */
		$filename = __DIR__ . '/../files/image.png';

		// Check the type of file. We'll use this as the 'post_mime_type'.
		$filetype = wp_check_filetype( basename( $filename ), null );

		// Get the path to the upload directory.
		$wp_upload_dir = wp_upload_dir();

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the attachment.
		$attachment_id = wp_insert_attachment( $attachment, $filename, $post_id );

		// Make it a featured image.
		set_post_thumbnail( $post_id, $attachment_id );

		return array(
			'post_id'       => $post_id,
			'attachment_id' => $attachment_id,
		);
	}
}
