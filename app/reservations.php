<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class reservations extends Model
{
    protected $table = 'reservations';
    
    protected $fillable = [
        'seat_name',
        'row',
        'colomn',
        'tribune_id',
        'customer_id',
        'order_date',
        'pre_reserve'
    ];

	protected $primaryKey = 'id';
	public $timestamps = false;
}

