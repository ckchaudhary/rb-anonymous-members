<?php
/**
 * Reusable singleton trait
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers;

if ( ! trait_exists( '\RecycleBin\AnonymousMembers\TraitSingleton' ) ) {

	/**
	 * Singleton base class for having singleton implementation.
	 * This allows you to have only one instance of the needed object
	 * You can get the instance with
	 *     $class = My_Class::get_instance();
	 *
	 * /!\ The get_instance method have to be implemented !
	 *
	 * @package Anonymous Members
	 * @author ckchaudhary
	 */
	trait TraitSingleton {

		/**
		 * The singleton object.
		 *
		 * @var static
		 */
		protected static $instance;

		/**
		 * Get the single instance of this class.
		 *
		 * @return static
		 */
		final public static function get_instance() {
			if ( is_null( static::$instance ) ) {
				static::$instance = new static();
			}

			return static::$instance;
		}

		/**
		 * Constructor protected from the outside.
		 *
		 * @return void
		 */
		private function __construct() {
			$this->init();
		}

		/**
		 * Add init function by default
		 * Implement this method in your child class
		 * If you want to have actions send at construct
		 */
		protected function init() {}

		/**
		 * Prevent the instance from being cloned.
		 *
		 * @return void
		 */
		final public function __clone() {
		}

		/**
		 * Prevent from being unserialized
		 *
		 * @return void
		 */
		final public function __wakeup() {
		}
	}
}
