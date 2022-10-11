<?php

namespace App\Http\Traits;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Products;

trait CartTraits {
	
  public function dataCartsColelction($carts)
  {
    $stores = [];
    $grupos = [];

    foreach ($carts as $key => $cart) {
        $d = [  'GROUP_CD' => $cart->GROUP_CD,
                'LOCAL_CD' => $cart->LOCAL_CD,
             ];
        if (!in_array($d, $grupos)) {
            $grupos[] = $d;
        }
    }

    foreach ($grupos as $key => $grupo) {
      $cartss = [];

      foreach ($carts as $c => $cart) {
        if ($grupo['GROUP_CD'] == $cart['GROUP_CD'] && $grupo['LOCAL_CD'] == $cart['LOCAL_CD']) {
          $cartss[] = $this->dataCartCollection($cart);
        }
      }

      $stores[] = [
        'store' => Store::select('GROUP_CD','LOCAL_CD','NUM','LOCAL_NAME')->GROUP_CD($grupo['GROUP_CD'])->LOCAL_CD($grupo['LOCAL_CD'])->first(),
        'products' => $cartss
      ];

    }

    return $stores;
  }

  public function dataCartCollection($cart)
  {

    $product = new Products(['product_id'=> $cart['MODELO_NUM']]);
    $product = $product->getProduct(['detalle_id' => $cart['MODELO_DETALE_NUM']]);
    // dd($product);
    // $detalle = $product->detalle()->where('NUM',$cart['MODELO_DETALE_NUM'])->first();
    // dd($cart);
    $cart = collect([
      'id'        => $cart['NUM'],
      'client_id' => $cart['CLIENT_NUM'],
      'product'   => $product ,
      // 'detalle'   => $detalle,
    ]); 
    // dd($cart);
    return $cart;
  }
	
}