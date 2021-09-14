<?php

namespace App\Http\Controllers\V1\Common;

use App\Http\Controllers\Controller;
use App\Models\Common\Setting;
use Illuminate\Http\Request;
use App\Models\Common\AdminService;
use App\Models\Common\CompanyCountry;
use App\Services\SendPushNotification;
use App\Models\Common\CompanyCity;
use App\Models\Common\UserRequest;
use App\Models\Common\Company;
use App\Models\Common\Country;
use App\Models\Common\State;
use App\Models\Common\City;
use App\Models\Common\Menu;
use App\Models\Common\CmsPage;
use App\Models\Common\PaymentLog;
use App\Models\Common\Rating;
use App\Models\Common\AuthLog;
use App\Models\Common\UserWallet;
use App\Models\Common\ProviderWallet;
use App\Models\Common\FleetWallet;
use App\Models\Common\AuthMobileOtp;
use App\Models\Common\Chat;
use App\Helpers\Helper;
use Carbon\Carbon;
use Auth;

class CommonController extends Controller
{
    
    public function base(Request $request) {
       
        $this->validate($request, [
            'salt_key' => 'required',
        ]);

        $license = Company::find(base64_decode($request->salt_key));
        
        if ($license != null) {
            try{  
            if (Carbon::parse($license->expiry_date)->lt(Carbon::now())) {
                return response()->json(['message' => 'License Expired'], 503);
            }

            $admin_service = AdminService::where('company_id', $license->id)->where('status', 1)->get();
           
            //$settings = Setting::where('company_id', $license->id)->first();

            $base_url = $license->base_url;

        $setting = Setting::where('company_id', $license->id)->first();
        $settings = json_decode(json_encode($setting->settings_data));

        $company_country = CompanyCountry::with('country')->where('company_id', $license->id)->where('status', 1)->get();
       
        $appsettings=[];
        if(count($settings)>0){
         
         $appsettings['demo_mode'] = (int)$setting->demo_mode;
         $appsettings['country'] = (int)$settings->site->country;
         $appsettings['provider_negative_balance'] = (isset($settings->site->provider_negative_balance)) ? $settings->site->provider_negative_balance : '';
         $appsettings['android_key'] = (isset($settings->site->android_key)) ? $settings->site->android_key : '';
         $appsettings['ios_key'] = (isset($settings->site->ios_key)) ? $settings->site->ios_key : '';
         $appsettings['referral'] = ($settings->site->referral ==1) ? 1 : 0;
        
         $appsettings['social_login'] = ($settings->site->social_login ==1) ? 1 :0;
         $appsettings['send_sms'] = ($settings->site->send_sms == 1) ? 1 : 0;
         $appsettings['send_email'] = ($settings->site->send_email == 1) ? 1 : 0;
         $appsettings['otp_verify'] = ($settings->transport->ride_otp == 1) ? 1 : 0;
         
         $appsettings['ride_otp'] = ($settings->transport->ride_otp == 1) ? 1 : 0;
         
         $appsettings['order_otp'] = ($settings->order->order_otp == 1) ? 1 : 0;
         $appsettings['date_format'] = (isset($settings->site->date_format)) ? $settings->site->date_format : 0;
       
        
         $appsettings['service_otp'] = ($settings->service->serve_otp == 1) ? 1 : 0;
         $appsettings['payments'] = (count($settings->payment) > 0) ? $settings->payment : 0;
         
         $appsettings['cmspage']['privacypolicy'] = (isset($settings->site->page_privacy)) ? $settings->site->page_privacy : 0;
         $appsettings['cmspage']['help'] = (isset($settings->site->help)) ? $settings->site->help : 0;
         $appsettings['cmspage']['terms'] = (isset($settings->site->terms)) ? $settings->site->terms : 0;
         $appsettings['cmspage']['cancel'] = (isset($settings->site->cancel)) ? $settings->site->cancel : 0;
         $appsettings['supportdetails']['contact_number'] = (isset($settings->site->contact_number) > 0) ? $settings->site->contact_number : 0;
         $appsettings['supportdetails']['contact_email']=(isset($settings->site->contact_email) > 0) ? $settings->site->contact_email : 0;
         $appsettings['languages']=(isset($settings->site->language) > 0) ? $settings->site->language : 0;

         $appsettings['cbs_url'] = $settings->site->cbs_url ;
        
         }
              return Helper::getResponse(['status' => 200, 'data' => ['base_url' => $base_url, 'services' => $admin_service,'appsetting'=>$appsettings, 'country' => $company_country ]]);
            }catch (Exception $e) {
               
                return Helper::getResponse(['status' => 500, 'message' => trans('Something Went Wrong'), 'error' => $e->getMessage() ]);
            }
        }
    }

    public function admin_services() {

        $admin_service = AdminService::where('company_id', Auth::user()->company_id)->whereNotIn('admin_service', ['ORDER'] )->where('status', 1)->get();

        return Helper::getResponse(['status' => 200, 'data' => $admin_service]);

    }

    public function hbl_amount_hash(Request $request) {

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

        
        $amount = $request->amount;
        $cost = $request->amount*100;
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


        $data=[];
        $data['amount']= $amount;
        $data['invoiceNo']= $invoiceNo;
        $data['merchantId']= $merchantId;
        $data['currencyCode']= $currencyCode;
        $data['secretKey']= $secretKey;
        $data['nonSecure']= $nonSecure;
        $data['signData']= $signData;

        $random = $invoiceNo;

        $user_type = $request->user_type;
 
        $log = new PaymentLog();
        $log->user_type = $user_type;
        $log->admin_service = 'WALLET';
        $log->is_wallet = '1';
        $log->amount = $request->amount;
        $log->response = json_encode($data);
        $log->transaction_code = $random;
        $log->payment_mode = strtoupper('hbl');
        $log->user_id = Auth::guard($user_type)->user()->id;
        $log->company_id = Auth::guard($user_type)->user()->company_id;
        $log->save();



        return Helper::getResponse(['status' => 200, 'data' => $data]);

    }

    public function countries_list() {
        $countries = Country::get();
        return Helper::getResponse(['data' => $countries]);
    }

    public function states_list($id) {
        $states = State::where('country_id', $id)->get();
        return Helper::getResponse(['data' => $states]);
    }

    public function cities_list($id) {
        $cities = City::where('state_id', $id)->get();
        return Helper::getResponse(['data' => $cities]);
    }

    public function cmspagetype($type) {
        $cities = CmsPage::where('page_name', $type)->where()->get();
        return Helper::getResponse(['data' => $cities]);
    }

    public function rating($request) {

        Rating::create([
                    'company_id' => $request->company_id,
                    'admin_service' => $request->admin_service,
                    'provider_id' => $request->provider_id,
                    'user_id' => $request->user_id,
                    'request_id' => $request->id,
                    'user_rating' => $request->rating,
                    'user_comment' => $request->comment,
                  ]);

        return true;
    }

    public function logdata($type, $id)
    {
        
        $date = \Carbon\Carbon::today()->subDays(7);

        $datum = AuthLog::where('user_type', $type)->where('user_id', $id)->orderBy('created_at','DESC')->whereDate('created_at', '>', $date)->paginate(5);

        return Helper::getResponse(['data' => $datum]);
    }

    public function walletDetails($type, $id)
    {
        
        $date = \Carbon\Carbon::today()->subDays(15);

        if($type == "User"){
            $datum = UserWallet::with('user')->where('user_id', $id)->select('*',\DB::raw('DATEDIFF(now(),created_at) as days'),\DB::raw('TIMEDIFF(now(),created_at) as total_time'));

        }elseif ($type == "Provider") {
            $datum = ProviderWallet::with('provider')->where('provider_id', $id);
        }elseif ($type == "Fleet") {
            $datum = FleetWallet::with('provider')->where('fleet_id', $id);
        }else if($type == "store"){
            try{ 
            $datum =\App\Models\Order\StoreWallet::where('store_id',$id);
            }catch (Exception $e) {
              return Helper::getResponse(['data' => []]);
                
            } 
        }

        $wallet_details = $datum->orderBy('created_at','DESC')->whereDate('created_at', '>', $date)->paginate(10);

        return Helper::getResponse(['data' => $wallet_details]);
    }

    public function chat(Request $request) 
    {

        $this->validate($request,[
            'id' => 'required',
            'admin_service' => 'required|in:TRANSPORT,ORDER,SERVICE', 
            'salt_key' => 'required',
            'user_name' => 'required',
            'provider_name' => 'required',
            'type' => 'required',
            'message' => 'required'
        ]);

        $company_id = base64_decode($request->salt_key);

        $user_request = UserRequest::where('request_id', $request->id)->where('admin_service', $request->admin_service)->where('company_id', $company_id)->first();

        if($user_request != null) {
            $chat=Chat::where('admin_service', $request->admin_service)->where('request_id', $request->id)->where('company_id', $company_id)->first();


            if($chat != null) {
                $data = $chat->data;
                $data[] = ['type' => $request->type, 'user' => $request->user_name, 'provider' => $request->provider_name, 'message' => $request->message  ];
                $chat->data = json_encode($data);
                $chat->save();
            } else {
                $chat = new Chat();
                $data[] = ['type' => $request->type, 'user' => $request->user_name, 'provider' => $request->provider_name, 'message' => $request->message  ];
                $chat->admin_service = $request->admin_service;
                $chat->request_id = $request->id;
                $chat->company_id = $company_id;
                $chat->data = json_encode($data);
                $chat->save();
            }

            if($request->type == 'user') {
                (new SendPushNotification)->ChatPushProvider($user_request->provider_id, 'chat_'.strtolower($chat->admin_service)); 
            } else if($request->type == 'provider') {
                (new SendPushNotification)->ChatPushUser($user_request->user_id, 'chat_'.strtolower($chat->admin_service)); 
            }
            
            

            return Helper::getResponse(['message' => 'Successfully Inserted!']);
        } else {
            return Helper::getResponse(['status' => 400, 'message' => 'No service found!']);
        }

        
    }

    public function sendOtp(Request $request) {

        $this->validate($request, [
            'country_code' => 'required',
            'mobile' => 'required',
            'salt_key' => 'required',
        ]);


        $company_id=base64_decode($request->salt_key);

        $otp = $this->createOtp($company_id);

        AuthMobileOtp::updateOrCreate(['company_id' => $company_id, 'country_code' => $request->country_code, 'mobile' => $request->mobile],['otp' => $otp]);

        $send_sms = Helper::send_sms($company_id, $request->mobile, 'Your OTP is ' . $otp . '. Do not share your OTP with anyone' );

        if($send_sms === true) {
            return Helper::getResponse(['message' => 'OTP sent!']);
        } else {
            return Helper::getResponse(['status' => '400', 'message' => 'Could not send SMS notification. Please try again!', 'error' => $send_sms]);
        }

        
    }

    public function createOtp($company_id) {

        $otp = mt_rand(1111, 9999);

        $auth_mobile_otp = AuthMobileOtp::select('id')->where('otp', $otp)->where('company_id', $company_id)->orderBy('id', 'desc')->first();

        if($auth_mobile_otp != null) {
            $this->createOtp($company_id);
        } else {
            return $otp ;
        } 
    }

    public function verifyOtp(Request $request) {

        $this->validate($request, [
            'country_code' => 'required',
            'mobile' => 'required',
            'otp' => 'required',
            'salt_key' => 'required',
        ]);


        $company_id=base64_decode($request->salt_key);

        $auth_mobile_otp = AuthMobileOtp::where('country_code', $request->country_code)->where('mobile', $request->mobile)->where('otp', $request->otp)->where('updated_at','>=',Carbon::now()->subMinutes(10))->where('company_id', $company_id)->first();

        if($auth_mobile_otp != null) {

            $auth_mobile_otp->delete();

            return Helper::getResponse([ 'message' => 'OTP sent!' ]);
        } else {

            return Helper::getResponse([ 'status' => '400', 'message' => 'OTP error!' ]);

        }


            

        
    }

}
