<?php

namespace App\Http\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    public function postRequest($url='',$content)
    {
        $client = new \GuzzleHttp\Client();
        $promise = $client->postAsync($url, [
            'headers' => [
                'Content-Type' => "application/json",
                'Accept' => "application/json",
                'Authorization' => 'Zoho-oauthtoken ' . config('ZOHO_ACCESS_TOKEN'),
            ],
            'json' => $content,
        ]);
        
        $responseData = (object) [];
        $promise->then(
            function ($response) use(&$responseData){
                $responseData = json_decode($response->getBody()->getContents());
            },
            function (Exception $e) {
                Log::error('Zoho Post error, ' . $e->getMessage());
            }
        );
        $promise->wait();
        return $responseData;
    }

    public function getRequest($url='',$params)
    {
        $client = new \GuzzleHttp\Client();
        $promise = $client->getAsync($url, [
            'headers' => [
                'Content-Type' => "application/json",
                'Accept' => "application/json",
                'Authorization' => 'Zoho-oauthtoken ' . config('ZOHO_ACCESS_TOKEN'),
            ],
            'query' => $params,
        ]);
        
        $responseData = (object) [];
        $promise->then(
            function ($response) use(&$responseData){
                $responseData = json_decode($response->getBody()->getContents());
            },
            function (Exception $e) {
                Log::error('Zoho Post error, ' . $e->getMessage());
            }
        );
        $promise->wait();
        return $responseData;
    }

    public function refreshToken()
    {
        $url = config('ZOHO_REFRESH_TOKEN_URL');
        $query = [
            'client_id' => config('ZOHO_CLIENT_ID'),
            'client_secret' => config('ZOHO_CLIENT_SECRET'),
            'grant_type' => 'refresh_token',
            'refresh_token' => config('ZOHO_REFRESH_TOKEN'),
        ];

        $client = new \GuzzleHttp\Client();
        $promise = $client->postAsync($url, [
            'headers' => [
                'Content-Type' => "application/json",
                'Accept' => "application/json",
            ],
            'query' => $query,
        ]);
        
        $responseData = (object) [];
        $promise->then(
            function ($response) use(&$responseData){
                $responseData = json_decode($response->getBody()->getContents());
            },
            function (Exception $e) {
                Log::error('Zoho Post error, ' . $e->getMessage());
            }
        );
        $promise->wait();
        return $responseData;
    }

    public function getContacts($params=[])
    {
        $response = $this->getRequest(config('ZOHO_BASE_URL').'/contacts', [
            'organization_id' => config('ZOHO_ORGANIZATION_ID'),
        ] + $params);

        return $response;
    }
}
