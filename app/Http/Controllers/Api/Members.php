<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\Farming;
use App\Models\TgMemberReff;
use App\Models\TgMembers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Constraint\IsEmpty;

class Members extends Controller
{
    public function register(Request $request)
    {
        $userId = $request->input('userId');
        $reff = $request->input('reff');
        $name = $request->input('fullname');
        $usrname = $request->input('username');
        $check = TgMembers::where(['userTgId' => $userId])->count();
        if ($check < 1) {
            $create = TgMembers::create([
                'userTgId' => $userId,
                'refferalTgId' => $reff,
                'fullname' => $name,
                'usernameTg' => $usrname
            ]);
            if ($create) {
                $this->addReward($userId, $reff);
                return Response()->json(['status' => true, 'message' => 'Member is successfully registered'], 200, [], JSON_PRETTY_PRINT);
            }
            return Response()->json(['status' => true, 'message' => 'Failure to register'], 400, [], JSON_PRETTY_PRINT);
        }
        return Response()->json([
            'status' => false,
            'message' => 'User Id is already exists'
        ], 400, [], JSON_PRETTY_PRINT);
    }

    public function memberIsLogedin(Request $request)
    {
        $userId = $request->input('user_id');
        $check = TgMembers::where(['userTgId' => $userId])->count();
        if ($check > 0) {
            return Response()->json([
                'status' => true,
                'message' => 'Authenticated'
            ], 200, [], JSON_PRETTY_PRINT);

        }
        return Response()->json([
            'status' => false,
            'message' => 'Un Authenticated'
        ], 401, [], JSON_PRETTY_PRINT);
    }


    function addReward($userId, $fromReffId)
    {
        $getBalanceJoin = Balance::where(['userTgId' => $userId]);
        $rewardFromJoin = DB::table('reward_masters')->where(['type' => 'join'])->select('amount')->first();
        $rewardFromReff = DB::table('reward_masters')->where(['type' => 'refferal'])->select('amount')->first();

        if ($getBalanceJoin->count() < 1) {
            $create = Balance::create([
                'userTgId' => $userId,
                'balance' => $rewardFromJoin->amount,
                'wdID' => hash('sha512', Str::uuid())
            ]);
            if ($fromReffId) {
                $getBalanceReff = Balance::where(['userTgId' => $fromReffId])->first();
                $update = Balance::where(['userTgId' => $fromReffId])->update([
                    'balance' => $getBalanceReff->balance + $rewardFromReff->amount
                ]);
                $balanceReward = TgMemberReff::create([
                    'userTgId' => $fromReffId,
                    'userTgIdJoined' => $userId,
                    'amount' => $rewardFromReff->amount
                ]);
                return ($create && $update && $balanceReward);
            }
            return $create;
        }
        return true;
    }

    public function getUserInfo(Request $request)
    {
        $balance = Balance::where([
            'userTgId' => $request->input('userTgId')
        ])->select('balance')->first();

        $userInfo = TgMembers::where(['userTgId' => $request->input('userTgId')])->first();
        return Response()->json([
            'balance' => number_format($balance->balance, 0, ",", "."),
            'userInfo' => $userInfo
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function farming(Request $request)
    {
        $userId = $request->input('userTgId');
        $reward = DB::table('reward_masters')->where('type', 'farming')->select('amount')->first();
        $start = Carbon::now('Asia/Jakarta');
        $target = Carbon::parse($start)->addHours(8);
        $farming = Farming::create([
            'userTgId' => $userId,
            'transactionId' => Str::uuid(),
            'startFarmingDate' => $start,
            'targetFarmingDate' => $target,
            'reward' => $reward->amount,
            'status' => 'farming',
            'point' => 27.8
        ]);
        $startFarm = Carbon::parse($farming->startFarmingDate);
        $targetFarm = Carbon::parse($farming->targetFarmingDate);
        $start = $startFarm->format('Y-m-d H:i:s');
        $target = $targetFarm->format('Y-m-d H:i:s');
        return Response()->json([
            'data' => $farming,
            'start' => $start ?? null,
            'target' => $target ?? null,
            'perseconds' => $farming ? $farming->amount / 86400 : 1 
        ], 200, [], JSON_PRETTY_PRINT);
    }


    public function getFarming(Request $request)
    {
        $userId = $request->input('userTgId');
        $farm = Farming::where([
            'userTgId' => $userId,
            'status' => 'farming'
        ]);
        if ($farm->count() > 0) {
            $data = $farm->first();
            $startFarm = Carbon::parse($data->startFarmingDate);
            $targetFarm = Carbon::parse($data->targetFarmingDate);
            $start = $startFarm->format('Y-m-d H:i:s');
            $target = $targetFarm->format('Y-m-d H:i:s');
        }
        return Response()->json([
            'data' => $data ?? $farm->first(),
            'start' => $start ?? null,
            'target' => $target ?? null,
            'perseconds' => $farm->count() > 0 ? $data->amount / 86400 : 1 
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function claim(Request $request)
    {
        $userId = $request->input('userTgId');
        $farm = Farming::where([
            'userTgId' => $userId,
            'status' => 'farming'
        ])->update(['status' => 'claimed']);
        $balance = Balance::where(['userTgId' => $userId])->first();
        if ($farm) {
            $reward = DB::table('reward_masters')->where(['type' => 'farming'])->select('amount')->first();
            $balance->balance = $balance->balance + $reward->amount;
            $balance->save();
            return Response()->json(['balance' => number_format($balance->balance, 0, ",", "."), 'claim' => "yes"], 200, [], JSON_PRETTY_PRINT);
        }
        return Response()->json(['balance' => number_format($balance->balance, 0, ",", "."), 'claim' => "no"], 200, [], JSON_PRETTY_PRINT);

    }
}
