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
    $router->post('LoginSocial','AuthController@LoginSocial');
    
});

// $router->group(['prefix' => 'notifications_push'], function() use ($router){
//     $router->get('save_token','NotificationsUserAppController@save_token');
// });


$router->post('saveToken','HomeController@saveToken');
$router->group(['middleware' => 'auth'], function () use ($router) {

    $router->get('generarCacheBloquesHome','HomeController@generarCacheBloquesHome');
    $router->get('home','HomeController@index');
    $router->get('menu','HomeController@menuList');


    

    $router->get('productsVisitados','HomeController@productsVisitados');
    $router->get('sliders','HomeController@sliders');
    $router->get('states/get','HomeController@statesGet');

    $router->post('preferences','PreferencesController@store');
    $router->get('preferences','PreferencesController@getPreferences');

    

    $router->post('likeStore','VisitsController@likeStore');

    
    $router->post('store_visits','VisitsController@StoreVisits');
    $router->post('product_visits','VisitsController@ProductVisits');

    $router->get('home','HomeController@index');
    $router->get('get_product_category/{category_id}','HomeController@get_product_category');
    
    $router->get('store/get_categories','StoresController@getCategoriesStore'); 
    $router->get('store/{store}','StoresController@getStore');
    $router->get('stores','StoresController@getStores'); 

    $router->get('product/{product_id}','ProductsController@oneProduct');
    $router->get('products','ProductsController@getProducts');
    $router->get('product_favorite','ProductsController@product_favorite');
    // $router->get('product/{product_id}', 'ProductsController@getModelos');

    $router->get('categories','CategoriesController@getCategories');

    $router->get('subcategories','CategoriesController@getSubCategories');

    // $router->get('cart/{client_id}','CartController@getCarts'); 
    // $router->get('getProductsCar','CartController@getProductsCar');

    $router->get('client','ClientController@index');

    $router->group(['prefix' => 'car'], function() use ($router){
        $router->post('addCar', 'CartController@addCar');
        $router->post('updateCar', 'CartController@updatedCar');
        $router->get('getCar', 'CartController@getCarts');

        $router->get('getCart/{store_id}', 'CartController@getCart');

        $router->get('getProductsCart/{store_id}','CartController@getProductsCart');
        $router->post('deleteModelo','CartController@deleteModelo');
        $router->post('deleteProduct','CartController@deleteProduct');
        $router->post('process_cart','CartController@processCart');
        $router->post('delete_carts','CartController@deleteCarts');
    });

    $router->get('getCategorieSearch/{categorie_id}','HomeController@getCategorieSearch');
    $router->get('getBloques','HomeController@getBloques');
    $router->get('getPromociones','HomeController@getPromociones');
    $router->get('getCategories','HomeController@getCategories');
    
    //ROSA
    $router->group(['prefix' => 'rosa'], function () use ($router) {
        $router->get('stores','StoresController@getStoresRosa');
        $router->get('getPromociones/{local_cd}','StoresController@getPromociones');
        $router->get('products','ProductsController@getProductsRosa');
        $router->get('products_home','StoresController@productsHome');
        $router->get('search','ProductsController@getSearch');
        
        $router->post('inProducts','ProductsController@inProducts');
    });
    //
    $router->group(['prefix' => 'ventas'], function () use ($router) {
        $router->get('/','VentasController@index');
    });

    $router->get('/getComboDirecciones','AddressController@getComboDirecciones');
    
    $router->group(['prefix' => 'profile'], function () use ($router) {

        $router->group(['prefix' => 'direcciones'], function () use ($router) {
            $router->get('/','AddressController@index');
            $router->post('create','AddressController@create');
            $router->post('deleteDireccion','AddressController@deleteDireccion');
            $router->post('update/{adress}','AddressController@update');
            $router->post('change_principal_address','AddressController@changePrincipalAddress');
        });
        
        $router->group(['prefix' => 'coupons'], function () use ($router) {
            $router->get('/','CouponsController@index');
            $router->get('redeem_coupon','CouponsController@redeemCoupon');
            $router->post('canjear_cupon','CouponsController@canjearCupon');
           
            
        });

        $router->group(['prefix' => 'client'], function () use ($router) {
            $router->get('/','ClientController@index');
            $router->post('change_password','ClientController@change_password');
            $router->post('update','ClientController@update');

        });
    });
    
    $router->get('descuentosExclusivos','CouponsController@descuentosExclusivos');
    
    $router->group(['prefix' => 'checkout'], function() use ($router){
        $router->post('getEnvios','CheckoutController@getEnvios');
        $router->post('editClient','CheckoutController@editClient');
        $router->post('selectMethodEnvio','CheckoutController@selectMethodEnvio');
        $router->post('searchSucursales','CheckoutController@searchSucursales');
        $router->post('datosEnvio','CheckoutController@datosEnvio');
        $router->post('envioDetail','CheckoutController@envioDetail');
        $router->post('deleteShipping','CheckoutController@deleteShipping');
        $router->post('homeDeliveryProviders','CheckoutController@homeDeliveryProviders');
        $router->post('editServiceProvider','CheckoutController@editServiceProvider');
        $router->post('datosFacturacion','CheckoutController@datosFacturacion');
        $router->post('isDatosFacturacion','CheckoutController@isDatosFacturacion');
        $router->post('selectMethodPayment','CheckoutController@selectMethodPayment');
        $router->post('getResumen','CheckoutController@getResumen');
        $router->post('confirmarCompra','CheckoutController@confirmarCompra');
        $router->post('couponUnselectAll','CheckoutController@couponUnselectAll');
        $router->post('couponSelect','CheckoutController@couponSelect');
        $router->post('getMetodos','CheckoutController@getMetodos');
        $router->post('shippingSelectAddress','CheckoutController@shippingSelectAddress');
        $router->post('getMetodosPagos','CheckoutController@getMetodosPagos');
        $router->post('getHorarios','CheckoutController@getHorarios');
        

    });

    $router->group(['prefix' => 'notifications_push'], function() use ($router){
        $router->post('save_token','NotificationsUserAppController@save_token');
        $router->post('notification_send','NotificationsUserAppController@notification_send');
        $router->post('getTokens','NotificationsUserAppController@getTokens');
        $router->get('get_notifications','NotificationsUserAppController@get_notifications');
    });

    $router->group(['prefix' => 'cms'], function() use ($router){
        $router->get('get/{id}','cmsController@get');
    });
    
});

