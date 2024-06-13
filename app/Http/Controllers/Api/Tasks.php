<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserTasks;
use Illuminate\Http\Request;
use App\Models\Balance;
use App\Models\Farming;
use App\Models\TgMemberReff;
use App\Models\TgMembers;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Str;

class Tasks extends Controller
{
    public function getTasks(Request $request)
    {
        $task = DB::table('tasks_rewards')->orderBy('amount','desc')->get();
        $tasks = [];
        foreach ($task as $item) {
            $check = DB::table('user_tasks_rewards')->where([
                'userTgId' => $request->input('userTgId'),
                'taskId' => $item->id
            ]);
            if ($check->count() > 0) {
                $row = $check->first();
                if($row->status !== '3'){
                    array_push($tasks, [
                        'userTgId' => $row->userTgId,
                        'taskId' => $row->taskId,
                        'amount' => $this->formatNumber($item->amount),
                        'icon' => $item->remixicon,
                        'title' => $item->title,
                        'type' => $item->type,
                        'username' => $item->username,    
                        'url' => $item->url,
                        'status' => $row->status
                    ]);    
                }
            } else {
                array_push($tasks, [
                    'userTgId' => $request->input('userTgId'),
                    'taskId' => $item->id,
                    'amount' => number_format($item->amount,",","."),
                    'icon' => $item->remixicon,
                    'title' => $item->title,
                    'url' => $item->url,
                    'type' => $item->type,
                    'username' => $item->username,
                    'status' => '1'
                ]);
            }
        }
        return Response()->json($tasks);
    }

    public function claimCreate(Request $request)
    {
        $userId = $request->input('userTgId');
        $taskId = $request->input('taskId');
        $data = DB::table('tasks_rewards')->where(['id' => $taskId])->first();
        $check = UserTasks::where([
            'userTgId' => $userId,
            'taskId' => $taskId,
        ])->count();
        if($check < 1){
            $create = UserTasks::create([
                'userTgId' => $userId,
                'taskId' => $taskId,
                'amount' => $data->amount,
                'status' => '2'
            ]);
            return Response()->json(['status' => true],200,[],JSON_PRETTY_PRINT);    
        }
        return Response()->json(['status' => false],500,[],JSON_PRETTY_PRINT);    

    }

    public function claim(Request $request)
    {
        $userId = $request->input('userTgId');
        $taskId = $request->input('taskId');
        $check = UserTasks::where([
            'userTgId' => $userId,
            'taskId' => $taskId,
        ]);
        if($check->count() > 0){
            $claim = UserTasks::where([
                'userTgId' => $userId,
                'taskId' => $taskId,
            ]);
            if($claim->update(['status' => '3'])){
                $balance = Balance::where(['userTgId' => $userId])->first();
                $claim = $claim->first();
                $balances =  $balance->balance + $claim->amount;
                $updateBalance = Balance::where(['userTgId' => $userId]);
                if($updateBalance->update(['balance' => $balances])){
                    return Response()->json(['status' => true],200,[],JSON_PRETTY_PRINT);    
                }                
                return Response()->json(['status' => true],200,[],JSON_PRETTY_PRINT);    
            }
            return Response()->json(['status' => false],500,[],JSON_PRETTY_PRINT);    
        }
        return Response()->json(['status' => false],500,[],JSON_PRETTY_PRINT);    
    }

    function formatNumber($number)
    {
        if ($number >= 1e12) {
            // Triliunan
            return number_format($number / 1e12, 0) . 'T';
        } elseif ($number >= 1e9) {
            // Milyaran
            return number_format($number / 1e9, 0) . 'B';
        } elseif ($number >= 1e6) {
            // Jutaan
            return number_format($number / 1e6, 0) . 'M';
        } elseif ($number >= 1e3) {
            // Ribuan
            return number_format($number / 1e3, 0) . 'K';
        }

        // Angka kurang dari 1000 tetap ditampilkan apa adanya
        return number_format($number, 0);
    }
}
