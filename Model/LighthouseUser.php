<?php
App::uses('LighthouseAppModel', 'Lighthouse.Model');

/**
 * Description of LighthouseUser
 *
 * @author amoreno
 */
class LighthouseUser extends LighthouseAppModel {
	public $useTable = 'users';
	public $useDbConfig  = 'lighthouse';
}
