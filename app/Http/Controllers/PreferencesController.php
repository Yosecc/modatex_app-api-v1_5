<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use App\Models\CategoryPreferences;
use App\Models\SubcategoryPreferences;

class PreferencesController extends Controller
{

    public $preferences;
    
    public function getPreferences(Request $request)
    {
        $this->preferences = Auth::user()->preferences;

        return response()->json(['status'=>true,'preferences'=>$this->preferences],200);
    }


    public function store(Request $request)
    {

        $this->validate($request, [
            'categories' => 'required',
        ]);

        if (count($request->categories) > 0) {
            foreach ($request->categories as $key => $category) {
                $preference = CategoryPreferences::where('CLIENT_NUM', Auth::user()->num)
                                ->where('TIPOMODELO_DP1_NUM',$category['id'])->first();
                if (!$preference) {

                    $preference                     = new CategoryPreferences();
                    $preference->CLIENT_NUM         = Auth::user()->num;
                    $preference->TIPOMODELO_DP1_NUM = $category['id'];
                    $preference->save();
                }

                if (count($category['subcategories']) > 0) {
                    foreach ($category['subcategories'] as $key => $subcategory) {
                        $subpreference = SubcategoryPreferences::
                                            where('TIPOMODELO_DP1_NUM', $category['id'])
                                            ->where('TIPOMODELO_DP2_NUM',$subcategory)->first();
                        if (!$subpreference) {
                            $subpreference                      = new SubcategoryPreferences();
                            $subpreference->TIPOMODELO_DP1_NUM  = $category['id'];
                            $subpreference->TIPOMODELO_DP2_NUM  = $subcategory;
                            $subpreference->CLIENT_NUM          = Auth::user()->num;
                            $subpreference->save();
                        }
                    }
                }   
            }
        }

        $this->getPreferences($request);

        return response()->json(['status'=> true, 'preferences' => $this->preferences ]);
    }
}
