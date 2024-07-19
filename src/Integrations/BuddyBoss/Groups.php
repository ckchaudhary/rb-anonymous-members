<?php
/**
 * BuddyBoss groups integration.
 *
 * @package Anonymous Members
 * @subpackage BuddyBoss
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers\Integrations\BuddyBoss;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * BuddyBoss groups integration.
 *
 * @package Anonymous Members
 * @subpackage BuddyBoss
 * @author ckchaudhary
 */
class Groups extends \RecycleBin\AnonymousMembers\Integrations\BuddyPress\Groups {
	/**
	 * Constructor
	 *
	 * @param string $type type of the integration.
	 * @param string $name Name. Optional.
	 *
	 * @return void
	 */
	public function __construct( $type, $name = '' ) {
		parent::__construct( $type, $name );

		// @todo: disable 'like' feature for anonymous members.
	}
}
