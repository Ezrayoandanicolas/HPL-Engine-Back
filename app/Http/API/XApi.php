<?php

namespace App\Http\API;

use Illuminate\Support\Facades\Log;

class XApi
{
    public $agen = "blackhub2";
    public $token = "59ae72410929f87de199af1a78ac0a94";
    public $secret_key = "be3e396b8c20800a07122d796384eebf";
    public $url = "https://x-api.asia/api";

    public function create($username)
    {
        $param = [
            'method' => 'user_create',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function userbalance($username)
    {
        $param = [
            'method' => 'money_info',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function agentbalance()
    {
        $param = [
            'method' => 'money_info',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function allUsersBalance()
    {
        $param = [
            'method' => 'money_info',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'all_users' => true,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function deposit($username, $amount, $agentSign = null)
    {
        $param = [
            'method' => 'user_deposit',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
            'amount' => $amount,
        ];

        if ($agentSign) {
            $param['agent_sign'] = $agentSign;
        }

        return $this->xapi_connect($this->url, $param);
    }

    public function withdraw($username, $amount, $agentSign = null)
    {
        $param = [
            'method' => 'user_withdraw',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
            'amount' => $amount,
        ];

        if ($agentSign) {
            $param['agent_sign'] = $agentSign;
        }

        return $this->xapi_connect($this->url, $param);
    }

    public function resetBalance()
    {
        $param = [
            'method' => 'user_withdraw_reset',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'all_users' => true,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function resetUserBalance($username)
    {
        $param = [
            'method' => 'user_withdraw_reset',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function transferStatus($username, $agentSign)
    {
        $param = [
            'method' => 'transfer_status',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
            'agent_sign' => $agentSign,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function gamelist($provider)
    {
        $param = [
            'method' => 'game_list',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'provider_code' => $provider,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function providerlist()
    {
        $param = [
            'method' => 'provider_list',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function callPlayer()
    {
        $param = [
            'method' => 'call_players',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function callList($provider, $gamecode)
    {
        $param = [
            'method' => 'call_list',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'provider_code' => $provider,
            'game_code' => $gamecode,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function callApply($provider, $gamecode, $username, $win_amount, $call_type)
    {
        $callTypeMap = [
            'normal' => 1,
            'buy' => 2,
        ];

        $mappedCallType = $callTypeMap[$call_type] ?? 1;

        $param = [
            'method' => 'call_apply',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'provider_code' => $provider,
            'game_code' => $gamecode,
            'user_code' => $username,
            'call_rtp' => (float) $win_amount,
            'call_type' => $mappedCallType,
        ];

        Log::info('=== XAPI CALL APPLY REQUEST ===', $param);

        $response = $this->xapi_connect($this->url, $param);

        Log::info('=== XAPI CALL APPLY RESPONSE ===', [
            'response' => $response
        ]);

        return $response;
    }

    public function opengame($username, $gamecode, $game_provider)
    {
        $param = [
            'method' => 'game_launch',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
            'game_code' => $gamecode,
            'provider_code' => $game_provider,
            'lang' => 'id',
            'currency' => 'IDR',
        ];

        Log::info('================ XAPI GAME LAUNCH REQUEST ================');
        Log::info($param);

        $response = $this->xapi_connect($this->url, $param);

        Log::info('================ XAPI GAME LAUNCH RESPONSE ================');
        Log::info([
            'response' => $response
        ]);

        return $response;
    }

    public function callCancel($callId)
    {
        $param = [
            'method' => 'call_cancel',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'call_id' => (int) $callId,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function callHistory($offset = 0, $limit = 100)
    {
        $param = [
            'method' => 'call_history',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'offset' => (int) $offset,
            'limit' => (int) $limit,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function controlUserRtp($provider, $username, $rtp)
    {
        $param = [
            'method' => 'control_rtp',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'provider_code' => $provider,
            'user_code' => $username,
            'rtp' => (int) $rtp,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function controlUsersRtp(array $userCodes, $rtp)
    {
        $param = [
            'method' => 'control_users_rtp',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_codes' => json_encode($userCodes),
            'rtp' => (int) $rtp,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function inGameHistory($username, $providerCode, $gameCode)
    {
        $param = [
            'method' => 'get_game_history',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
            'provider_code' => $providerCode,
            'game_code' => $gameCode,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    public function historyPlay($username, $type, $start, $end, $page, $perpage)
    {
        $param = [
            'method' => 'get_game_log',
            'agent_code' => $this->agen,
            'agent_token' => $this->token,
            'user_code' => $username,
            'game_type' => $type,
            'start' => $start,
            'end' => $end,
            'page' => $page,
            'perPage' => $perpage,
        ];

        return $this->xapi_connect($this->url, $param);
    }

    private function xapi_connect($url, $postArray)
    {
        $jsonData = json_encode($postArray);
        $headerArray = ['Content-Type: application/json'];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $res = curl_exec($ch);

        if (curl_errno($ch)) {
            Log::error('XAPI CURL ERROR', [
                'error' => curl_error($ch)
            ]);
        }

        curl_close($ch);

        return $res;
    }
}