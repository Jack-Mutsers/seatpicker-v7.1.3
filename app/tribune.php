<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class tribune extends Model
{
    protected $table = 'tribune';
    
    protected $fillable = [
        'visible',
        'active',
        'tribune',
        'name',
        'creation_date'
    ];

	protected $primaryKey = 'id';
	public $timestamps = false;
}

/* 	
	function find($id){
		// get specific tribune by id
        $this->db->where($this->tablename . ".id", $id);
		$query = $this->db->get($this->tablename);

		foreach ($query->result() as $row)
		{
			return $row->tribune;
		}
	}
	
	function gettribunes(){
		// get all tribunes for a selector
		$this->db->distinct();
        $this->db->select($this->tablename . ".name");
        $this->db->select($this->tablename . ".id");
        $this->db->where($this->tablename . ".visible", 1);
        $this->db->where("(" . $this->tablename . ".`active-nl` = 1 OR " . $this->tablename . ".`active-en` = 1)");
		$query = $this->db->get($this->tablename);

		$result = array();
		foreach ($query->result() as $row)
		{
			array_push($result, $row);
		}

		return $result;
	}
*/