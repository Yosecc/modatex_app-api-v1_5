<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Objects\NotificationsPush;
use App\Models\NotificationsApp;
use App\Models\NotificationsUserApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class NotificationsUserAppController extends Controller
{
    public function notification_send(Request $request)
    {
        $this->validate($request, [
            'notification' => 'required'
        ]);

        $validator = Validator::make($request->notification, [
            'title' => 'required',
            'body' => 'required',
        ]);
        

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }


        $notification = new NotificationsPush($request->all());

        $notificacion = $notification->sendUserNotification(Auth::user()->num);


        if($notification->fails()){
             return response()->json($notification->getErrors(), 422);
        }

        return response()->json($notificacion);
    }

    public function save_token(Request $request)
    {

        $this->validate($request, [
            'token' => 'required'
        ]);

        $notification = NotificationsUserApp::insert([ 'client_num' => Auth::user()->num ],[
            'token' => $request->token, 
            'platform' => 'app',
        ]);

        return response()->json($notification ? true : false);
    }

    public function getTokens()
    {
        return NotificationsUserApp::all();
    }

    public function get_notifications()
    {
        $notificaciones = NotificationsApp::where('client_num',Auth::user()->num)
                            ->orderBy('id','desc')->get();
        

        return response()->json($notificaciones->makeHidden(['id','updated_at','client_num']));
    }
}
