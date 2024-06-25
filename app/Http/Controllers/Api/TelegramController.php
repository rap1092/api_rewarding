<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class TelegramController extends Controller
{
    private $botToken = '7206831431:AAEq6iw0lwkM5zaWEpr6eljdezVC5Hv62Lk';
    private $chatId = '@MinkArmyIndia'; // ID grup Telegram

    public function kickMembers(Request $request)
    {
        $data = DB::table('members')
            ->whereNotIn('userTgId', ['1879724579'])
            ->whereNotIn('country', ['India'])
            ->select('userTgId')->get();
        $userIds = (array) $data->pluck('userTgId');
        if (empty($userIds) || !is_array($userIds)) {
            return response()->json(['error' => 'Invalid user IDs'], 400);
        }

        foreach ($userIds as $userId) {
            $result = $this->kickMember($userId);

            if ($result['ok'] === false) {
                // Log atau lakukan sesuatu dengan error
                return response()->json(['error' => 'Failed to kick user: ' . $result['description']], 500);
            }
        }

        return response()->json(['message' => 'Users kicked successfully']);
    }

    private function kickMember($userId)
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/kickChatMember";

        $client = new Client();
        $response = $client->post($url, [
            'json' => [
                'chat_id' => $this->chatId,
                'user_id' => $userId,
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
