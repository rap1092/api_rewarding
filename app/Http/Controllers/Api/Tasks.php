<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserTasks;
use Illuminate\Http\Request;
use App\Models\Balance;
use App\Models\TgMemberReff;
use App\Models\TgMembers;
use Carbon\Carbon;
use DB;

class Tasks extends Controller
{
    public function getTasks(Request $request)
    {
        $userTgId = $request->input('userTgId');

        // Ambil semua data task dan join dengan user_tasks_rewards
        $this->TaskReset($userTgId);

        $tasks = DB::table('tasks_rewards as tr')
            ->leftJoin('user_tasks_rewards as utr', function ($join) use ($userTgId) {
                $join->on('tr.id', '=', 'utr.taskId')
                    ->where('utr.userTgId', '=', $userTgId);
            })
            ->select('tr.id', 'tr.amount', 'tr.remixicon', 'tr.title', 'tr.type', 'tr.username', 'tr.url', 'utr.status', 'utr.userTgId as user_task_userTgId')
            ->orderBy('tr.amount', 'desc')
            ->get();

        $formattedTasks = $tasks->map(function ($task) use ($userTgId) {
            if (!is_null($task->user_task_userTgId)) {
                if ($task->status !== '3') {
                    return [
                        'userTgId' => $task->user_task_userTgId,
                        'taskId' => (int)$task->id,
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
                return [
                    'userTgId' => $userTgId,
                    'taskId' => (int)$task->id,
                    'amount' => $this->formatNumber($task->amount),
                    'icon' => $task->remixicon,
                    'title' => $task->title,
                    'url' => $task->url,
                    'type' => $task->type,
                    'username' => $task->username,
                    'status' => '1',
                ];
            }
        })->filter()->values();

        return response()->json($formattedTasks);
    }

    public function TaskReset($userId)
    {
        $tasks = DB::table('tasks_rewards')
            ->where('type', 'always_exist')
            ->select('id')
            ->get();
        $ids = $tasks->pluck('id');

        UserTasks::where('userTgId', $userId)
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
        $data = DB::table('tasks_rewards')->find($taskId);
        
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Task not found'], 404);
        }

        $userTask = UserTasks::updateOrCreate(
            ['userTgId' => $userId, 'taskId' => $taskId],
            ['status' => '2', 'amount' => $data->amount]
        );

        return response()->json(['status' => true], 200, [], JSON_PRETTY_PRINT);
    }

    public function claim(Request $request)
    {
        $userId = $request->input('userTgId');
        $taskId = $request->input('taskId');
        $userTask = UserTasks::where([
            'userTgId' => $userId,
            'taskId' => $taskId,
        ])->first();

        if ($userTask && $userTask->update(['status' => '3'])) {
            $balance = Balance::where('userTgId', $userId)->first();
            $newBalance = $balance->balance + $userTask->amount;

            if (Balance::where('userTgId', $userId)->update(['balance' => $newBalance])) {
                return response()->json(['status' => true], 200, [], JSON_PRETTY_PRINT);
            }
        }

        return response()->json(['status' => false], 500, [], JSON_PRETTY_PRINT);
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
