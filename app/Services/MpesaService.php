<?php

namespace App\Services;

use App\Models\MpesaConfig;
use App\Models\MpesaTransaction;
use App\Models\Purchase;
use App\Models\FloatRequest;
use App\Models\Expense;
use Safaricom\Mpesa\Mpesa;
use Illuminate\Support\Facades\DB;

class MpesaService
{
    protected $config;
    protected $maxRetries = 3;

    public function __construct()
    {
        $this->config = MpesaConfig::where('is_active', true)->first();
    }

    /**
     * Extract phone number from different model types
     */
    private function extractPhoneNumber($model)
    {
        return match (get_class($model)) {
            Purchase::class => $model->vendor?->phone ?? $model->vendor_phone ?? null,
            FloatRequest::class => $model->user?->phone ?? $model->phone_number ?? null,
            Expense::class => $model->phone_number ?? null,
            default => null,
        };
    }

    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            return '254' . substr($phone, 1);
        }
        
        if (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            return '254' . $phone;
        }
        
        return $phone;
    }

    /**
     * Process payment and prevent race conditions.
     */
    public function processPayment($model)
    {
        if (!$this->config) {
            return ['status' => false, 'message' => 'M-Pesa configuration inactive.'];
        }

        return DB::transaction(function () use ($model) {
            // Check for existing pending transaction
            $existing = MpesaTransaction::where('transactionable_id', $model->id)
                ->where('transactionable_type', get_class($model))
                ->whereIn('status', ['requested', 'processing'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['status' => false, 'message' => 'Payment request already pending.'];
            }

            $phone = $this->extractPhoneNumber($model);
            if (!$phone) {
                return ['status' => false, 'message' => 'Invalid phone number.'];
            }
            
            $phone = $this->formatPhoneNumber($phone);
            
            // Calculate amount
            $amount = match (true) {
                $model instanceof Purchase => $model->total_amount - ($model->transaction_fee ?? 0),
                default => $model->total_amount ?? $model->amount ?? null,
            };

            if (!$amount || $amount <= 0) {
                return ['status' => false, 'message' => 'Invalid amount.'];
            }

            return $this->initiateStkPush($model, $phone, $amount);
        });
    }

    /**
     * Initiate STK Push with retry logic
     */
    public function initiateStkPush($model, $phoneNumber, $amount, $retryCount = 0)
    {
        try {
            // Set environment variables
            putenv("MPESA_ENV=" . strtolower($this->config->env));
            putenv("MPESA_CONSUMER_KEY=" . $this->config->consumer_key);
            putenv("MPESA_CONSUMER_SECRET=" . $this->config->consumer_secret);
            putenv("MPESA_PASSKEY=" . $this->config->passkey);
            putenv("MPESA_SHORTCODE=" . $this->config->shortcode);

            $mpesa = new Mpesa();
            $callbackUrl = $this->config->callback_url ?: config('app.url') . '/api/mpesa/callback';
            $reference = strtoupper(substr(class_basename($model), 0, 3)) . '_' . $model->id;

            $response = $mpesa->STKPushSimulation(
                $this->config->shortcode,
                $this->config->passkey,
                'CustomerPayBillOnline',
                $amount,
                $phoneNumber,
                $this->config->shortcode,
                $phoneNumber,
                $callbackUrl,
                $reference,
                "Payment for " . class_basename($model),
                'Payment'
            );

            if ($response === null) {
                throw new \Exception("Empty response from M-Pesa API");
            }

            $resData = json_decode($response, true);
            
            if (!isset($resData['CheckoutRequestID'])) {
                $errorMessage = $resData['errorMessage'] ?? 'STK push failed';
                
                if ($retryCount < $this->maxRetries) {
                    sleep(pow(2, $retryCount) * 5);
                    return $this->initiateStkPush($model, $phoneNumber, $amount, $retryCount + 1);
                }
                
                if ($model instanceof Purchase) {
                    $model->update([
                        'mpesa_error_message' => $errorMessage,
                        'payment_status' => 'failed'
                    ]);
                }
                
                return ['status' => false, 'message' => $errorMessage];
            }

            // Determine transaction type
            $type = match (get_class($model)) {
                Purchase::class => 'purchase',
                FloatRequest::class => 'float_request',
                Expense::class => 'expense',
                default => 'unknown'
            };

            // Create transaction record
            MpesaTransaction::create([
                'transactionable_id'   => $model->id,
                'transactionable_type' => get_class($model),
                'type'                 => $type,
                'checkout_request_id'  => $resData['CheckoutRequestID'],
                'amount'               => $amount,
                'phone_number'         => $phoneNumber,
                'status'               => 'requested',
            ]);

            // Update purchase record
            if ($model instanceof Purchase) {
                $model->update([
                    'mpesa_checkout_id' => $resData['CheckoutRequestID'],
                    'mpesa_phone' => $phoneNumber,
                    'payment_status' => 'processing',
                    'mpesa_error_message' => null
                ]);
            }

            return [
                'status' => true, 
                'message' => 'STK Push initiated.', 
                'CheckoutRequestID' => $resData['CheckoutRequestID']
            ];

        } catch (\Exception $e) {
            if ($retryCount < $this->maxRetries) {
                sleep(pow(2, $retryCount) * 5);
                return $this->initiateStkPush($model, $phoneNumber, $amount, $retryCount + 1);
            }
            
            if ($model instanceof Purchase) {
                $model->update([
                    'mpesa_error_message' => $e->getMessage(),
                    'payment_status' => 'failed'
                ]);
            }
            
            return ['status' => false, 'message' => 'Payment service temporarily unavailable.'];
        }
    }
}