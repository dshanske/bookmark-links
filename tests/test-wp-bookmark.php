<?php

require_once( 'class-blinks-unittest-factory-for-bookmark.php' );

class WPBookmarkTest extends WP_UnitTestCase {

	protected static $bookmark_factory;

	public function set_up() {
		blinks_create_tables();
		self::$bookmark_factory = new Blinks_UnitTest_Factory_For_Bookmark();
		parent::set_up();
	}

	public function test_set_and_get_wp_bookmark() {
		$link_id = wp_insert_link(
				array(
					'link_url' => 'https://example.org',
					'link_name' => 'Test'
				),
				true
			);
		add_link_meta( $link_id, 'test', 'testdata' );
		$bookmark = get_bookmark( $link_id );
		$bookmark_object = blinks_get_bookmark( $link_id );

		$this->assertEquals( $bookmark_object, new WP_Bookmark( $bookmark ) );

		// Test magic getter
		$this->assertEquals( 'testdata', $bookmark_object->test );
		
		wp_delete_link( $link_id );
	}

	public function test_factory() {
		$bookmark = self::$bookmark_factory->create_and_get();
		$bookmark_object = blinks_get_bookmark( $bookmark );
		$this->assertNotFalse( $bookmark_object );
	}
}

