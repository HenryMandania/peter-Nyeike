<?php

namespace App\Services;

use App\Models\MpesaConfig;
use App\Models\MpesaTransaction;
use App\Models\Purchase;
use App\Models\FloatRequest;
use App\Models\Expense;
use Safaricom\Mpesa\Mpesa;  
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $config;

    public function __construct()
    {
        $this->config = MpesaConfig::where('is_active', true)->first();
        
        if ($this->config) {
            $env = strtolower($this->config->env); 
    
            // Inject EVERYTHING from the database into the environment memory
            putenv("MPESA_ENV={$env}");
            putenv("MPESA_CONSUMER_KEY={$this->config->consumer_key}");
            putenv("MPESA_CONSUMER_SECRET={$this->config->consumer_secret}");
            putenv("MPESA_PASSKEY={$this->config->passkey}");
            putenv("MPESA_SHORTCODE={$this->config->shortcode}");
            
            // Also keep the Laravel config for your own use
            config([
                'mpesa.env' => $env,
                'mpesa.shortcode' => $this->config->shortcode,
            ]);
        }
    }

    public function processPayment($model)
{
    if (!$this->config) {
        return [
            'status' => false,
            'message' => 'M-Pesa configuration inactive.'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate STK requests
    |--------------------------------------------------------------------------
    */

    $existing = MpesaTransaction::where('transactionable_id', $model->id)
        ->where('transactionable_type', get_class($model))
        ->whereIn('status', ['requested', 'processing'])
        ->latest()
        ->first();

    if ($existing) {
        return [
            'status' => false,
            'message' => 'A payment request is already pending for this transaction.'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent retry spam (cooldown)
    |--------------------------------------------------------------------------
    */

    $lastAttempt = MpesaTransaction::where('transactionable_id', $model->id)
        ->where('transactionable_type', get_class($model))
        ->latest()
        ->first();

    if ($lastAttempt && $lastAttempt->created_at->diffInSeconds(now()) < 10) {
        return [
            'status' => false,
            'message' => 'Please wait a few seconds before retrying payment.'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Determine phone number
    |--------------------------------------------------------------------------
    */

    $phone = match (get_class($model)) {
        Purchase::class     => $model->vendor?->phone ?? $model->vendor_phone,
        FloatRequest::class => $model->user?->phone ?? $model->phone_number,
        Expense::class      => $model->phone_number,
        default             => null,
    };

    if (!$phone) {
        return [
            'status' => false,
            'message' => 'No valid phone number found.'
        ];
    }

    $phone = $this->formatPhoneNumber($phone);

    /*
    |--------------------------------------------------------------------------
    | Determine payment amount
    |--------------------------------------------------------------------------
    */

    $type = strtolower(class_basename($model));

    $amount = ($model instanceof Purchase)
        ? ($model->total_amount - ($model->transaction_fee ?? 0))
        : ($model->total_amount ?? $model->amount);

    if (!$amount || $amount <= 0) {
        return [
            'status' => false,
            'message' => 'Invalid transaction amount.'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent paying already paid record
    |--------------------------------------------------------------------------
    */

    if (isset($model->status) && $model->status === 'paid') {
        return [
            'status' => false,
            'message' => 'This transaction has already been paid.'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Generate reference
    |--------------------------------------------------------------------------
    */

    $reference = strtoupper(substr($type, 0, 3)) . '_' . $model->id;

    $description = "Payment for {$type} #{$model->id}";

    /*
    |--------------------------------------------------------------------------
    | Send STK push
    |--------------------------------------------------------------------------
    */

    try {

        return $this->initiateStkPush(
            $model,
            $phone,
            $reference,
            $description,
            $amount,
            $type
        );

    } catch (\Throwable $e) {

        Log::error("MPESA PAYMENT ERROR: " . $e->getMessage());

        return [
            'status' => false,
            'message' => 'Failed to initiate M-Pesa payment.'
        ];
    }
}
    public function initiateStkPush($model, $phoneNumber, $accountRef, $description, $amount, $type)
    {
        try {
            $mpesa = new \Safaricom\Mpesa\Mpesa();
            
            $callbackUrl = $this->config->callback_url ?: config('app.url') . '/api/mpesa/callback';
            
            // This version of the package uses STKPushSimulation
            // Parameters: $BusinessShortCode, $LipaNaMpesaPasskey, $TransactionType, $Amount, $PartyA, $PartyB, $PhoneNumber, $CallBackURL, $AccountReference, $TransactionDesc, $Remark
            $response = $mpesa->STKPushSimulation(
                $this->config->shortcode,     // BusinessShortCode
                $this->config->passkey,       // LipaNaMpesaPasskey
                'CustomerPayBillOnline',      // TransactionType
                $amount,                      // Amount
                $phoneNumber,                 // PartyA
                $this->config->shortcode,     // PartyB
                $phoneNumber,                 // PhoneNumber
                $callbackUrl,                 // CallBackURL
                $accountRef,                  // AccountReference
                $description,                 // TransactionDesc
                'Payment'                     // Remark (Required by this package)
            );

            // This package returns a JSON string, so we decode it
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

                return ['status' => true, 'message' => 'STK Push sent to ' . $phoneNumber];
            }

            Log::error("M-Pesa API Error: " . $response);
            return ['status' => false, 'message' => $resData['errorMessage'] ?? 'Safaricom Error. Check Logs.'];

        } catch (\Exception $e) {
            Log::error("M-Pesa Exception: " . $e->getMessage());
            return ['status' => false, 'message' => "M-Pesa Service Error."];
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