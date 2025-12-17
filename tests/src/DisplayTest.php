<?php
/**
 * Tests for Jeherve\Posts_On_This_Day\Display;
 *
 * @package jeherve/posts-on-this-day
 */

namespace Jeherve\Posts_On_This_Day;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class DisplayTest
 */
#[CoversClass( Display::class )]
class DisplayTest extends TestCase {
	/**
	 * Our Display class.
	 *
	 * @var Display
	 */
	protected Display $display;

	/**
	 * Set up Brain Monkey and Display before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->display = new Display();
	}

	/**
	 * Tear down Brain Monkey after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test the output of the display_post method with thumbnails.
	 */
	public function test_display_post_with_thumbnails(): void {
		$post_id    = 123;
		$post_title = 'My Post Title';
		$permalink  = 'https://example.com/my-post-title/';

		// Set up mocks.
		Functions\when( 'get_permalink' )->justReturn( $permalink );
		Functions\when( 'get_the_title' )->justReturn( $post_title );
		Functions\when( 'get_the_date' )->justReturn( '2020' );
		Functions\when( 'has_post_thumbnail' )->justReturn( true );
		Functions\when( 'get_the_post_thumbnail' )->justReturn( '<img src="test.jpg" class="posts_on_this_day__image" />' );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$widget_settings = array(
			'title'           => '',
			'max'             => 10,
			'back'            => 10,
			'show_thumbnails' => true,
			'group_by_year'   => false,
			'post_types'      => array( 'post' ),
		);

		$markup = $this->display->display_post( $post_id, $widget_settings );

		$this->assertStringContainsString( '<div class="posts_on_this_day__article">', $markup );
		$this->assertStringContainsString( '<a href="' . $permalink . '">', $markup );
		$this->assertStringContainsString( '<img', $markup );
	}

	/**
	 * Test the output of the display_post method without thumbnails.
	 */
	public function test_display_post_without_thumbnails(): void {
		$post_id    = 123;
		$post_title = 'My Post Title';
		$permalink  = 'https://example.com/my-post-title/';

		// Set up mocks.
		Functions\when( 'get_permalink' )->justReturn( $permalink );
		Functions\when( 'get_the_title' )->justReturn( $post_title );
		Functions\when( 'get_the_date' )->justReturn( '2020' );
		Functions\when( 'has_post_thumbnail' )->justReturn( false );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$widget_settings = array(
			'title'           => '',
			'max'             => 10,
			'back'            => 10,
			'show_thumbnails' => false,
			'group_by_year'   => false,
			'post_types'      => array( 'post' ),
		);

		$markup = $this->display->display_post( $post_id, $widget_settings );

		$this->assertStringContainsString( '<div class="posts_on_this_day__article">', $markup );
		$this->assertStringContainsString( '<div class="posts_on_this_day__title">', $markup );
		$this->assertStringNotContainsString( '<img', $markup );
	}

	/**
	 * Test the output of the display_post method grouped by year.
	 */
	public function test_display_post_grouped_by_year(): void {
		$post_id    = 123;
		$post_title = 'My Post Title';
		$permalink  = 'https://example.com/my-post-title/';

		// Set up mocks.
		Functions\when( 'get_permalink' )->justReturn( $permalink );
		Functions\when( 'get_the_title' )->justReturn( $post_title );
		Functions\when( 'has_post_thumbnail' )->justReturn( true );
		Functions\when( 'get_the_post_thumbnail' )->justReturn( '<img src="test.jpg" class="posts_on_this_day__image" />' );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$widget_settings = array(
			'title'           => '',
			'max'             => 10,
			'back'            => 10,
			'show_thumbnails' => true,
			'group_by_year'   => true,
			'post_types'      => array( 'post' ),
		);

		$markup = $this->display->display_post( $post_id, $widget_settings );

		// When grouped by year, the title should NOT include the year in parentheses.
		$this->assertStringContainsString( $post_title, $markup );
		$this->assertStringNotContainsString( '(2020)', $markup );
	}

	/**
	 * Test the output of the display_post method not grouped by year.
	 */
	public function test_display_post_not_grouped_by_year(): void {
		$post_id    = 123;
		$post_title = 'My Post Title';
		$permalink  = 'https://example.com/my-post-title/';

		// Set up mocks.
		Functions\when( 'get_permalink' )->justReturn( $permalink );
		Functions\when( 'get_the_title' )->justReturn( $post_title );
		Functions\when( 'get_the_date' )->justReturn( '2020' );
		Functions\when( 'has_post_thumbnail' )->justReturn( true );
		Functions\when( 'get_the_post_thumbnail' )->justReturn( '<img src="test.jpg" class="posts_on_this_day__image" />' );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'apply_filters' )->returnArg( 2 );

		// Mock sprintf-like behavior for __().
		Functions\when( '__' )->alias(
			function ( $text ) use ( $post_title ) {
				if ( '%1$s (%2$s)' === $text ) {
					return sprintf( $text, $post_title, '2020' );
				}
				return $text;
			}
		);

		$widget_settings = array(
			'title'           => '',
			'max'             => 10,
			'back'            => 10,
			'show_thumbnails' => true,
			'group_by_year'   => false,
			'post_types'      => array( 'post' ),
		);

		$markup = $this->display->display_post( $post_id, $widget_settings );

		// When not grouped by year, the title should include the year in parentheses.
		$this->assertStringContainsString( $post_title, $markup );
		$this->assertStringContainsString( '2020', $markup );
	}
}
