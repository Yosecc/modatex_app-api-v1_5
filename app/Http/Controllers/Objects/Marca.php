<?php

namespace App\Http\Controllers\Objects;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Marca extends Controller
{
    private $data = [
        "logo"             => '',
        "name"             => '',
        "id"               => '',
        "local_cd"         => '',
        "company_id"       => '',
        "company"          => '',
        "min"              => '',
        "rep"              => '',
        "vc"               => '',
        "categorie"        => '',
        "category_default" => '',
        'categories_store' => '',
        'paquete'          => '',
        'cleaned'          => '',
        'favorite'         => false ,
        'status'           => '1000'
    ];

    private  $CATEGORIES = [
        ['key'=> 'USE_WOMAN'     , 'id' => 1, 'name' => 'woman'],
        ['key'=> 'USE_ACCESORY'  , 'id' => 2, 'name' => 'accessories'],
        ['key'=> 'USE_MAN'       , 'id' => 3, 'name' => 'man'],
        ['key'=> 'USE_CHILD'     , 'id' => 4, 'name' => 'kids'],
        ['key'=> 'USE_SPECIAL'   , 'id' => 6, 'name' => 'xl'],
        ['key'=> 'USE_DEPORTIVA' , 'id' => 7, 'name' => 'sportive'],
        ['key'=> 'USE_LENCERIA'  , 'id' => 10, 'name' => 'lingerie'],
        ['key'=> 'USE_SHOES'     , 'id' => 24, 'name' => 'shoes'],
        ['key'=> 'USE_HOME'      , 'id' => 17, 'name' => 'home'],
    ];
 
    public function __construct($store)
    {
        $this->crearMarca($store);
    }

    private function getLogo($logo){
        return env('URL_IMAGE').'/common/img/logo/'. $logo;
    }

    // private function categorieDefaultId($store)
    // {
    //   /**
    //      * Si PREDEF_SECTION != '' ??  Es es el ID por defecto. 
    //      * Si PREDEF_SECTION == '' ??  Si subcaetgorias > 1 ? La prioridad es woman|talle especial|men|nino|accesorios : Es la posicion 0  
    //      */

    //   $predefSection = 0;

    //   if( $store && $store['PREDEF_SECTION'] != '')
    //   {
    //     $predefSection = intval($store['PREDEF_SECTION']);
    //   }
    //   elseif( $store && $store['PREDEF_SECTION'] == '')
    //   {
    //     $categorias = $this->categoriesCollection($store, null);
       
    //       if($categorias->count() > 1){
    //         $predefSection = $categorias->where('descripcion' , 'mujer')->first();
    //         if($predefSection){
    //           $predefSection = $predefSection['id'];
    //         }else{
    //           $predefSection = $categorias->first();
    //           if($predefSection){
    //             $predefSection = $predefSection['id'];
    //           }
    //         }
    //       }else{
    //         $predefSection = $categorias->first();
    //         if($predefSection){
    //           $predefSection = $predefSection['id'];
    //         }
    //       }
    //   }
    //   return $predefSection;
    // }

    private function getCategoria($store)
    {
       
        $categorie = '';
        if(isset($store['more']['USE_MAN']) && $store['more']['USE_MAN'] == "Y"){
          $categorie = 'man';
        }elseif(isset($store['more']['USE_WOMAN']) && $store['more']['USE_WOMAN'] == "Y"){
          $categorie = 'woman';
        }elseif(isset($store['more']['USE_CHILD']) && $store['more']['USE_CHILD'] == "Y"){
          $categorie = 'kids';
        }elseif(isset($store['more']['USE_ACCESORY']) && $store['more']['USE_ACCESORY'] == "Y"){
          $categorie = 'accessories';
        }elseif(isset($store['more']['USE_SPECIAL']) && $store['more']['USE_SPECIAL'] == "Y"){
          $categorie = 'xl';
        }elseif(isset($store['more']['USE_DEPORTIVA']) && $store['more']['USE_DEPORTIVA'] == "Y"){
          $categorie = 'sportive';
        }elseif(isset($store['more']['USE_LENCERIA']) && $store['more']['USE_LENCERIA'] == "Y"){
          $categorie = 'lingerie';
        }elseif(isset($store['more']['USE_SHOES']) && $store['more']['USE_SHOES'] == "Y"){
          $categorie = 'shoes';
        }elseif(isset($store['more']['USE_HOME']) && $store['more']['USE_HOME'] == "Y"){
          $categorie = 'home';
        }
    
        $categoriaCalc = collect($this->CATEGORIES)->where('name',  $categorie)->first();
    
        $categorieR = [];
        $paquete = '';
        if(isset($store['sections'])){
          foreach (json_decode($store['sections']) as $key => $value) {
            $categorieR[] = $key;
            $paquete = $value;
          }
        }

        return [
            'categories_store' => $categorieR,
            'paquete' => $paquete,
            'category_default' => isset($categoriaCalc['id']) ? $categoriaCalc['id'] : '',
            'categorie' => $categorie
        ];
    }

    private function crearMarca($store)
    {
      try {
        //code...
      

        $this->data['logo']         = $this->getLogo($store['logo']);

        $this->data['name']         = $store['name'];
        $this->data['id']           = $store['id'];
        $this->data['local_cd']     = $store['more']['LOCAL_CD'];
        $this->data['company_id']   = $store['more']['GROUP_CD'];
        $this->data['company']      = $store['more']['GROUP_CD'];
        $this->data['min']          = $store['more']['minimum'];
        $this->data['rep']          = $store['more']['stats']['points'];
        $this->data['vc']           = $store['more']['stats']['completed_sales_perc'];
        
        $categoria = $this->getCategoria($store);
        
        $this->data['categorie']        = $categoria['categorie'];
        $this->data['category_default'] = $categoria['category_default'];
        $this->data['categories_store'] = $categoria['categories_store'] ;
        $this->data['paquete']          = $categoria['paquete'];

        $this->data['cleaned'] = $store['cleaned'];
        $this->data['status'] = $store['status'];
        
        // $this->data['favorite'] = $store[''];
      } catch (\Throwable $th) {
        \Log::info($th->getMessage());
      }
    }

    public function getMarca()
    {
        return $this->data;
    }
}
