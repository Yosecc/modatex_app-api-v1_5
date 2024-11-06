<?php

namespace App\Http\Controllers\Objects;

use App\Models\Favorite;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;

class Marca extends Controller
{
    private $data = [
        "logo"             => '', #String
        "name"             => '', #String
        "id"               => '', #Int
        "local_cd"         => '', #String
        "company_id"       => '', #String
        "company"          => '', #String
        "min"              => '', #Int
        "rep"              => '', #Int
        "vc"               => '', #Int
        "categorie"        => '', #String
        "category_default" => '', #Int
        'categories_store' => '', #Array
        'paquete'          => '', #String
        'cleaned'          => '', #String
        'favorite'         => false , #bool
        'status'           => '1000', #String
        'max_discount'     => 0, #Int
        'portada'          => '' #opcional String
    ];

    private $allowebCategories = ['woman','man','xl','kids','accessories','sportive','lingerie','home','shoes'];

    static public $allowedPlanes = ['premium', 'black','platinum','gold','blue'];

 
    public function __construct($store)
    {
        $this->crearMarca($store);
    }

    private function getLogo($logo){
        return env('URL_IMAGE').'/common/img/logo/'. $logo;
    }

    private function sortCategories(array $categoryOptions)
    {
        usort($categoryOptions, function ($a, $b) {
            $orderA = array_search($a, $this->allowebCategories);
            $orderB = array_search($b, $this->allowebCategories);
            return $orderA <=> $orderB;
        });

        return $categoryOptions;
    }

    private function getCategoria($store)
    {
       
        $categorieR = [];
        $paquete = '';
        if(isset($store['sections'])){
          foreach (json_decode($store['sections']) as $key => $value) {
            $categorieR[] = $key;
            $paquete = $value;
          }
        }

        $categorieR = $this->sortCategories($categorieR);

        $d = new HomeController();
        $categoriesBase = $d->getCategories();

        $c = count($categorieR) ? $categorieR[0] : '';

        $predefSection = collect($categoriesBase)->where('key', $c)->first();
        
        if($predefSection){
          $predefSection =  $predefSection['id'];
        }else{
          $predefSection = 0;
        }

        return [
            'categories_store' => $categorieR,
            'paquete' => $paquete,
            'category_default' => $predefSection,
            'categorie' => $c
        ];
    }

    private function crearMarca($store)
    {
      try {

        $this->data['logo']         = $this->getLogo($store['logo']);

        $this->data['name']         = $store['name'];
        $this->data['id']           = $store['id'];
        $this->data['local_cd']     = $store['more']['LOCAL_CD'];
        $this->data['company_id']   = $store['more']['GROUP_CD'];
        $this->data['company']      = $store['more']['GROUP_CD'];
        $this->data['min']          = intval($store['minimum']);
        $this->data['rep']          = $store['stats']['points'];
        $this->data['vc']           = $store['stats']['completed_sales_perc'];
        
        $categoria = $this->getCategoria($store);
        
        $this->data['categorie']        = $categoria['categorie'];
        $this->data['category_default'] = $categoria['category_default'];
        $this->data['categories_store'] = $categoria['categories_store'] ;
        $this->data['paquete']          = $categoria['paquete'];

        $this->data['cleaned'] = $store['cleaned'];
        $this->data['status'] = $store['status'];
        $this->data['max_discount'] = isset($store['stats']) && isset($store['stats']['max_disc_perc']) ? $store['stats']['max_disc_perc'] : 0;

        $this->data['favorite'] = $store['favorite'];

      } catch (\Throwable $th) {
        \Log::info($th->getMessage());
      }
    }

    public function getMarca()
    {
        return $this->data;
    }
}
