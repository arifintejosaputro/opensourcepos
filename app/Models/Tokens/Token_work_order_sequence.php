<?php

namespace App\Models\Tokens;

use app\Models\Appconfig;

/**
 * Token_work_order_sequence class
 *
 * @property appconfig appconfig
 *
 */
class Token_work_order_sequence extends Token
{
	public function __construct($value = '')
	{
		parent::__construct($value);
	}

	public function token_id(): string
	{
		return 'WSEQ';
	}

	public function get_value(): string
	{
		return $this->appconfig->acquire_save_next_work_order_sequence();
	}
}
?>
