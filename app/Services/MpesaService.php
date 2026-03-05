<?php

namespace App\Services;

use App\Models\MpesaConfig;
use App\Models\MpesaTransaction;
use App\Models\Purchase;
use App\Models\FloatRequest;
use App\Models\Expense;
use Safaricom\Mpesa\Mpesa; // Correct class for this package
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $config;

    public function __construct()
    {
        $this->config = MpesaConfig::where('is_active', true)->first();
        
        if ($this->config) {
            // This package expects these specific config keys
            config([
                'mpesa.env'             => $this->config->env, // 'sandbox' or 'live'
                'mpesa.consumer_key'    => $this->config->consumer_key,
                'mpesa.consumer_secret' => $this->config->consumer_secret,
                'mpesa.passkey'         => $this->config->passkey,
                'mpesa.shortcode'       => $this->config->shortcode,
            ]);
        }
    }

    public function processPayment($model)
    {
        if (!$this->config) {
            return ['status' => false, 'message' => 'M-Pesa configuration inactive.'];
        }

        // 1. Determine Phone
        $phone = match (get_class($model)) {
            Purchase::class     => $model->vendor?->phone ?? $model->vendor_phone,
            FloatRequest::class => $model->user?->phone ?? $model->phone_number,
            Expense::class      => $model->phone_number,
            default             => null,
        };

        if (!$phone) return ['status' => false, 'message' => 'No valid phone found.'];

        // 2. Identify Amount (Net amount for Purchases)
        $type = strtolower(class_basename($model));
        $amount = ($model instanceof Purchase) 
            ? ($model->total_amount - ($model->transaction_fee ?? 0))
            : ($model->total_amount ?? $model->amount);

        $reference = strtoupper(substr($type, 0, 3)) . '_' . $model->id;
        $description = "Payment for $type #$model->id";
        
        return $this->initiateStkPush($model, $this->formatPhoneNumber($phone), $reference, $description, $amount, $type);
    }

    public function initiateStkPush($model, $phoneNumber, $accountRef, $description, $amount, $type)
    {
        try {
            // This package requires the STK push to be called through the 'STK' method 
            // which returns an instance of the LNM (Lipa Na Mpesa) class.
            $mpesa = new \Safaricom\Mpesa\Mpesa();
            
            $callbackUrl = $this->config->callback_url ?: config('app.url') . '/api/mpesa/callback';
            
            // In safaricom/mpesa, STK pushes use the STK() method
            $response = $mpesa->STKPush(
                $this->config->shortcode,     // BusinessShortCode
                $this->config->passkey,       // LipaNaMpesaPasskey
                'CustomerPayBillOnline',      // TransactionType
                $amount,                      // Amount
                $phoneNumber,                 // PartyA
                $this->config->shortcode,     // PartyB
                $phoneNumber,                 // PhoneNumber
                $callbackUrl,                 // CallBackURL
                $accountRef,                  // AccountReference
                $description                  // TransactionDesc
            );

            // Handle the JSON response
            $resData = json_decode($response, true);
            $checkoutId = $resData['CheckoutRequestID'] ?? null;

            if ($checkoutId) {
                MpesaTransaction::create([
                    'transactionable_id'   => $model->id,
                    'transactionable_type' => get_class($model),
                    'type'                 => $type,
                    'checkout_request_id'  => $checkoutId,
                    'amount'               => $amount,
                    'phone_number'         => $phoneNumber,
                    'status'               => 'requested',
                ]);

                $model->update([
                    'mpesa_checkout_id' => $checkoutId,
                    'mpesa_phone'       => $phoneNumber,
                ]);

                return ['status' => true, 'message' => 'STK Push sent!'];
            }

            Log::error("M-Pesa API Response: " . $response);
            return ['status' => false, 'message' => $resData['errorMessage'] ?? 'STK Push Failed.'];

        } catch (\Exception $e) {
            Log::error("M-Pesa Exception: " . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    protected function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone); 
        if (str_starts_with($phone, '0')) return '254' . substr($phone, 1);
        if (str_starts_with($phone, '7') || str_starts_with($phone, '1')) return '254' . $phone;
        return $phone; 
    }
}