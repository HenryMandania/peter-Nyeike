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
        Log::info('Starting secure B2C payment', ['purchase_id' => $purchase->id]);

        $vendor = $purchase->vendor;
        if (!$vendor || !$vendor->phone) {
            throw new Exception("Vendor phone number missing.");
        }

        $token = $this->getAccessToken();

        $payload = [
            "InitiatorName"      => config('mpesa.initiator_name'),
            "SecurityCredential" => $this->getSecurityCredential(),
            "CommandID"          => "SalaryPayment", // Change this from "BusinessPayment"
            "Amount"             => (float) $purchase->total_amount,
            "PartyA"             => config('mpesa.shortcode'),
            "PartyB"             => $this->formatPhone($vendor->phone), // Must be a valid MSISDN
            "Remarks"            => "Payment for P#{$purchase->id}",
            "QueueTimeOutURL"    => config('mpesa.b2c_timeout_url'),
            "ResultURL"          => config('mpesa.b2c_result_url'),
            "Occasion"           => "Purchase Payment"
        ];

        $response = Http::withToken($token)
            ->post('https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest', $payload);

        $result = $response->json();
        
        if ($response->failed() || (isset($result['ResponseCode']) && $result['ResponseCode'] !== '0')) {
            throw new Exception("M-Pesa API Error: " . ($result['ResponseDescription'] ?? 'Unknown error'));
        }

        $purchase->update(['conversation_id' => $result['ConversationID'] ?? null]);

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
        $path = storage_path('app/cert/sandbox_cert.cer');
        if (!file_exists($path)) {
            throw new Exception("Encryption certificate not found at: {$path}. Download from M-Pesa Portal.");
        }

        $pubKey = file_get_contents($path);
        $encrypted = '';
        
        if (openssl_public_encrypt(config('mpesa.initiator_password'), $encrypted, $pubKey, OPENSSL_PKCS1_PADDING)) {
            return base64_encode($encrypted);
        }
        
        throw new Exception("Failed to encrypt Security Credential.");
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return str_starts_with($phone, '254') ? $phone : '254' . ltrim($phone, '0');
    }
}