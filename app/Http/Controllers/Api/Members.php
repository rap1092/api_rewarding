<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\Farming;
use App\Models\TgMemberReff;
use App\Models\TgMembers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Members extends Controller
{
    public function register(Request $request)
    {
        $userId = $request->input('userId');
        $reff = $request->input('reff');
        $name = $request->input('fullname');
        $usrname = $request->input('username');

        if (!TgMembers::where('userTgId', $userId)->exists()) {
            $data = $this->getDataByIp($this->getUserIpAddress());
            $create = TgMembers::create([
                'userTgId' => $userId,
                'refferalTgId' => $reff,
                'fullname' => $name,
                'usernameTg' => $usrname,
                'userinfo' => request()->header('User-Agent') ?? '',
                'uri' => request()->getRequestUri() ?? '',
                'org' => $this->getOrg($data),
                'referer' => request()->header('Referer') ?? 'unknown',
                'country' => $this->getCountry($data),
                'city' => $this->getCity($data),
            ]);

            if ($create) {
                $this->addReward($userId, $reff);
                return response()->json(['status' => true, 'message' => 'Member is successfully registered'], 200, [], JSON_PRETTY_PRINT);
            }

            return response()->json(['status' => true, 'message' => 'Failure to register'], 400, [], JSON_PRETTY_PRINT);
        }

        return response()->json(['status' => false, 'message' => 'User Id already exists'], 400, [], JSON_PRETTY_PRINT);
    }

    public function memberIsLogedin(Request $request)
    {
        $userId = $request->input('user_id');
        if (TgMembers::where('userTgId', $userId)->exists()) {
            $data = $this->getDataByIp($this->getUserIpAddress());
            $checkisExist = TgMembers::where('userTgId', $userId)
                ->where(function ($query) {
                    $query->whereNull('ipaddress')
                        ->orWhere('country', 'Unknown');
                })
                ->count();

            if ($checkisExist) {
                TgMembers::where('userTgId', $userId)->update([
                    'userinfo' => request()->header('User-Agent') ?? '',
                    'ipaddress' => $this->getUserIpAddress() ?? '',
                    'uri' => request()->getRequestUri() ?? '',
                    'org' => $this->getOrg($data),
                    'referer' => request()->header('Referer') ?? 'unknown',
                    'country' => $this->getCountry($data),
                    'city' => $this->getCity($data),
                ]);
            }

            return response()->json(['status' => true, 'message' => 'Authenticated'], 200, [], JSON_PRETTY_PRINT);
        }

        return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401, [], JSON_PRETTY_PRINT);
    }

    public function addReward($userId, $fromReffId)
    {
        if (!Balance::where('userTgId', $userId)->exists()) {
            $rewardFromJoin = Cache::remember('reward_join', 3600, function () {
                return DB::table('reward_masters')->where('type', 'join')->value('amount');
            });
            $rewardFromReff = Cache::remember('reward_refferal', 3600, function () {
                return DB::table('reward_masters')->where('type', 'refferal')->value('amount');
            });

            $create = Balance::create([
                'userTgId' => $userId,
                'balance' => $rewardFromJoin,
                'wdID' => hash('sha512', Str::uuid()),
            ]);

            if ($fromReffId) {
                $balanceReff = Balance::where('userTgId', $fromReffId)->first();
                $balanceReff->update(['balance' => $balanceReff->balance + $rewardFromReff]);

                TgMemberReff::create([
                    'userTgId' => $fromReffId,
                    'userTgIdJoined' => $userId,
                    'amount' => $rewardFromReff,
                ]);

                return true;
            }

            return $create;
        }

        return true;
    }

    public function getUserInfo(Request $request)
    {
        $userId = $request->input('userTgId');
        $balance = Balance::where('userTgId', $userId)->value('balance');
        $userInfo = TgMembers::where('userTgId', $userId)->first();

        return response()->json([
            'balance' => number_format($balance, 0, ',', '.'),
            'userInfo' => $userInfo
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function farming(Request $request)
    {
        $userId = $request->input('userTgId');
        $rewardAmount = Cache::remember('reward_farming', 3600, function () {
            return DB::table('reward_masters')->where('type', 'farming')->value('amount');
        });

        $start = Carbon::now('Asia/Jakarta');
        $target = Carbon::parse($start)->addHours(8);

        $farming = Farming::create([
            'userTgId' => $userId,
            'transactionId' => Str::uuid(),
            'startFarmingDate' => $start,
            'targetFarmingDate' => $target,
            'reward' => $rewardAmount,
            'status' => 'farming',
            'point' => 27.8,
        ]);

        return response()->json([
            'data' => $farming,
            'start' => $start->format('Y-m-d H:i:s'),
            'target' => $target->format('Y-m-d H:i:s'),
            'perseconds' => $rewardAmount / 28800,
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function getFarming(Request $request)
    {
        $userId = $request->input('userTgId');
        $farmingData = Farming::where('userTgId', $userId)->where('status', 'farming')->first();

        if ($farmingData) {
            $start = Carbon::parse($farmingData->startFarmingDate)->format('Y-m-d H:i:s');
            $target = Carbon::parse($farmingData->targetFarmingDate)->format('Y-m-d H:i:s');
            $perSecondReward = $farmingData->reward / 28800;

            return response()->json([
                'data' => $farmingData,
                'start' => $start,
                'target' => $target,
                'perseconds' => $perSecondReward,
            ], 200, [], JSON_PRETTY_PRINT);
        }

        return response()->json(['data' => null, 'perseconds' => 1], 200, [], JSON_PRETTY_PRINT);
    }

    public function claim(Request $request)
    {
        $userId = $request->input('userTgId');
        $farm = Farming::where('userTgId', $userId)->where('status', 'farming')->update(['status' => 'claimed']);

        if ($farm) {
            $rewardAmount = Cache::remember('reward_farming', 3600, function () {
                return DB::table('reward_masters')->where('type', 'farming')->value('amount');
            });

            $balance = Balance::where('userTgId', $userId)->first();
            $balance->balance += $rewardAmount;
            $balance->save();

            return response()->json(['balance' => number_format($balance->balance, 0, ',', '.'), 'claim' => 'yes'], 200, [], JSON_PRETTY_PRINT);
        }

        return response()->json(['balance' => 0, 'claim' => 'no'], 200, [], JSON_PRETTY_PRINT);
    }

    private function getDataByIp($ip)
    {
        $apiUrl = "http://ip-api.com/json/{$ip}";
        $response = file_get_contents($apiUrl);
        return json_decode($response, true);
    }

    private function getCountry($data)
    {
        return $data['country'] ?? 'Unknown';
    }

    private function getCity($data)
    {
        return $data['city'] ?? 'Unknown';
    }

    private function getOrg($data)
    {
        return $data['org'] ?? 'Unknown';
    }

    private function getUserIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }
}
