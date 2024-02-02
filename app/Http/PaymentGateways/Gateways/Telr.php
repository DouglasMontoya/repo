<?php

namespace App\Http\PaymentGateways\Gateways;


use Exception;
use App\Enums\Activity;
use App\Enums\GatewayMode;
use App\Models\PaymentGateway;
use App\Models\Currency;
use App\Services\PaymentService;
use App\Services\PaymentAbstract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Smartisan\Settings\Facades\Settings;
use TelrGateway\TelrManager;


class Telr extends PaymentAbstract
{
    public mixed $response;

    public function __construct()
    {
        $paymentService = new PaymentService();
        parent::__construct($paymentService);
        $this->paymentGateway = PaymentGateway::with('gatewayOptions')->where(['slug' => 'telr'])->first();
        $this->paymentGatewayOption = $this->paymentGateway->gatewayOptions->pluck('value', 'option');
        Config::set('telr.test_mode', $this->paymentGatewayOption['telr_mode'] == GatewayMode::SANDBOX ? "test" : "live");
        Config::set('telr.create.ivp_store', $this->paymentGatewayOption['telr_store_id']);
        Config::set('telr.create.ivp_authkey', $this->paymentGatewayOption['telr_store_auth_key']);

    }

    public function payment($order, $request)
    {
        try {

            $currencyCode = 'USD';
            $currencyId = Settings::group('site')->get('site_default_currency');
            if (!blank($currencyId)) {
                $currency = Currency::find($currencyId);
                if ($currency) {
                    $currencyCode = $currency->code;
                }
            }

            Config::set('telr.currency', $currencyCode);
            Config::set('telr.return_auth', route('payment.success', ['order' => $order, 'paymentGateway' => 'telr']));
            Config::set('telr.return_can', route('payment.cancel', ['order' => $order, 'paymentGateway' => 'telr']));
            Config::set('telr.return_decl', route('payment.cancel', ['order' => $order, 'paymentGateway' => 'telr']));

            $telr = new TelrManager();
            $billingParams = [];
            return $telr->pay($order->order_serial_no, $order->amount, ' ', $billingParams)->redirect();

        } catch (Exception $e) {
            Log::info($e->getMessage());
            return redirect()->route('payment.index', [
                'order' => $order,
                'paymentGateway' => 'telr'
            ])->with('error', $e->getMessage());
        }
    }

    public function status(): bool
    {
        $paymentGateways = PaymentGateway::where(['slug' => 'telr', 'status' => Activity::ENABLE])->first();
        if ($paymentGateways) {
            return true;
        }
        return false;
    }

    public function success($order, $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $telrManager = new TelrManager();
            $transaction = $telrManager->handleTransactionResponse($request);

            if(isset($transaction->response['order']['card']['last4'])){
                $paymentService = new PaymentService;
                $paymentService->payment($order, 'telr', $transaction->response['order']['card']['last4']);
                return redirect()->route('payment.successful', ['order' => $order])->with('success', trans('all.message.payment_successful'));
            }else {
                return redirect()->route('payment.fail', [
                    'order'          => $order,
                    'paymentGateway' => 'telr'
                ])->with('error', $this->response['message'] ?? trans('all.message.something_wrong'));
            }

        } catch (Exception $e) {
            Log::info($e->getMessage());
            DB::rollBack();
            return redirect()->route('payment.fail', [
                'order' => $order,
                'paymentGateway' => 'telr'
            ])->with('error', $e->getMessage());
        }
    }

    public function fail($order, $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('payment.index', ['order' => $order])->with('error', trans('all.message.something_wrong'));
    }

    public function cancel($order, $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('home')->with('error', trans('all.message.payment_canceled'));
    }
}
