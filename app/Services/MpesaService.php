<?php

namespace App\Services;

use Exception;
use Akika\LaravelMpesa\Mpesa;
use App\Models\Purchase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class MpesaService
{
    protected $mpesa;

    public function __construct()
    {
        $this->mpesa = new Mpesa();
    }

    public function processVendorPayment(Purchase $purchase)
    {
        try {
            $shortcode = Config::get('mpesa.shortcode'); // your sender shortcode
            $resultUrl = Config::get('mpesa.result_url');
            $timeoutUrl = Config::get('mpesa.queue_timeout_url'); // or config('mpesa.timeout_url')

            $phone = $this->formatPhone($purchase->vendor_phone ?? '');

            if ($purchase->vendor_type === 'phone' && $phone) {
                // B2C to phone (Business to Customer)
                $response = $this->mpesa->b2cTransaction(
                    null,                               // originatorConversationId (null for new)
                    'BusinessPayment',                  // commandID (or SalaryPayment, PromotionPayment)
                    $phone,                             // msisdn / recipient phone
                    $purchase->total_amount,            // amount
                    "Payment for Purchase #{$purchase->id}", // remarks
                    ''                                  // occasion (optional)
                );
            } else {
                // B2B to PayBill/Till (choose based on vendor type)
                // For PayBill → use b2bPaybill
                // For Till/BuyGoods → use b2bBuyGoods
                // Here assuming PayBill; adjust if vendor has a type field for BuyGoods
                $response = $this->mpesa->b2bPaybill(
                    $purchase->vendor_paybill,          // destShortcode (receiver)
                    $purchase->total_amount,            // amount
                    "Payment for Purchase #{$purchase->id}", // remarks
                    (string) $purchase->id,             // accountNumber (up to 13 chars)
                    $resultUrl,                         // resultUrl
                    $timeoutUrl,                        // timeoutUrl
                    null                                // requester (optional)
                );
            }

            // Package returns Guzzle Response object
            $result = $response->json();  // Get JSON body as array

            Log::info('M-Pesa Vendor Payment Initiated', [
                'purchase_id' => $purchase->id,
                'type' => $purchase->vendor_type,
                'response' => $result
            ]);

            if (isset($result['ConversationID'])) {
                $purchase->update(['conversation_id' => $result['ConversationID']]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('M-Pesa Vendor Payment Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function formatPhone($phone)
    {
        if (!$phone) return null;
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }
        return $phone;
    }
}