<?php

namespace App\Http\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    public function postRequest($url='',$query=[],$content=[])
    {
        $client = new \GuzzleHttp\Client();
        $promise = $client->postAsync($url, [
            'headers' => [
                'Content-Type' => "application/json",
                'Accept' => "application/json",
                'Authorization' => 'Zoho-oauthtoken ' . config('ZOHO_ACCESS_TOKEN'),
            ],
            'query' => $query,
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

    public function getRequest($url='',$query=[])
    {
        $client = new \GuzzleHttp\Client();
        $promise = $client->getAsync($url, [
            'headers' => [
                'Content-Type' => "application/json",
                'Accept' => "application/json",
                'Authorization' => 'Zoho-oauthtoken ' . config('ZOHO_ACCESS_TOKEN'),
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

    public function getSalesPerson($params=[])
    {
        $response = $this->getRequest(config('ZOHO_BASE_URL').'/salespersons', [
            'organization_id' => config('ZOHO_ORGANIZATION_ID'),
        ] + $params);

        return $response;
    }

    public function getItems($params=[])
    {
        $response = $this->getRequest(config('ZOHO_BASE_URL').'/items', [
            'organization_id' => config('ZOHO_ORGANIZATION_ID'),
        ] + $params);

        return $response;
    }

    public function getItem($itemId)
    {
        $response = $this->getRequest(config('ZOHO_BASE_URL')."/items/{$itemId}", [
            'organization_id' => config('ZOHO_ORGANIZATION_ID'),
        ]);

        return $response;
    }

    public function getCompositeItem($compositeItemId)
    {
        $response = $this->getRequest(config('ZOHO_BASE_URL')."/compositeitems/{$compositeItemId}", [
            'organization_id' => config('ZOHO_ORGANIZATION_ID'),
        ]);

        return $response;
    }

    public function getCompositeItems($params=[])
    {
        $response = $this->getRequest(config('ZOHO_BASE_URL').'/compositeitems', [
            'organization_id' => config('ZOHO_ORGANIZATION_ID'),
        ] + $params);

        return $response;
    }

    public function paymentTerms($params=[])
    {
        $response = $this->getRequest(config('ZOHO_BASE_URL').'/settings/paymentterms', [
            'organization_id' => config('ZOHO_ORGANIZATION_ID'),
        ] + $params);

        return $response;
    }

    public function postInvoice($model=[])
    {
        $invoiceData = array_replace(config('zohotemplate.invoice'), [
            'customer_id' => '6519309000000099242',
            'date' => date('Y-m-d'),
            'due_date' => date('Y-m-d'),
            "payment_terms" => 0, 
            "payment_terms_label" => "Due on Receipt",
            'salesperson_name' => 'Caroline Wakio',
            "is_discount_before_tax" => true,
            "discount_type" => "item_level",
            "is_inclusive_tax" => false,
            "exchange_rate" => 1,
            "line_items" => [
                // [
                //     "item_id" => 6519309000000099166,
                //     "name" => "Ethernet Cable 5M",
                //     "description" => "Ethernet Cable 5M",
                //     "item_order" => 1,
                //     "bcy_rate" => 1,
                //     "rate" => 2500,
                //     "quantity" => 10,
                //     "unit" => "mtr",
                //     "discount" => 0,
                //     "item_total" => 25000,
                // ]
                [
                    "item_id" => 6519309000000107186,
                    "name" => "Business Suite Setup",
                    "description" => "Business Suite Setup",
                    "item_order" => 1,
                    "bcy_rate" => 1,
                    "rate" => 301000,
                    "quantity" => 1,
                    "unit" => "mtr",
                    "discount" => 0,
                    "item_total" => 301000,
                ]
            ],
            'sub_total' => 301000,
            'tax_total' => 0,
            'total' => 301000,
            "notes" => "Looking forward for your business.",
            "terms" => "Terms & Conditions apply",
            "shipping_charge" => 0,
            "adjustment" => 0,
        ]);
        foreach ($invoiceData as $key => $val) {
            if ($val === '' || (is_array($val) && !$val)) {
                unset($invoiceData[$key]);
            }
        }
        // dd($invoiceData);

        $response = $this->postRequest(
            config('ZOHO_BASE_URL').'/invoices', 
            ['organization_id' => config('ZOHO_ORGANIZATION_ID')],
            $invoiceData
        );

        return $response;
    }

    public function markSentInvoice($invoiceId)
    {
        $response = $this->postRequest(
            config('ZOHO_BASE_URL')."/invoices/{$invoiceId}/status/sent", 
            ['organization_id' => config('ZOHO_ORGANIZATION_ID')]
        );
        return $response;
    }

    public function postInventoryAdjustment($adjustment=[])
    {
        // $adjustment = [
        //   "reason" => "Inventory Revaluation",
        //   "date" => date('Y-m-d'),
        //   "warehouse_id" => "6519309000000093087", // dynamic
        //   "line_items" => [
        //     [
        //       "item_id" => "6519309000000099166",
        //       "quantity_adjusted" => -5,
        //     ],
        //   ]
        // ];

        $response = $this->postRequest(
            config('ZOHO_BASE_URL')."/inventoryadjustments", 
            ['organization_id' => config('ZOHO_ORGANIZATION_ID')],
            $adjustment
        );

        return $response;
    }
}
