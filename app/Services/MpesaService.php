<?php

namespace App\Services;

use App\Models\MpesaConfig;
use App\Models\MpesaTransaction;
use App\Models\Purchase;
use App\Models\FloatRequest;
use App\Models\Expense;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class MpesaService
{
    protected $config;
    // Strictly define allowed statuses to match your database ENUM
    protected $validStatuses = ['requested', 'completed', 'failed', 'cancelled'];

    public function __construct()
    {
        $this->config = MpesaConfig::where('is_active', true)->first();
    }

    public function processPayment($model)
    {
        if (!$this->config) {
            return ['status' => false, 'message' => 'M-Pesa configuration inactive.'];
        }

        $phone = $this->resolvePhone($model);
        if (!$phone) return ['status' => false, 'message' => 'No valid phone found.'];

        $type = strtolower(class_basename($model));
        $amount = ($model instanceof Purchase) 
            ? ($model->total_amount - ($model->transaction_fee ?? 0))
            : ($model->total_amount ?? $model->amount);

        $reference = strtoupper(substr($type, 0, 3)) . '_' . $model->id;
        
        return $this->initiateStkPush($model, $this->formatPhoneNumber($phone), $reference, $amount, $type);
    }

    public function initiateStkPush($model, $phoneNumber, $accountRef, $amount, $type)
    {
        return DB::transaction(function () use ($model, $phoneNumber, $accountRef, $amount, $type) {
            try {
                $mpesa = new \Safaricom\Mpesa\Mpesa();
                
                $response = $mpesa->STKPushSimulation(
                    $this->config->shortcode, $this->config->passkey, 'CustomerPayBillOnline',
                    $amount, $phoneNumber, $this->config->shortcode, $phoneNumber,
                    $this->config->callback_url ?? config('app.url') . '/api/mpesa/callback',
                    $accountRef, "Payment for " . class_basename($model), 'Payment'
                );

                $resData = json_decode($response, true);
                
                if (isset($resData['CheckoutRequestID'])) {
                    // Force status to be valid or default to 'failed'
                    $initialStatus = 'requested';
                    $finalStatus = in_array($initialStatus, $this->validStatuses) ? $initialStatus : 'failed';

                    MpesaTransaction::create([
                        'transactionable_id'   => $model->id,
                        'transactionable_type' => get_class($model),
                        'type'                 => $type,
                        'checkout_request_id'  => $resData['CheckoutRequestID'],
                        'amount'               => $amount,
                        'phone_number'         => $phoneNumber,
                        'status'               => $finalStatus,
                    ]);

                    return ['status' => true, 'message' => 'STK Push sent to ' . $phoneNumber];
                }

                throw new Exception($resData['errorMessage'] ?? 'Safaricom API Error.');

            } catch (Exception $e) {
                Log::error("M-Pesa Transaction Error: " . $e->getMessage());
                return ['status' => false, 'message' => "M-Pesa Service Error."];
            }
        });
    }

    private function resolvePhone($model) {
        return match (get_class($model)) {
            Purchase::class     => $model->vendor?->phone ?? $model->vendor_phone,
            FloatRequest::class => $model->user?->phone ?? $model->phone_number,
            Expense::class      => $model->phone_number,
            default             => null,
        };
    }

    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) return '254' . substr($phone, 1);
        if (str_starts_with($phone, '7') || str_starts_with($phone, '1')) return '254' . $phone;
        return $phone;
    }
}