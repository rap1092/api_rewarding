<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\Withdraw as WD;
use App\Library\EncryptionService;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class Withdraw extends Controller
{
    protected $fairlaunchLink = "https://raydium.io/swap/?inputCurrency=A1Rd2rGscGqUzUTaqcK1yC7M2r9jCmVKxeHFmXVnPvL9&outputCurrency=sol&fixed=in&inputMint=sol&outputMint=A1Rd2rGscGqUzUTaqcK1yC7M2r9jCmVKxeHFmXVnPvL9";

    public function getTime(Request $request)
    {
        $airdrop = DB::table('countdowns')->where('type', 'airdrop')->first();
        $fairlaunch = DB::table('countdowns')->where('type', 'fairlaunch')->first();
        
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
        $data = base64_encode("https://claim.minkspace.com/".$wdId->wdID);

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

    public function claim(Request $req){
        $reqid = $req->header('SecChUaOrigin');
        $real_balance = DB::table('member_balance_real_token')->where('wdID', $reqid)->first();
        $sc = DB::table("smart_contract")->where('id',1)->first();
        $point = DB::table('balances')->where('wdID', $reqid)->first();
        $ratio = DB::table('comparison_conversion')->select('conversion_result')->limit(1)->first();
        $data = new EncryptionService($reqid);
        $encrypt = $data->encrypt(json_encode([
            'data' => [
                'real_balance' => number_format($real_balance->real_balance_mink,2,",","."),
                'fullname' => $real_balance->fullname,
                'isEligible' => $this->isEligible($point->userTgId,10000000),
                'isCreatedWLNFT' => $this->isCreatedWLNFT($point->userTgId),
                'address' => $this->getWLNFT($point->userTgId),
                'userId' => $real_balance->userTgId,
                'point' =>  number_format($point->balance,2,",","."),
                'ratio' =>(float) number_format($ratio->conversion_result,2),
                'amount' => (float) number_format($real_balance->real_balance_mink,2,".","") * 1000000
            ]
        ]));
        $record = [
            'data' => $encrypt,
            'token' => base64_encode(json_encode([
                'reqid' => $reqid,
                'k' => $data->key
            ])),
        ];
        $encrypt = new EncryptionService("3f59d5e982e37a9db70b721fcfa8e062");
        $encryptedData = $encrypt->encrypt(base64_encode(json_encode($record)));
        return Response()->json(['status' => true,'data' => $encryptedData]);
    }

    public function createClaim(Request $req){
        $reqid = $req->header('SecChUaOrigin');
        $transactionId = $req->input('transactionId');
        $status = $req->input('status');
        $max=9000000000;
        $real_balance = DB::table('member_balance_real_token')->where('wdID', $reqid)->first();
        $amount = $real_balance->real_balance_mink > $max ? $max : $real_balance->real_balance_mink;
        $wd = WD::where(['transactionId' => $transactionId]);
        if($wd->count() > 0){
            $update = $wd->update(['status' => $status]);
            if($update){
                $blnc = $real_balance->real_balance_mink - $amount;
                $balance = Balance::where(['wdID' => $reqid])->update(['balance' => $blnc]);
                return Response()->json(['status' => true],200);
            }
            return Response()->json(['status' => false],500);
        }
        else{
            $create = WD::create([
                'userTgId' => $real_balance->userTgId,
                'transactionId' => $transactionId,
                'amount' => $amount,
                'status' => $status
            ]);
            if($create){
                $blnc = $real_balance->real_balance_mink - $amount;
                $balance = Balance::where(['wdID' => $reqid])->update(['balance' => $blnc]);
                return Response()->json(['status' => true],200);
            }
            return Response()->json(['status' => false],500);
        }
    }

    public function getHistory(Request $req) {
        $reqid = $req->header('SecChUaOrigin');
        $real_balance = DB::table('member_balance_real_token')->where('wdID', $reqid)->first();
        $wdData = WD::where(['userTgId' => $real_balance->userTgId])
                  ->select(DB::raw('transactionId as signature'),'amount','status')->get();
        return Response()->json(['status' => true, 'data' => $wdData]);
    }

    public function wd(Request $req,$id){
        $real_balance = DB::table('member_balance_real_token')->where('wdID', $id)->first();
        return Response()->json(['status' => true, 'amount' => $real_balance->real_balance_mink]);
    }

    function isEligible($userTgId, $amount){
        $members = DB::table('members')
             ->where('userTgId', $userTgId)
             ->whereNull('ban')
             ->count();
        if($members > 0){
            $balance = DB::table('member_balance_real_token')
            ->where('userTgId', $userTgId)
            ->where('real_balance_mink', '>=', $amount)
            ->count();
            if($balance > 0){
                return true;
            }
            return false;
        }
        return false;
    }

    public function WLNFT(Request $request){
        $reqid = $request->header('SecChUaOrigin');
        $wallet = $request->header('address');
        $balance = DB::table('balances')->where(['wdID' => $reqid])->first();
        if($this->isEligible($balance->userTgId,10000000)){
            try{
                $create = DB::table('whitelist_nft')->insert([
                    'userTgId' => $balance->userTgId,
                    'walletAddress' => $wallet
                ]);
                if($create){
                    return Response()->json(['status' => true,'message' => "Success"],200,[],JSON_PRETTY_PRINT);
                }
                return Response()->json(['status' => false,'message' => "Failed"],400,[],JSON_PRETTY_PRINT);
            }
            catch(\Exception $e){
                return Response()->json(['status' => false,'message' => $e->getMessage()],400,[],JSON_PRETTY_PRINT);
            }
        }
        return Response()->json(['status' => false,'message' => 'Not Eligible'],400,[],JSON_PRETTY_PRINT);
    }

    function isCreatedWLNFT($userTgId){
        $data = DB::table('whitelist_nft')->where(['userTgId' => $userTgId])->count();
        if($data > 0){
            return true;
        }
        return false;
    }

    function getWLNFT($userTgId){
        $data = DB::table('whitelist_nft')->where(['userTgId' => $userTgId])->first();
        if($data){
            return $data->walletAddress;
        }
        return "";
    }
}
