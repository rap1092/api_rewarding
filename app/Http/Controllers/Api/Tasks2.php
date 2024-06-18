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
        $userTgId = $request->input('userTgId');

        // Ambil semua data task dan join dengan user_tasks_rewards
        $tasks = DB::table('tasks_rewards as tr')
            ->leftJoin('user_tasks_rewards as utr', function($join) use ($userTgId) {
                $join->on('tr.id', '=', 'utr.taskId')
                     ->where('utr.userTgId', '=', $userTgId);
            })
            ->select('tr.*', 'utr.status', 'utr.userTgId as user_task_userTgId', 'utr.taskId as user_task_taskId')
            ->orderBy('tr.amount', 'desc')
            ->get();
    
        $formattedTasks = [];
        foreach ($tasks as $task) {
            // Jika user_task_userTgId tidak null, berarti user sudah pernah mengakses task tersebut
            if (!is_null($task->user_task_userTgId)) {
                // Panggil TaskReset jika diperlukan
                $this->TaskReset($userTgId);
    
                // Hanya tambahkan task jika statusnya tidak 3
                if ($task->status !== '3') {
                    $formattedTasks[] = [
                        'userTgId' => $task->user_task_userTgId,
                        'taskId' => (int) $task->id,
                        'amount' => $this->formatNumber($task->amount),
                        'icon' => $task->remixicon,
                        'title' => $task->title,
                        'type' => $task->type,
                        'username' => $task->username,
                        'url' => $task->url,
                        'status' => $task->status,
                    ];
                }
            } else {
                // Jika user belum pernah mengakses task tersebut, tambahkan dengan status default 1
                $formattedTasks[] = [
                    'userTgId' => $userTgId,
                    'taskId' => (int) $task->id,
                    'amount' => $this->formatNumber($task->amount),
                    'icon' => $task->remixicon,
                    'title' => $task->title,
                    'url' => $task->url,
                    'type' => $task->type,
                    'username' => $task->username,
                    'status' => '1',
                ];
            }
        }
    
        return response()->json($formattedTasks);
    }
    // public function getTasks(Request $request)
    // {
    //     $task = DB::table('tasks_rewards')->orderBy('amount', 'desc')->get();
    //     $tasks = [];
    //     foreach ($task as $item) {
    //         $check = DB::table('user_tasks_rewards')->where([
    //             'userTgId' => $request->input('userTgId'),
    //             'taskId' => $item->id
    //         ]);
    //         if ($check->count() > 0) {
    //             $this->TaskReset($request->input('userTgId'));
    //             $row = $check->first();
    //             if ($row->status !== '3') {
    //                 array_push($tasks, [
    //                     'userTgId' => $row->userTgId,
    //                     'taskId' => (int) $row->taskId,
    //                     'amount' => $this->formatNumber($item->amount),
    //                     'icon' => $item->remixicon,
    //                     'title' => $item->title,
    //                     'type' => $item->type,
    //                     'username' => $item->username,
    //                     'url' => $item->url,
    //                     'status' => $row->status
    //                 ]);
    //             }
    //         } else {
    //             array_push($tasks, [
    //                 'userTgId' => $request->input('userTgId'),
    //                 'taskId' => (int) $item->id,
    //                 'amount' => $this->formatNumber($item->amount),
    //                 'icon' => $item->remixicon,
    //                 'title' => $item->title,
    //                 'url' => $item->url,
    //                 'type' => $item->type,
    //                 'username' => $item->username,
    //                 'status' => '1'
    //             ]);
    //         }
    //     }
    //     return Response()->json($tasks);
    // }

    public function TaskReset($userId)
    {
        $tasks = DB::table('tasks_rewards')
            ->where('type', 'always_exist')
            ->select('id')
            ->get();
        $ids = $tasks->pluck('id');
        
        $check = UserTasks::where('userTgId', $userId)
            ->where('status', 3)
            ->whereIn('taskId', $ids)
            ->where('updated_at', '<', Carbon::now()->subMinutes(30))
            ->update(['status' => 1]);
        return true;
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
        if ($check < 1) {
            $create = UserTasks::create([
                'userTgId' => $userId,
                'taskId' => $taskId,
                'amount' => $data->amount,
                'status' => '2'
            ]);
            return Response()->json(['status' => true], 200, [], JSON_PRETTY_PRINT);
        }
        else{
            $create = UserTasks::where([
                'userTgId' => $userId,
                'taskId' => $taskId,
            ])->update(['status' => '2','amount' => $data->amount]);
            return Response()->json(['status' => true], 200, [], JSON_PRETTY_PRINT);
        }

    }

    public function claim(Request $request)
    {
        $userId = $request->input('userTgId');
        $taskId = $request->input('taskId');
        $check = UserTasks::where([
            'userTgId' => $userId,
            'taskId' => $taskId,
        ]);
        if ($check->count() > 0) {
            $claim = UserTasks::where([
                'userTgId' => $userId,
                'taskId' => $taskId,
            ]);
            if ($claim->update(['status' => '3'])) {
                $balance = Balance::where(['userTgId' => $userId])->first();
                $claim = $claim->first();
                $balances = $balance->balance + $claim->amount;
                $updateBalance = Balance::where(['userTgId' => $userId]);
                if ($updateBalance->update(['balance' => $balances])) {
                    return Response()->json(['status' => true], 200, [], JSON_PRETTY_PRINT);
                }
                return Response()->json(['status' => true], 200, [], JSON_PRETTY_PRINT);
            }
            return Response()->json(['status' => false], 500, [], JSON_PRETTY_PRINT);
        }
        return Response()->json(['status' => false], 500, [], JSON_PRETTY_PRINT);
    }

    function formatNumber($number)
    {
        if ($number >= 1e12) {
            return round($number / 1e12, 2) . 'T';
        } elseif ($number >= 1e9) {
            return round($number / 1e9, 2) . 'B';
        } elseif ($number >= 1e6) {
            return round($number / 1e6, 2) . 'M';
        } elseif ($number >= 1e3) {
            return round($number / 1e3, 2) . 'K';
        } else {
            return $number;
        }
    }
}
