<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Objects\NotificationsPush;
use App\Models\NotificationsApp;
use App\Models\Links;
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

        // dd(Auth::user()->num);
        $notification = new NotificationsPush($request->all());
        $notificacion = $notification->sendUserNotification($request->client_id);


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

        $notification = NotificationsUserApp::updateOrInsert([ 'client_num' => Auth::user()->num , 'token' => $request->token ],[
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
        $noti = NotificationsApp::
                    where(function ($query) {
                        $query->where('type', 'LIKE', '%massive_msg.Mensaje%')
                              ->orWhere('client_num', Auth::user()->num);
                    })
                    ;
        if($noti->count()){
            $notificationsIds = $noti->pluck('num');  
            $links = Links::whereIn('section_id', $notificationsIds->toArray() )->get();

            $noti = $noti->map(function($item) use ($links) {
                $redirect = $links->where('section_id', $item['num'])->first();
                return [
                    'body' => html_entity_decode($item['msg']),
                    'title' => html_entity_decode($item['title']),
                    'id' => $item['num'],
                    'created_at' => $item['created_at'],
                    'image' => 'https://netivooregon.s3.amazonaws.com/'.$item['img'],
                    'redirect' =>  $redirect ? json_decode($redirect->data_json) : NULL,
                ];
            });

        }
         

        return response()->json($noti);
    }
}
