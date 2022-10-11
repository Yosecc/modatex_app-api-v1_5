<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaMemo extends Model
{
    protected $connection   = 'oracle';

    public const TABLE_NAME = 'VENTA_MEMO';

    protected $table        = self::TABLE_NAME;

    protected $primaryKey   = 'num';

    const CREATED_AT        = 'insert_date';

    const UPDATED_AT        = 'update_date';
}
