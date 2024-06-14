<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Balance;
use App\Models\Farming;
use App\Models\TgMemberReff;
use App\Models\TgMembers;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Str;

class Refferal extends Controller
{
    protected $refferalLink = "https://t.me/mink_coin_rewards_bot/mink?startapp=";
    public function getInfo(Request $request){
        $userId = $request->input('userTgId');
        $totalReward = number_format(TgMemberReff::where(['userTgId'=>$userId])->sum('amount'),0,",",".");
        $fromRefferal= DB::table('reward_refferals')
                            ->join('members','members.userTgId','=','reward_refferals.userTgIdJoined')
                            ->join('balances','balances.userTgId','=','reward_refferals.userTgIdJoined')
                       ->where(['reward_refferals.userTgId'=>$userId])
                       ->select('reward_refferals.amount','members.fullname','balances.balance')
                       ->orderBy('reward_refferals.id','desc')->get();
        $rewardPerRef= DB::table('reward_masters')->where(['type' => 'refferal'])
                        ->select('amount')->first();
        $rewardPerRefferal = $this->formatNumber($rewardPerRef->amount);
        $data = [];
        foreach($fromRefferal as $item){
            array_push($data,[
                'fullname' => $item->fullname,
                'amount' => $this->formatNumber($item->amount),
                'balance' => $this->formatNumber($item->balance),
            ]);
        }
        return Response()->json([
            'totalReward' => $totalReward,
            'data' => $data,
            'rewardPerRef' => $rewardPerRefferal,
            'refferalLink' => $this->refferalLink.$userId
        ],200,[],JSON_PRETTY_PRINT);
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
