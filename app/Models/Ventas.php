<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ventas extends Model
{
    protected $connection   = 'oracle';

    public const TABLE_NAME = 'VENTA';

    protected $table        = self::TABLE_NAME;

    protected $primaryKey   = 'num';

    const CREATED_AT        = 'insert_date';

    const UPDATED_AT        = 'update_date';

    protected $with = ['billing_info','detail','envio','delivery','memo'];

    public function billing_info()
    {
        return $this->hasOne(BillingInfo::class,'client_num','client_num');
    }

    public function detail()
    {
        return $this->hasMany(VentaDetail::class,'parent_num','num');
    }

    public function envio()
    {
        return $this->hasOne(VentaEnvio::class,'num','envio_info_num');
    }

    public function delivery()
    {
        return $this->hasOne(VentaDelivery::class,'venta_shop_num','num');
    }

    public function memo()
    {
        return $this->hasOne(VentaMemo::class,'parent_num','num');
    }
}
