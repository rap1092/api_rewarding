<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class Withdraw extends Controller
{
    protected $fairlaunchLink = "https://www.pinksale.finance/solana/launchpad/9nAFikRNTRwqaFvt2V6N4FCQ5GS7thTPHLqbsfMPjKoM";

    public function getTime(Request $request)
    {
        $airdrop = Cache::remember('airdrop', 3600, function () {
           return $airdrop = DB::table('countdowns')->where('type', 'airdrop')->first();
        });
        $fairlaunch = Cache::remember('fairlaunch', 3600, function () {
            return $airdrop = DB::table('countdowns')->where('type', 'fairlaunch')->first();
        });
        $dateAirdrop = Carbon::parse($airdrop->endTime);
        $airdrop = [
            'year' => $dateAirdrop->year,
            'month' => $dateAirdrop->month == 1 ? 0 : $dateAirdrop->month - 1,
            'day' => $dateAirdrop->day,
            'hour' => $dateAirdrop->hour,
            'minute' => $dateAirdrop->minute,
            'second' => $dateAirdrop->second,
        ];

        $wdId = Balance::where(['userTgId' => $request->input('userTgId')])->select('wdID')->first();
        $data = base64_encode("https://claim.minkspace.com?req=".$wdId->wdID);

        $dateFair = Carbon::parse($fairlaunch->endTime);
        $fairlaunch = [
            'year' => $dateFair->year,
            'month' => $dateFair->month == 1 ? 0 : $dateFair->month - 1,
            'day' => $dateFair->day,
            'hour' => $dateFair->hour,
            'minute' => $dateFair->minute,
            'second' => $dateFair->second,
        ];
        return Response()->json([
                'airdrop' => $airdrop, 
                'fairlaunch' => $fairlaunch, 
                "fairlink" => $this->fairlaunchLink,
                'x' => $data
        ]);

    }
}
