<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StoresVisits;
use App\Models\ProductVisits;
use Auth;
class VisitsController extends Controller
{
    public function StoreVisits(Request $request)
    {
        $this->validate($request, [
            'GROUP_CD' => 'required',
            'LOCAL_CD' => 'required',
        ]);

        if ($request->isJson()) {
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
        }
        return response()->json(['status' => true ], 200);
    }

    public function ProductVisits(Request $request)
    {
        // dd( Auth::user()->num);
        $this->validate($request, [
            'MODELOS_NUM' => 'required',
        ]);

        if ($request->isJson()) {
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
        }
        return response()->json(['status' => true ], 200);
    }
}
