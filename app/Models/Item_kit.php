<?php

namespace App\Models;

use CodeIgniter\Model;
use stdClass;

/**
 * Item_kit class
 *
 * @property appconfig appconfig
 */
class Item_kit extends Model
{
	/**
	 * Determines if a given item_id is an item kit
	 */
	public function exists(int $item_kit_id): bool
	{
		$builder = $this->db->table('item_kits');
		$builder->where('item_kit_id', $item_kit_id);

		return ($builder->get()->getNumRows() == 1);	//TODO: ===
	}

	/**
	 * Check if a given item_id is an item kit
	 */
	public function is_valid_item_kit(int $item_kit_id): bool
	{
		if(!empty($item_kit_id))
		{
			//KIT #
			$pieces = explode(' ', $item_kit_id);

			if((count($pieces) == 2) && preg_match('/(KIT)/i', $pieces[0]))	//TODO: === ... perhaps think about converting this to ternary notation
			{
				return $this->exists($pieces[1]);
			}
			else
			{
				return $this->item_number_exists($item_kit_id);
			}
		}

		return FALSE;
	}

	/**
	 * Determines if a given item_number exists
	 */
	public function item_number_exists(string $item_kit_number, string $item_kit_id = ''): bool
	{
		if($this->appconfig->get('allow_duplicate_barcodes') != FALSE)	//TODO: avoid double-negatives.
		{
			return FALSE;
		}

		$builder = $this->db->table('item_kits');
		$builder->where('item_kit_number', (string) $item_kit_number);

		// check if $item_id is a number and not a string starting with 0
		// because cases like 00012345 will be seen as a number where it is a barcode
		if(ctype_digit($item_kit_id) && substr($item_kit_id, 0, 1) !== '0')
		{
			$builder->where('item_kit_id !=', (int) $item_kit_id);
		}

		return ($builder->get()->getNumRows() >= 1);
	}

	/**
	 * Gets total of rows
	 */
	public function get_total_rows(): int
	{
		$builder = $this->db->table('item_kits');

		return $builder->countAllResults();
	}

	/**
	 * Gets information about a particular item kit
	 */
	public function get_info(int $item_kit_id): object
	{
		$builder = $this->db->table('item_kits');
		$builder->select('
			item_kit_id,
			item_kits.name as name,
			item_kit_number,
			items.name as item_name,
			item_kits.description,
			items.description as item_description,
			item_kits.item_id as kit_item_id,
			kit_discount,
			kit_discount_type,
			price_option,
			print_option,
			category,
			supplier_id,
			item_number,
			cost_price,
			unit_price,
			reorder_level,
			receiving_quantity,
			pic_filename,
			allow_alt_description,
			is_serialized,
			items.deleted,
			item_type,
			stock_type
		');

		$builder->join('items', 'item_kits.item_id = items.item_id', 'left');
		$builder->where('item_kit_id', $item_kit_id);
		$builder->orWhere('item_kit_number', $item_kit_id);

		$query = $builder->get();

		if($query->getNumRows() == 1)	//TODO: ===
		{
			return $query->getRow();
		}
		else
		{
			//Get empty base parent object, as $item_kit_id is NOT an item kit
			$item_obj = new stdClass();

			//Get all the fields from items table
			foreach($this->db->getFieldNames('item_kits') as $field)
			{
				$item_obj->$field = '';
			}

			return $item_obj;
		}
	}

	/**
	 * Gets information about multiple item kits
	 */
	public function get_multiple_info(array $item_kit_ids)
	{
		$builder = $this->db->table('item_kits');
		$builder->whereIn('item_kit_id', $item_kit_ids);
		$builder->orderBy('name', 'asc');

		return $builder->get();
	}

	/**
	 * Inserts or updates an item kit
	 */
	public function save(array &$item_kit_data, bool $item_kit_id = FALSE): bool	//TODO: incompatible with base save()
	{
		$builder = $this->db->table('item_kits');
		if(!$item_kit_id || !$this->exists($item_kit_id))
		{
			if($builder->insert($item_kit_data))
			{
				$item_kit_data['item_kit_id'] = $this->db->insertID();

				return TRUE;
			}

			return FALSE;
		}

		$builder->where('item_kit_id', $item_kit_id);

		return $builder->update($item_kit_data);
	}

	/**
	 * Deletes one item kit
	 */
	public function delete(int $item_kit_id = null, bool $purge = false): bool
	{
		$builder = $this->db->table('item_kits');

		return $builder->delete(['item_kit_id' => $item_kit_id]);
	}

	/**
	 * Deletes a list of item kits
	 */
	public function delete_list(array $item_kit_ids): bool
	{
		$builder = $this->db->table('item_kits');
		$builder->whereIn('item_kit_id', $item_kit_ids);

		return $builder->delete();
	}

	public function get_search_suggestions(string $search, int $limit = 25): array
	{
		$suggestions = [];

		$builder = $this->db->table('item_kits');

		//KIT #
		if(stripos($search, 'KIT ') !== FALSE)
		{
			$builder->like('item_kit_id', str_ireplace('KIT ', '', $search));
			$builder->orderBy('item_kit_id', 'asc');

			foreach($builder->get()->getResult() as $row)
			{
				$suggestions[] = ['value' => 'KIT '. $row->item_kit_id, 'label' => 'KIT ' . $row->item_kit_id];
			}
		}
		else
		{
			$builder->like('name', $search);
			$builder->orLike('item_kit_number', $search);
			$builder->orderBy('name', 'asc');

			foreach($builder->get()->getResult() as $row)
			{
				$suggestions[] = ['value' => 'KIT ' . $row->item_kit_id, 'label' => $row->name];
			}
		}

		//only return $limit suggestions
		if(count($suggestions) > $limit)
		{
			$suggestions = array_slice($suggestions, 0, $limit);
		}

		return $suggestions;
	}

 	/**
	 * Gets rows
	 */
	public function get_found_rows(string $search)
	{
		return $this->search($search, 0, 0, 'name', 'asc', TRUE);
	}

	/**
	 * Perform a search on items
	 */
	public function search(string $search, int $rows = 0, int $limit_from = 0, string $sort = 'name', string $order = 'asc', bool $count_only = FALSE)
	{
		$builder = $this->db->table('item_kits AS item_kits');	//TODO: Can we just say 'item_kits' here?

		// get_found_rows case
		if($count_only == TRUE)	//TODO: replace this with `if($count_only)`
		{
			$builder->select('COUNT(item_kits.item_kit_id) as count');
		}

		$builder->like('name', $search);
		$builder->orLike('description', $search);
		$builder->orLike('item_kit_number', $search);

		//KIT #
		if(stripos($search, 'KIT ') !== FALSE)
		{
			$builder->orLike('item_kit_id', str_ireplace('KIT ', '', $search));
		}

		// get_found_rows case
		if($count_only == TRUE)	//TODO: replace this with `if($count_only)`
		{
			return $builder->get()->getRow()->count;
		}

		$builder->orderBy($sort, $order);

		if($rows > 0)
		{
			$builder->limit($rows, $limit_from);
		}

		return $builder->get();
	}
}
?>
