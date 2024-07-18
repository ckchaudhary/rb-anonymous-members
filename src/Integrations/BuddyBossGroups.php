<?php
/**
 * BuddyPress groups integration.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers\Integrations;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * BuddyPress groups integration.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */
class BuddyBossGroups extends BuddyPressGroups {
	public function init() {
		parent::init();

		// @todo: disable 'like' feature for anonymous members.
		
	}
}
