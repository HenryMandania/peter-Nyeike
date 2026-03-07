<?php

namespace App\Services;

use Exception;
use App\Models\Purchase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MpesaService
{
    public function processVendorPayment(Purchase $purchase)
    {
        Log::info('Initiating manual B2C payment', ['purchase_id' => $purchase->id]);

        $vendor = $purchase->vendor;
        $phone = $this->formatPhone($vendor->phone ?? '');
        $amount = (float) $purchase->total_amount;

        // 1. Get Access Token
        $token = $this->getAccessToken();

        // 2. Prepare Payload
        // Note: Safaricom B2C requires specific fields; if one is missing/wrong, it returns "Invalid ResultURL"
        $payload = [
            "InitiatorName" => config('mpesa.initiator_name'),
            "SecurityCredential" => $this->getSecurityCredential(),
            "CommandID" => "BusinessPayment",
            "Amount" => $amount,
            "PartyA" => config('mpesa.shortcode'),
            "PartyB" => $phone,
            "Remarks" => "Payment for P#{$purchase->id}",
            "QueueTimeOutURL" => config('mpesa.b2c_timeout_url'),
            "ResultURL" => config('mpesa.b2c_result_url'),
            "Occasion" => "Purchase Payment"
        ];

        // 3. Execute Request
        $response = Http::withToken($token)
            ->post('https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest', $payload);

        $result = $response->json();
        Log::debug('M-Pesa API Response', ['result' => $result]);

        if ($response->failed() || (isset($result['ResponseCode']) && $result['ResponseCode'] !== '0')) {
            throw new Exception("M-Pesa API Error: " . ($result['ResponseDescription'] ?? 'Unknown error'));
        }

        if (isset($result['ConversationID'])) {
            $purchase->update(['conversation_id' => $result['ConversationID']]);
        }

        return $result;
    }

    private function getAccessToken()
    {
        $response = Http::withBasicAuth(config('mpesa.consumer_key'), config('mpesa.consumer_secret'))
            ->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
        
        return $response->json('access_token');
    }

    private function getSecurityCredential()
    {
        // Must be the public certificate provided in the M-Pesa Developer portal
        $pubKey = file_get_contents(storage_path('app/cert/sandbox_cert.cer'));
        openssl_public_encrypt(config('mpesa.initiator_password'), $encrypted, $pubKey, OPENSSL_PKCS1_PADDING);
        return base64_encode($encrypted);
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return str_starts_with($phone, '254') ? $phone : '254' . ltrim($phone, '0');
    }
}