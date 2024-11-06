<?php

namespace App\Http\Controllers\Objects;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class Producto extends Controller
{
    private $storesCache;
    private $urlImage = 'https://netivooregon.s3.amazonaws.com/';
    private $data = [
        "id"          => 0,
        "code"        => '',
        "local_cd"    => '',
        "company"     => '',
        "name"        => '',
        "category"    => '',
        "category_id" => '',
        "price"       => '',
        "prev_price"  => '',
        "images"      => [],
        "sizes"       => [],
        "colors"      => [],
        "is_desc"     => '',
        "isCart"      => false,
        "has_stock"   => true,
        "models"      => [],
        "store"       => [],
        "is_promo_2x1"=> false,
    ];

    public function __construct($producto)
    {
        $this->storesCache = Cache::get('stores');
        $this->crearProducto($producto);
    }

    private function crearProducto($producto)
    {
        $this->data['id']           = $producto['id'];
        $this->data['code']         = $producto['code'];
        $this->data['local_cd']     = $producto['store'];
        $this->data['company']      = $producto['company'];
        
        $buscar = "2x1";
        $name = $producto['name'];
        if(strpos($producto['name'], $buscar) !== false){
            $this->data['is_promo_2x1'] = true;
            // $name = str_replace($buscar, '', $producto['name']);
        }
        $this->data['name']         = $name;
        
        $this->data['category']     = isset($producto['category']) ? $producto['category'] : '';
        $this->data['category_id']  = $producto['category_id'];
        $this->data['price']        = $producto['price'];
        $this->data['prev_price']   = $producto['prev_price'];
        $this->data['images']       = collect($producto['images'])->map(fn ($image) => $this->urlImage.$image['lg'] );
        $this->data['sizes']        = $producto['sizes'];
        $this->data['colors']       = isset($producto['colors']) ? $producto['colors']: null;
        $this->data['is_desc']      = $producto['discount'];
        $this->data['has_stock']    = $producto['has_stock'];
        $this->data['store']        = $this->storesCache->where('id', $producto['store'] )->first();
    }

    public function setModelos($producto)
    {
        $this->data['models'] = $producto['models'];
        $this->data['isCart']  = collect($this->data['models'])->map(fn($model) => $model['added'])->collapse()->count() ? true:false;
    }

    public function getProducto()
    {
        return $this->data;
    }
}
