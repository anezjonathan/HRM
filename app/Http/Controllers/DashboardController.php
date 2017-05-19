<?php

namespace App\Http\Controllers;

use App\RentCollection;
use Carbon\Carbon;
use DB;
use Faker\Provider\DateTime;
use Illuminate\Http\Request;
use App\Rent;
use App\MyNotify;

class DashboardController extends Controller
{
    public function index(){
        $collections = RentCollection::whereMonth('collectionDate', '=', date('m'))->whereYear('collectionDate', '=', date('Y'))
            ->where('deleted_at',null)
            ->sum('amount');

        $collectionsHave = RentCollection::select('rents_id')->whereMonth('collectionDate', '=', date('m'))->whereYear('collectionDate', '=', date('Y'))
            ->pluck('rents_id');
        $notPaidRent = Rent::select(DB::raw('sum(rent) AS total_rent'),DB::raw('sum(serviceCharge) AS total_service'),DB::raw('sum(utilityCharge) AS total_utility'))
            ->whereNotIn('id',$collectionsHave)
            ->first();
        $totalDue = $notPaidRent->total_rent + $notPaidRent->total_service + $notPaidRent->totalutility;

        $total = $collections + $totalDue;

        $newRenters = Rent::orderBy('entryDate','desc')->with('customer')->take(5)->get();
        $collectionsAll =  RentCollection::select(
            DB::raw('sum(amount) as amounts'),
            DB::raw("DATE_FORMAT(collectionDate,'%m-%d-%Y') as date")
        )->groupBy('collectionDate')->get();

        return view('dashboard',compact('collections','totalDue','total','newRenters','collectionsAll'));
    }
    public function mailCompose(){
        return view('composemail');
    }
    public function mailSend(){
        return view('composemail');
    }

    public function deleteNotification(Request $request){
        $notiType = $request->get('type');

        if($notiType=="collection"){
            $notifications = MyNotify::where('notiType',trim($notiType))->orderBy('created_at','asc')->take(5)->get();
            foreach ($notifications as $noti){
                $noti->delete();
            }
            session(['collectionNotifications' => []]);

        }
        if($notiType=="due"){
            $notifications = MyNotify::where('notiType',trim($notiType))->where('isRead',0)->orderBy('created_at','asc')->take(5)->get();
            foreach ($notifications as $noti){
                $noti->isRead=1;
                $noti->save();
            }
            session(['dueNotifications' => []]);
            $today = Carbon::today()->format('Y-m-d');
            $readedNotis = MyNotify::where('notiType',trim($notiType))->where('isRead',1)
                ->whereDate('created_at','<',$today)->get();
            foreach ($readedNotis as $noti){
                $noti->delete();
            }

        }
        if($notiType=="tolet"){
            $notifications = MyNotify::where('notiType',trim($notiType))->orderBy('created_at','asc')->take(5)->get();
            foreach ($notifications as $noti){
                $noti->delete();
            }
            session(['toletNotifications' => []]);
        }


        return ['message'=>'5 notificaton clean'];
    }

    public function fetchAll(){
        //crate due notification
        if(!\Session::has('collectionNotifications') || !count(session('collectionNotifications'))){
            $collectionNotifications = MyNotify::where('notiType','collection')->orderBy('created_at','asc')->take(5)->get();
            session(['collectionNotifications' => $collectionNotifications]);

        }else{
            $collectionNotifications = session('collectionNotifications');
        }
        if(!\Session::has('dueNotifications') || !count(session('dueNotifications'))){
            $today = Carbon::today()->format('Y-m-d');
            $haveDueNotification = MyNotify::where('notiType','due')
                ->whereDate('created_at',$today)->where('isRead', 0)->count();

            if($haveDueNotification) {
                $dueNotifications = MyNotify::where('notiType','due')->where('isRead', 0)->orderBy('created_at','asc')->take(5)->get();
                session(['dueNotifications' => $dueNotifications]);
            }
            else{
                $collectionsHave = RentCollection::select('rents_id')->whereMonth('collectionDate', '=', date('m'))->whereYear('collectionDate', '=', date('Y'))
                    ->pluck('rents_id');
                $notPaidRentCustomers = Rent::with('customer')
                    ->whereNotIn('id',$collectionsHave)
                    ->get();
                foreach ($notPaidRentCustomers as $rent){
                    //notification code
                    $myNoti = new MyNotify();
                    $myNoti->title = $rent->customer->name;
                    $myNoti->value = $rent->rent+$rent->serviceCharge+$rent->utilityCharge;
                    $myNoti->notiType = "due";
                    $myNoti->save();
                    //end mynoti
                }
                $dueNotifications = MyNotify::where('notiType','due')->where('isRead', 0)->orderBy('created_at','asc')->take(5)->get();
                session(['dueNotifications' => $dueNotifications]);

            }
        } else{
            $dueNotifications = session('dueNotifications');
        }

        if(!\Session::has('toletNotifications') || !count(session('toletNotifications'))){
            $toletNotifications = MyNotify::where('notiType','tolet')->orderBy('created_at','asc')->take(5)->get();
            session(['toletNotifications' => $toletNotifications]);

        }else{
            $toletNotifications = session('toletNotifications');
        }
        $hasAnyNotification=0;
        if(count($collectionNotifications) || count($dueNotifications) || count($toletNotifications)){
            $hasAnyNotification = 1;
        }

        return [
            'collection' => $collectionNotifications,
            'due' => $dueNotifications,
            'tolet' => $toletNotifications,
            'hasNotify' => $hasAnyNotification
        ];
    }
}
