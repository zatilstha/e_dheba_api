<?php 

namespace App\Services;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Validator;
use Exception;
use DateTime;
use Auth;
use Lang;
use App\Models\Common\Setting;
use App\ServiceType;
use App\Models\Common\Promocode;
use App\Provider;
use App\ProviderService;
use App\Helpers\Helper;
use GuzzleHttp\Client;
use App\Models\Common\PaymentLog;


//PayuMoney
use Tzsk\Payu\Facade\Payment AS PayuPayment;

//Paypal
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payee;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

use Redirect;
use Session;
use URL;


class PaymentGateway {

	private $gateway;

	public function __construct($gateway){
		$this->gateway = strtoupper($gateway);
	}

	public function process($attributes) {
		$provider_url = '';

		$gateway = ($this->gateway == 'STRIPE') ? 'CARD' : $this->gateway ;

		$log = PaymentLog::where('transaction_code', $attributes['order'])->where('payment_mode', $gateway )->first();

		if($log->user_type == 'provider') {
			$provider_url = '/provider';
		}

		switch ($this->gateway) {

			case "HBL":
                    try{    
                            $settings = json_decode(json_encode(Setting::first()->settings_data));
                    	    $paymentConfig = json_decode( json_encode( $settings->payment ) , true);
                            $cardObject = array_values(array_filter( $paymentConfig, function ($e) { return $e['name'] == 'hbl'; }));
					        $hbl = 0;

					        $merchant_id = "";
					        $secret_key = "";

					        if(count($cardObject) > 0) { 

					          $hbl = $cardObject[0]['status'];

					          $merchant_id_Object = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'merchant_id'; }));

					          $secret_key_Object = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'secret_key'; }));

					          $hbl_currency_Object = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'hbl_currency'; }));


					          if(count($merchant_id_Object) > 0) {
					                $merchant_id = $merchant_id_Object[0]['value'];
					          }

					          if(count($secret_key_Object) > 0) {
					                $secret_key = $secret_key_Object[0]['value'];
					          }

					          if(count($hbl_currency_Object) > 0) {
					                $hbl_currency = $hbl_currency_Object[0]['value'];
					          }
					        }




					        $amount = $attributes['amount'];
					        $cost = $amount*100;
					        $invoiceNo = '0000'.mt_rand(1111111111111111,9999999999999999);
					        $amount = str_pad($cost,12,"0",STR_PAD_LEFT);
					        $merchantId = $merchant_id;
					        $currencyCode =$hbl_currency;
					        $secretKey = $secret_key;
					        $nonSecure = "N";
					        $signatureString = $merchantId.$invoiceNo.$amount.$currencyCode.$nonSecure;

					        $signData = hash_hmac('SHA256', $signatureString, $secretKey, false);
					        $signData = strtoupper($signData);
					        $signData =urlencode($signData);

					        $log->transaction_code = $invoiceNo;
					        $log->save();


					        $data=[];
					        $data['amount']= $amount;
					        $data['invoiceNo']= $invoiceNo;
					        $data['merchantId']= $merchantId;
					        $data['currencyCode']= $currencyCode;
					        $data['secretKey']= $secretKey;
					        $data['nonSecure']= $nonSecure;
					        $data['signData']= $signData;

                            
					        return ['message' => 'Payment Info','data' => $data];



                    }catch(Exception $e){
                        
                    	return ['data' =>'', 'message' =>$e->getMessage()];

                    }
			break;

			case "STRIPE":

				try {
				
					$settings = json_decode(json_encode(Setting::first()->settings_data));
        			$paymentConfig = json_decode( json_encode( $settings->payment ) , true);

        			$cardObject = array_values(array_filter( $paymentConfig, function ($e) { return $e['name'] == 'card'; }));
			        $card = 0;

			        $stripe_secret_key = "";
			        $stripe_publishable_key = "";
			        $stripe_currency = "";

			        if(count($cardObject) > 0) { 
			            $card = $cardObject[0]['status'];

			            $stripeSecretObject = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'stripe_secret_key'; }));
			            $stripePublishableObject = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'stripe_publishable_key'; }));
			            $stripeCurrencyObject = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'stripe_currency'; }));

			            if(count($stripeSecretObject) > 0) {
			                $stripe_secret_key = $stripeSecretObject[0]['value'];
			            }

			            if(count($stripePublishableObject) > 0) {
			                $stripe_publishable_key = $stripePublishableObject[0]['value'];
			            }

			            if(count($stripeCurrencyObject) > 0) {
			                $stripe_currency = $stripeCurrencyObject[0]['value'];
			            }
			        }


        			\Stripe\Stripe::setApiKey( $stripe_secret_key );
					  $Charge = \Stripe\Charge::create([
		                "amount" => $attributes['amount'] * 100,
		                "currency" => $attributes['currency'],
		                "customer" => $attributes['customer'],
		                "card" => $attributes['card'],
		                "description" => $attributes['description'],
		                "receipt_email" => $attributes['receipt_email']
		             ]);
					$log->response = json_encode($Charge);
                	$log->save();

					$paymentId = $Charge['id'];

					return (Object)['status' => 'SUCCESS', 'payment_id' => $paymentId];

				} catch(StripeInvalidRequestError $e){
					// echo $e->getMessage();exit;
					return (Object)['status' => 'FAILURE', 'message' => $e->getMessage()];

	            } catch(Exception $e) {
	                return (Object)['status' => 'FAILURE','message' => $e->getMessage()];
	            }

				break;

			default:
				return redirect('dashboard');
		}
		

	}

	public function verify(Request $request) {

		$settings = json_decode(json_encode(Setting::first()->settings_data));

		$paymentConfig = json_decode( json_encode( $settings->payment ) , true);;

		$cardObject = array_values(array_filter( $paymentConfig, function ($e) { return $e['name'] == 'hbl'; }));

		$hbl = 0;

		$merchant_id = "";
        $secret_key = "";

        if(count($cardObject) > 0) { 

          $hbl = $cardObject[0]['status'];

          $merchant_id_Object = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'merchant_id'; }));

          $secret_key_Object = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'secret_key'; }));

          $hbl_currency_Object = array_values(array_filter( $cardObject[0]['credentials'], function ($e) { return $e['name'] == 'hbl_currency'; }));


          if(count($merchant_id_Object) > 0) {
                $merchant_id = $merchant_id_Object[0]['value'];
          }

          if(count($secret_key_Object) > 0) {
                $secret_key = $secret_key_Object[0]['value'];
          }

          if(count($hbl_currency_Object) > 0) {
                $hbl_currency = $hbl_currency_Object[0]['value'];
          }
        }

		$log = PaymentLog::where('transaction_code', $request->invoiceNo)->first();

		$log->hbl_response = json_encode($request->all());

		$log->save();

        $orderData = [];
		
	    $hbl_detail = json_decode($log->response);

	  
	  
        if($request->respCode=='00' && $request->failReason=='Approved'){

		    if($log->admin_service == "ORDER") {
		    	$orderData = json_decode($log->order_request, true);
		    }

	       
	        $payment_id = $request->invoiceNo;
	        


	        if($log->admin_service == "TRANSPORT") {
	        	try {
	        		$userRequest = \App\Models\Transport\RideRequest::find($log->transaction_id);
	        		$log->type_id = $userRequest->ride_type_id;

	        		$payment = \App\Models\Transport\RideRequestPayment::where('ride_request_id', $log->transaction_id)->first();
	        		$payment->payment_id = $payment_id;
		    		$payment->save();
	        	} catch (\Throwable $e) { }
	        	
	        } else if($response->admin_service == "DELIVERY") {

	            $UserRequest = \App\Models\Delivery\DeliveryRequest::find($response->transaction_id);

	            $UserRequest->paid = 1;
	            $UserRequest->status = 'COMPLETED';
	            $UserRequest->save();
	            //for create the transaction
	            (new \App\Http\Controllers\V1\Delivery\Provider\TripController)->callTransaction($UserRequest->id);

	            $requestData = ['type' => $UserRequest->admin_service, 'room' => 'room_'.$UserRequest->company_id, 'id' => $UserRequest->id, 'city' =>  $UserRequest->city_id, 'user' => $UserRequest->user_id ];
	            app('redis')->publish('checkDeliveryRequest', json_encode( $requestData ));

	            $response->type_id = $UserRequest->delivery_type_id;

	        }else if($log->admin_service == "ORDER") {
	        	$log->transaction_id = $payment_id;
	        	$log->save();
	        } else if($log->admin_service == "SERVICE") {
	        	try {
	        		$userRequest = \App\Models\Service\ServiceRequest::find($log->transaction_id);
	        		$log->type_id = $userRequest->service_id;

	        		$payment = \App\Models\Service\ServiceRequestPayment::where('service_request_id', $log->transaction_id)->first();
	        		$payment->payment_id = $payment_id;
		    		$payment->save();
	        	} catch (\Throwable $e) { }
	        	
	        }

	        $log->payment_id = $payment_id;
            $log->status='success';

            

	        return $log;

	    }else{

	    	if($log->admin_service == "TRANSPORT") {
	            $rideRequest = \App\Models\Transport\RideRequest::find($log->transaction_id);
	            $log->type_id = $rideRequest->ride_type_id;
	        }  else if($log->admin_service == "ORDER") {

	        }  else if($log->admin_service == "SERVICE") {
	            $serviceRequest = \App\Models\Service\ServiceRequest::find($log->transaction_id);
	            $log->type_id = $serviceRequest->service_id;

	        }else if($log->admin_service == "DELIVERY") {

	            $UserRequest = \App\Models\Delivery\DeliveryRequest::find($log->transaction_id);
	            $log->type_id = $UserRequest->delivery_type_id;

	        }

	    	$log->status = 'failed';
	    	
	    	return $log;
	    }

        


	}
	
}