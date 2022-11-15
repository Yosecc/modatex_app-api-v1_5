<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'auth'], function () use ($router) {
    $router->post('register', 'AuthController@register');
    $router->post('login', 'AuthController@login');
    $router->post('code_validation','AuthController@code_validation');
    $router->get('resend_code/{email}','AuthController@resend_code');
    $router->post('recover_password','AuthController@recover_password');
    
    
});


$router->group(['middleware' => 'auth'], function () use ($router) {

    $router->get('home','HomeController@index');
    $router->get('productsVisitados','HomeController@productsVisitados');
    $router->get('sliders','HomeController@sliders');

    $router->get('getProductsCar','CartController@getProductsCar');

    $router->post('preferences','PreferencesController@store');
    $router->get('preferences','PreferencesController@getPreferences');

    $router->post('store_visits','VisitsController@StoreVisits');
    $router->post('product_visits','VisitsController@ProductVisits');

    $router->get('home','HomeController@index');
    $router->get('get_product_category/{category_id}','HomeController@get_product_category');
    
     $router->get('store/get_categories','StoresController@getCategoriesStore'); 
    $router->get('store/{store}','StoresController@getStore');
    $router->get('stores','StoresController@getStores'); 
    


    $router->get('product/{product}','ProductsController@getProduct');
    $router->get('products','ProductsController@getProducts');
    $router->get('product_favorite','ProductsController@product_favorite');

    $router->get('categories','CategoriesController@getCategories');

    $router->get('subcategories','CategoriesController@getSubCategories');

    $router->get('cart/{client_id}','CartController@getCarts'); 

    $router->get('client','ClientController@index');

    $router->group(['prefix' => 'car'], function() use ($router){
        $router->post('addCar', 'CartController@addCar');
        $router->post('updatedCar', 'CartController@updatedCar');
        $router->get('getCar', 'CartController@getCar');
        $router->get('getProductsCart/{store_id}','CartController@getProductsCart');
        $router->post('deleteModelo','CartController@deleteModelo');
        $router->post('deleteProduct','CartController@deleteProduct');
        $router->post('process_cart','CartController@processCart');
        $router->post('delete_carts','CartController@deleteCarts');
    });

    //ROSA
    $router->group(['prefix' => 'rosa'], function () use ($router) {
        $router->get('stores','StoresController@getStoresRosa');
        $router->get('products','ProductsController@getProductsRosa');
        $router->get('products_home','StoresController@productsHome');
        $router->get('search','ProductsController@getSearch');
    });
    //
    //
    $router->group(['prefix' => 'ventas'], function () use ($router) {
        $router->get('/','VentasController@index');
    });

    $router->get('/getProvincias','AddressController@getProvincias');
    
    $router->group(['prefix' => 'profile'], function () use ($router) {
        $router->group(['prefix' => 'direcciones'], function () use ($router) {
            $router->get('/','AddressController@index');
            $router->post('update/{adress}','AddressController@update');
            $router->post('change_principal_address','AddressController@changePrincipalAddress');
        });
        $router->group(['prefix' => 'coupons'], function () use ($router) {
            $router->get('/','CouponsController@index');
            $router->get('redeem_coupon','CouponsController@redeemCoupon');
        });

        $router->group(['prefix' => 'client'], function () use ($router) {
            $router->get('/','ClientController@index');
            $router->post('change_password','ClientController@change_password');
        });
    });
});

