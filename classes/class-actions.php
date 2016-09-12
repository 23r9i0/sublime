<?php
/**
 * Export Actions
 *
 * @package sublime
 * @subpackage export
 */
namespace Sublime {

	use \Sublime\Filters;

	class Actions extends Filters {

		// public $name = 'Actions';

		public $type = 'action';

		public function __construct( $directory = '' ) {
			parent::__construct( $directory );
		}
	}

}
