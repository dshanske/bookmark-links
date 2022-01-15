<?php

class LinkMetaTest extends WP_UnitTestCase {
	public function set_up() {
		blinks_create_tables();
		parent::set_up();
	}
	public function test_set_and_get_link_meta() {
		$link_id = wp_insert_link(
				array(
					'link_url' => 'https://example.org',
					'link_name' => 'Test'
				),
				true
			);
		$return = add_link_meta( $link_id, 'test', 'Test Data' );
		$this->assertNotFalse( $return, $return );
		$this->assertEquals( 'Test Data', get_link_meta( $link_id, 'test', true ) );
		$this->assertTrue( delete_link_meta( $link_id, 'test' ) );
		wp_delete_link( $link_id );
	}
}

