<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingInfo extends Model
{
    protected $connection   = 'oracle';

    public const TABLE_NAME = 'T_SHOP_VENTA_BILLING_INFO';

    protected $table        = self::TABLE_NAME;

    protected $primaryKey   = 'num';

    const CREATED_AT        = 'insert_date';

    const UPDATED_AT        = 'update_date';

}
