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
            $data = $this->getDataByIp($this->getUserIpAddress());
            $create = TgMembers::create([
                'userTgId' => $userId,
                'refferalTgId' => $reff,
                'fullname' => $name,
                'usernameTg' => $usrname,
                'userinfo'  =>  $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri'  => $_SERVER['REQUEST_URI'] ?? '',
                'org'  => $this->getOrg($data),
                'referer'  => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'unknown',
                'country' => $this->getCountry($data),
                'city' => $this->getCity($data),
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
            $data = $this->getDataByIp($this->getUserIpAddress());
            $update = TgMembers::where([
                'userTgId' => $userId,
            ])->update([
                'userinfo'  =>  $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ipaddress'  =>  $this->getUserIpAddress() ?? '',
                'uri'  => $_SERVER['REQUEST_URI'] ?? '',
                'org'  => $this->getOrg($data),
                'referer'  => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'unknown',
                'country' => $this->getCountry($data),
                'city' => $this->getCity($data),
            ]);

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
            'perseconds' => $farming ? (float) ($farming->reward / 28800) : 1
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
            'perseconds' => $farm->count() > 0 ? (float) ($data->reward / 28800) : 1
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

    function getDataByIp($ip)
    {
        $apiUrl = "http://ip-api.com/json/{$ip}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return $data;
    }

    function getCountry($data){
        return isset($data['country']) ? $data['country'] : 'Unknown';
    }

    function getCity($data){
        return isset($data['city']) ? $data['city'] : 'Unknown';
    }

    function getOrg($data){
        return isset($data['org']) ? $data['org'] : 'Unknown';
    }



    function getUserIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            // IP dari ISP proxy
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // IP dari load balancer atau proxy
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            // IP address langsung dari pengguna
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }
        return $ipAddress;
    }



}
