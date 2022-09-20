<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupons extends Model
{
    protected $connection   = 'oracle';
    protected $table        = 'COUPON';

    public $sequence        = 'S_SHOP_COUPON_NUM';

    protected $primaryKey   = 'num';

     public $timestamps = false;


    protected $fillable     = ['coupon_str','coupon_price','client_num','stat_cd','register_date','expire_date'];
    

}
