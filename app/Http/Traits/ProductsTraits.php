<?php

namespace App\Http\Traits;

use Illuminate\Support\Arr;
use App\Models\Products;
use App\Models\Store;
use Illuminate\Http\Request;


use App\Http\Traits\HelpersTraits;

trait ProductsTraits {

	use HelpersTraits;

  public $subcategorias;

  // public function __construct(){

  //   $this->subcategorias = [];
  // }
	 /*
    * Prepare dataa
    * @params Store Object Query 
    * @return Collect
    */
   // Función de comparación
   public function cmp($a, $b) {
      if ($a == $b) {
          return 0;
      }
      return ($a < $b) ? -1 : 1;
  }

    public function productCollect($product, $params = null, $is_store = true){

      if (isset($params['detalle_id'])) {
        $detalle = $product->detalle()->where('NUM',$params['detalle_id'])->get();
      }else{
        $detalle = $product->detalle;
      }

      // dd($product);
      $product = collect([
        'images'        => $this->dataImagesArray($product->Images),
        'GROUP_CD'      => $product->GROUP_CD,
        'LOCAL_CD'      => $product->LOCAL_CD,
        'precio'        => $product->Price->VENTA_PRECIO ?? rand(1000,10000),
        'id'            => $product->NUM,
        'moda_num'      => $product->MODA_NUM,
        'codigo_modelo' => $product->CODIGO_MODELO,
        'descripcion'   => $product->DESCRIPCION,
        'categoria'     => $this->dataTipoCollection($product->TipoModeloUno),
        'subcategoria'  => $this->dataTipoCollection($product->TipoModeloDos),
        'tipo_tela'     => $this->dataCodeCollection($product->Tela),
        'estampado'     => $this->dataCodeCollection($product->Estampado),
        'detail'        => $this->dataDetailCollection($detalle),
        'is_favorite'   => $product->isFavorite ? true:false
      ]);

      // $store = new Store();
      // $store = $store->searchStore(['GROUP_CD'=> $product['GROUP_CD'], 'LOCAL_CD'=> $product['LOCAL_CD']], $params);
      // dd($store['id']);

      // $product->merge(['store'=> $store]);

      if (!$is_store) {
        $params['is_store'] = false;
      }

      if ((isset($params['is_store']) && $params['is_store'] == true)) {
        $store = new Store();
        $store = $store->searchStore(['GROUP_CD'=> $product['GROUP_CD'], 'LOCAL_CD'=> $product['LOCAL_CD']], $params);

        $product = $product->merge(['store'=> $store]);
      }

      return $product;
    }

    public function dataSubcategoriasCollection($products){
      $subcategories = [];

      foreach ($products as $key => $product) {
        if ($product->TipoModeloDos) {
          $sub = $product->TipoModeloDos;
          $data = ['id'=> $sub->NUM, 'name'=>$sub->TIPO_NAME, 'order'=> $sub->ORDER_NUM, 'PARENT_NUM'=> $sub->PARENT_NUM];
          if (!in_array( $data, $subcategories)) {
            $subcategories[] = $data;
          }
        }
      }

      return collect($subcategories);
    }

    /*
    * Prepare dataa
    * @params Store Object Query 
    * @return Array
    */
    public function productsCollection($products, $params = null, $is_store = true){
      // dd($products);
      $func = function($product) use ($params,$is_store){
        $product = $this->productCollect($product, $params, $is_store);
       
        return $product;
      };
      $d = array_map($func, $products->all());

      return $d;
      
    }

    public function dataDetailCollection($detalles){

      $detalles = $this->detallesArray($detalles);

      $tallas = [];
      foreach ($detalles as $key => $detalle) {
          if ($detalle['stock'] > 0) {
            $tallas[$detalle['size']['name']]['colors'][] =  [
                'id'    => $detalle['id'],
                'name'  => $detalle['color']['name']?? null, 
                'code'  => $detalle['color']['code']??null,
                'stock' => $detalle['stock']
              ];
          }
      }

      return collect($tallas);

    }

    public function detalleCollection($detalle){
      // dd($detalle);
      $detalle = collect([
        'id'              => $detalle->NUM,
        'moda_num'        => $detalle->MODA_NUM,
        'product_id'      => $detalle->PARENT_NUM,
        'size'            => $this->dataCodeCollection($detalle->Size),
        'color'           => $this->dataCodeCollection($detalle->Color),
        'COLOR_ATACH_NUM' => $detalle->COLOR_ATACH_NUM,
        'stock'           => $detalle->QUANTITY,
        'ancho'           => $detalle->ANCHO,
        'largo'           => $detalle->LARGO,
        'price'           => $this->dataPriceCollection($detalle->Price)
      ]);

      return $detalle;

    }

    public function dataPriceCollection($price){
      if($price)
        return collect(intval($price->PRECIO))[0];
    }

    public function dataCodeCollection($code){
      if ($code) {
        // dd($code);
        $code = collect([
          'name'=> $code->CODE_NAME,
          'code'=> $code->REFERENCE1 != '-' ? $code->REFERENCE1 : null
        ]);
        // dd($code);
        return $code;
      }
      return [];
        
    }

    public function dataTipoCollection($tipo){
      if ($tipo) {
        return collect([
          'id'         => $tipo->NUM,
          'name'       => $tipo->TIPO_NAME,
          'description'=>$tipo->DESCRIPTION
        ]);
      }
      return collect([]);
    }

    public function detallesArray($detalles){

      $func = function($detalle){
        $detalle = $this->detalleCollection($detalle);
        return $detalle;
      };
      return array_map($func, $detalles->all());

    }

    public function dataImage($image){
      return env('URL_IMAGE').$image->FILE_PATH.'/'.$image->NEW_FILE_NAME;
    }

    public function dataImagesArray($images){
      
      $func = function($image){
        $image = $this->dataImage($image);
        return $image;
      };
      return array_map($func, $images->all());
    }
	
    public function dataRelacionados($product, $params){
      if (empty($params['product_related_same_store'])) {
        $params['product_related_same_store'] = true;
      }

      if (filter_var($params['product_related_same_store'], FILTER_VALIDATE_BOOLEAN)) {
        $products = Products::productsStoreNoDetalle(['local_cd'=>$product->GROUP_CD,
                                'group_cd'=>$product->LOCAL_CD])
                                ->Category($product->TIPO_MODELO_NUM1)
                                ->SubCategory($product->TIPO_MODELO_NUM2)
                                ->whereNotIn('NUM',[$product->NUM])
                                ->limit(3)
                                ->get();
        return $this->productsCollection($products);
      }
    }
}