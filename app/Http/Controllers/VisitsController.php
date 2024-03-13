<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StoresVisits;
use App\Models\ProductVisits;
use Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;


class VisitsController extends Controller
{
    private $token;
    public function StoreVisits(Request $request)
    {
        $this->validate($request, [
            'GROUP_CD' => 'required',
            'LOCAL_CD' => 'required',
        ]);

        // if ($request->isJson()) {
            $visits = StoresVisits::where('GROUP_CD',$request->GROUP_CD)
                                ->where('LOCAL_CD',$request->LOCAL_CD)
                                ->where('CLIENT_NUM', Auth::user()->num)
                                ->first();
            if (!$visits) {
                $visits = new StoresVisits();
                $visits->GROUP_CD   = $request->GROUP_CD;
                $visits->LOCAL_CD   = $request->LOCAL_CD;
                $visits->CLIENT_NUM = Auth::user()->num;
                $visits->save();
            }else{
                $visits->updated_at = \Carbon\Carbon::now();
                $visits->save();
            }
        // }
        return response()->json(['status' => true ], 200);
    }

    public function ProductVisits(Request $request)
    {
        // dd( Auth::user()->num);
        $this->validate($request, [
            'MODELOS_NUM' => 'required',
        ]);

        // if ($request->isJson()) {
            $visits = ProductVisits::where('MODELOS_NUM',$request->MODELOS_NUM)
                                ->where('CLIENT_NUM', Auth::user()->num)
                                ->first();
            if (!$visits) {
                $visits = new ProductVisits();
                $visits->MODELOS_NUM   = $request->MODELOS_NUM;
                $visits->CLIENT_NUM = Auth::user()->num;
                $visits->save();

                

            }else{
                $visits->updated_at = \Carbon\Carbon::now();
                $visits->save();
            }
            try {
                $visitados = ProductVisits::where('CLIENT_NUM', Auth::user()->num)->orderBy('updated_at','desc')->limit(20)->get();

                $visitados = $visitados->map(function($item){

                    $producto = new ProductsController();
                    return $producto->oneProduct($item->MODELOS_NUM);

                })->filter(fn ($valor) => $valor !== null);

                Cache::put('visitados'.Auth::user()->num, $visitados->toArray());
            } catch (\Throwable $th) {
                //throw $th;
            }
            
        // }
        return response()->json(['status' => true ], 200);
    }

    public function likeStore(Request $request)
    {
        // dd(Auth::user()->api_token);
        $response = Http::withHeaders([
            'x-api-key' => Auth::user()->api_token,
        ])
        ->asForm()
        ->get('https://www.modatex.com.ar/modatexrosa3/?c=Favorites::toggle&store_id='.$request->store_id.'&company_id='.$request->company_id.'&_='.\Carbon\Carbon::now()->timestamp);

        // $storesCache = Cache::get('stores');
        //     dd($storesCache->where('id',$request->store_id)->map(function($item){
        //         $item['favorite'] = true;
        //         return $item;
        //     }));
        // $response = Http::withHeaders([
        //     'x-api-key' => Auth::user()->api_token,
        // ])
        // ->asForm()
        // // ->acceptJson()
        // ->get('https://www.modatex.com.ar/modatexrosa3/?c=Checkout::shipping_method&group_id=abyjtwl0');
        // dd($response->body());

        // $response = Http::withHeaders([
        //     'x-api-key' => Auth::user()->api_token,
        // ])
        // ->asForm()
        // ->acceptJson()
        // ->get('https://www.modatex.com.ar?c=Favorites::added');
        // dd($response->json());

        return response()->json($response->json());
    }
}
