<?php

namespace App\Http\Traits;

use Illuminate\Support\Arr;
use App\Models\Products;
use App\Models\Store;
use Illuminate\Http\Request;

use App\Http\Traits\HelpersTraits;

trait TipoModeloUnoTraits {

	use HelpersTraits;
	
  public function dataCategoryCollect($category){
    return collect([
      'id'          => $category->NUM,
      'name'        => $category->TIPO_NAME,
      'descripcion' => $category->DESCRIPTION,
      'subcategories' => $this->dataSubcategories($category->subCategories)
    ]);

  }

  public function dataCategoriesArray($categories, $params){
    $func = function($category) use ($params) {
      $category = $this->dataCategoryCollect($category, $params);
      return $category;
    };
    return array_map($func, $categories->all());
  }

  public function dataSubcategory($subcategory){
    return collect([
      'id'=>$subcategory['NUM'],
      'name'=> $subcategory['TIPO_NAME'],
      'description'=> $subcategory['DESCRIPTION'],
      'category_id' => $subcategory['PARENT_NUM']
    ]);
  }

  public function dataSubcategories($subcategories){
    $func = function($subcategory) {
      $subcategory = $this->dataSubcategory($subcategory);
      return $subcategory;
    };
    return array_map($func, $subcategories->all());
  }
	
}