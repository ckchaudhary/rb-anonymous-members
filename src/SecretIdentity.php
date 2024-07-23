<?php
/**
 * The fetch and generate secret identities when required.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * The fetch and generate secret identities when required.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */
final class SecretIdentity {
	use TraitSingleton;

	/**
	 * Name of the meta key which stores a member's anonymous alias.
	 *
	 * @var string
	 */
	private $meta_key_alias = '_rb_an_m_alias';

	/**
	 * Get anonymous alias details for the given user id.
	 *
	 * @param int $user_id Id of the user.
	 *
	 * @return array {
	 *   @type int    $id     User id.
	 *   @type string $name   Alias for this user.
	 *   @type string $url    Url of anonymous user profile.
	 *   @type string $avatar Url of the avatar image.
	 * }
	 */
	public function get( $user_id = 0 ) {
		$retval = array(
			'id'     => $user_id,
			'url'    => '#',
			'name'   => 'Phantom',
			'avatar' => RB_AM_P_URL . 'assets/mystery-man.png',
		);

		if ( ! $user_id ) {
			return $retval;
		}

		$alias = \bp_get_user_meta( $user_id, $this->meta_key_alias, true );
		if ( empty( $alias ) ) {
			$alias = $this->generate_alias();
			\bp_update_user_meta( $user_id, $this->meta_key_alias, $alias );
		}

		$retval['name'] = $this->name_prefix() . $alias;
		return $retval;
	}

	/**
	 * Generate an alias.
	 * Uses current unix timestamp, and just inserts a digit at random position.
	 * This doesn't guarantee a unique string.
	 * However it introduces enough entropy to prevent guessing actual user by looking at an alias.
	 *
	 * @return string
	 */
	private function generate_alias() {
		$string        = time();
		$pos_partition = wp_rand( 0, strlen( $string ) - 1 );
		$random_digit  = wp_rand( 0, 9 );
		return substr( $string, 0, $pos_partition ) . $random_digit . substr( $string, $pos_partition );
	}

	/**
	 * Get the prefix for anonymous names( aliases ).
	 *
	 * @todo: add a setting for this.
	 *
	 * @return string
	 */
	public function name_prefix() {
		return 'Boo-';
	}
}
