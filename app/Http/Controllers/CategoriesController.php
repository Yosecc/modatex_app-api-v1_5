<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Products;
use App\Models\TipoModeloUno as Category;
use App\Models\TipoModeloDos as SubCategory;

class CategoriesController extends Controller
{
    public function getCategories(Request $request){
        
        $categories = new Category($request->all());
        $categories = $categories->getCategories();
        foreach ($categories as $key => $category) {
          $products = new Products($request->all());
          $category['products'] = $products->productsCategories($category['id']);
        }

        return response()->json($categories,200);
    }

    public function getSubCategories(Request $request){

        $subcategories = new SubCategory($request->all());
        $subcategories = $subcategories->getSubCategories();

        return response()->json(['subcategories'=> $subcategories],200);

    }
}
