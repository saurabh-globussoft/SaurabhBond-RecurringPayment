<?php
namespace SaurabhBond\RecurringPayment;

use App\Http\Controllers\Controller;
use Config;
use stdClass;

class PaymentController extends Controller
{

    protected $API_TOKEN;
    public static $_obj = null;

    public function  __construct()
    {
        $this->username = Config::get('paypal.username');
        $this->password = Config::get('paypal.password');
        $this->signature = Config::get('paypal.signature');
        $this->sandboxFlag = Config::get('paypal.sandboxFlag');
        $this->response = new stdClass();
    }

    public static function createObject()
    {
        if (!is_object(self::$_obj)) {
            self::$_obj = new PaymentController();
        }
        return self::$_obj;
    }

    public function createRecurringProfile($description, $cancelUrl, $returnUrl, $ipnUrl = '')
    {
        $data['METHOD'] = 'SetExpressCheckout';
        $data['VERSION'] = '86';
        $data['L_BILLINGTYPE0'] = 'RecurringPayments';
        $data['L_BILLINGAGREEMENTDESCRIPTION0'] = $description;
        $data['cancelUrl'] = $cancelUrl;
        $data['returnUrl'] = $returnUrl;
        if (isset($ipnUrl))
            $data['NOTIFYURL'] = $ipnUrl;

        $curlResponse = $this->paypalCurlFunction($data);

        $arr = preg_split("/\&/", $curlResponse);

        $response = [];
        foreach ($arr as $key => $val) {
            $temp = explode('=', $val);
            $response[$temp[0]] = urldecode($temp[1]);
        }

        if (strtoupper($response['ACK']) == "SUCCESS" || strtoupper($response['ACK']) == "SUCCESSWITHWARNING") {

            $this->response->status = 200;
            $this->response->success = true;
            $this->response->message = "Payment Initiated(Token generated). Please complete the payment by logging in.";
            if ($this->sandboxFlag == true)
                $this->response->url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $response['TOKEN'];
            else
                $this->response->url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $response['TOKEN'];
            $this->response->token = $response['TOKEN'];
            $this->response->data = $response;
            return json_encode($this->response);
        } else {
            return json_encode(['status' => '400', 'code' => $response['L_SEVERITYCODE0'], 'errCode' => $response['L_ERRORCODE0'], 'errMsg' => $response['L_LONGMESSAGE0'], 'message' => 'Something went wrong. Couldn\'t generate the paypal token']);
        }

    }

    public function confirmPayment($token, $description, $amount, $initialAmount = '', $billingPeriod, $billingFrequency)
    {
        $data['METHOD'] = 'GetExpressCheckoutDetails';
        $data['VERSION'] = '86';
//                $data['TOKEN'] = $request['token'];
        $data['TOKEN'] = $token;

        $curlResponse = $this->paypalCurlFunction($data);

        $arr = preg_split("/\&/", $curlResponse);

        $response = [];
        foreach ($arr as $key => $val) {
            $temp = explode('=', $val);
            $response[$temp[0]] = urldecode($temp[1]);
        }

        if (strtoupper($response['ACK']) == "SUCCESS" || strtoupper($response['ACK']) == "SUCCESSWITHWARNING") {
            if (isset($response['PAYERID'])) {

                //Saurabh describing each lines for reference
                $data['METHOD'] = 'CreateRecurringPaymentsProfile';            //method
                $data['VERSION'] = '86';                                //version
//                $data['TOKEN'] = $request['token'];
                $data['TOKEN'] = $token;
                $data['PAYERID'] = $response['PAYERID'];
                $data['PROFILESTARTDATE'] = date('Y-m-d h:i:s A T');  // The date when billing for this profile begins // It may take 24 hrs for activate
                $data['DESC'] = $description;
                $data['BILLINGPERIOD'] = $billingPeriod;   // Unit of measure for the billing cycle //Day Week Month SemiMonth // Year
                $data['BILLINGFREQUENCY'] = $billingFrequency;   // in every 1 week
                $data['AMT'] = $amount;             // amount // Amount to bill for each billing cycle
                if (isset($initialAmount))
                    $data['INITAMT'] = $initialAmount;             // amount // Amount to bill for each billing cycle
                $data['CURRENCYCODE'] = 'USD';   // currency code
                $data['COUNTRYCODE'] = 'US';    // country code
                $data['MAXFAILEDPAYMENTS'] = '3';  //

                $curlResponse = $this->paypalCurlFunction($data);
                $arr = preg_split("/\&/", $curlResponse);

                $response = [];
                foreach ($arr as $key => $val) {
                    $temp = explode('=', $val);
                    $response[$temp[0]] = urldecode($temp[1]);
                }
//                        dd($response);

                if (strtoupper($response['ACK']) == "SUCCESS" || strtoupper($response['ACK']) == "SUCCESSWITHWARNING") {

                    return json_encode([
                        'status' => 200,
                        'message' => 'Recurring profile created successfully.',
                        'paypal_recurring_profile_id' => $response['PROFILEID'],
                        'profile_status' => $response['PROFILESTATUS'],
                        'data' => $response
                    ]);


                } else {
                    return $this->apiError(400, "Invalid Token. Please check the token.");
                }
            } else {
                return $this->apiError(400, "Payment is only initiated with the provided token. Please complete the transaction");
            }
        } else {
            return json_encode(['status' => '400', 'code' => $response['L_SEVERITYCODE0'], 'errCode' => $response['L_ERRORCODE0'], 'errMsg' => $response['L_LONGMESSAGE0'], 'message' => 'Something went wrong. Please check the token.']);
        }


    }

    public function ipnHandler()
    {
        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2)
                $myPost[$keyval[0]] = urldecode($keyval[1]);
        }

        // read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
        $req = 'cmd=_notify-validate';
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($myPost as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $req .= "&$key=$value";
        }

        $res = $this->ipnHitCurlFunction($req);

        if (strcmp($res, "VERIFIED") == 0) {

            return json_encode(['status' => 200, 'message' => 'IPN response is verified. Update your database.']);

            /*  store the value like txn_id, payer_email other information in table

            NOTE: based on recurring_payment_id(got in IPN response) check the paypal_recurring_profile_id(got in confirm payment function) and update profile status.

            //after getting VERIFIED from ipn response, store the details in database.

            //Example
            $payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : '';
            $payment_amount = isset($_POST['mc_gross']) ? $_POST['mc_gross'] : '';
            $payment_currency = isset($_POST['mc_currency']) ? $_POST['mc_currency'] : '';
            $txn_id = isset($_POST['txn_id']) ? $_POST['txn_id'] : 'txn_id is not set';
            $receiver_email = isset($_POST['receiver_email']) ? $_POST['receiver_email'] : 'receiver email is not set';
            $payer_email = isset($_POST['payer_email']) ? $_POST['payer_email'] : 'payer_email is not set';

            // IPN message values depend upon the type of notification sent.
            // To loop through the &_POST array and print the NV pairs to the screen:

            foreach ($_POST as $key => $value) {
                echo $key . " = " . $value . "<br>";
            }

            */


        } else if (strcmp($res, "INVALID") == 0) {
            // IPN invalid, log for manual investigation
            return json_encode(['status' => 400, 'message' => 'INVALID']);
//            echo "The response from IPN was: <b>" . $res . "</b>";
        }
        /*  This is the IPN response what we get for recurring profile.

            mc_gross = 7.00 & period_type = +Regular & outstanding_balance = 0.00 & next_payment_date = 02 % 3A00 % 3A00 + Feb + 05 % 2C + 2017 + PST
            & protection_eligibility = Eligible & payment_cycle = Daily & address_status = confirmed & tax = 0.00 & payer_id = 4ZYSXVXGLNPAA
            & address_street = 1 + Main + St
            & payment_date = 02 % 3A27 % 3A34 + Feb + 04 % 2C + 2017 + PST &
            &payment_status = Completed & product_name = Recurring + Profile +for+Viralgram + Package + of +%247.00
            & charset = windows - 1252 & recurring_payment_id = I - 4XKPVAG007DU & address_zip = 95131 & first_name = saurabh & mc_fee = 0.50
            & address_country_code = US & address_name = Saurabh + Kumar % 27s + Test + Store & notify_version = 3.8 & amount_per_cycle = 7.00
            & payer_status = unverified & currency_code = USD & business = talktosaurabhkr % 40gmail . com & address_country = United + States
            & address_city = San + Jose & verify_sign = A2YnYs6LuOd - R8BHIdbWTA007galASzzVW9 - P2dsyvPDpzBppdGqmyiU
            & payer_email = srik9009 % 40gmail . com & initial_payment_amount = 7.00 & profile_status = Active
            & amount = 7.00 & txn_id = 9LU411419T3612616 & payment_type = instant & payer_business_name = saurabh + kumar % 27s + Test + Store
            & last_name = H & address_state = CA & receiver_email = talktosaurabhkr % 40gmail . com & payment_fee = 0.50 & receiver_id = LWZ9MK007B6GL
            & txn_type = recurring_payment & mc_currency = USD & residence_country = US & test_ipn = 1
            & transaction_subject = Recurring + Profile +for+Viralgram + Package + of +%247.00 & payment_gross = 7.00 & shipping = 0.00
            & product_type = 1 & time_created = 05 % 3A20 % 3A23 + Feb + 02 % 2C + 2017 + PST & ipn_track_id = RFALS

        */


    }

    public function getRecurringProfileDetails($profileId)
    {
        $data['METHOD'] = 'GetRecurringPaymentsProfileDetails';
        $data['VERSION'] = '86';
        $data['PROFILEID'] = $profileId;

        $curlResponse = $this->paypalCurlFunction($data);
        $arr = preg_split("/\&/", $curlResponse);

        $response = [];
        foreach ($arr as $key => $val) {
            $temp = explode('=', $val);
            $response[$temp[0]] = urldecode($temp[1]);
        }

        return $response;
//        dd($response);
    }

    public function paypalCurlFunction($data)
    {
        if ($this->sandboxFlag == true)
            $url = "https://api-3t.sandbox.paypal.com/nvp";
        else
            $url = "https://api-3t.paypal.com/nvp";
        $data['USER'] = $this->username;
        $data['PWD'] = $this->password;
        $data['SIGNATURE'] = $this->signature;

        $params = '';

        foreach ($data as $key => $value) {
            $params .= $key . '=' . $value . '&';
        }
        $params = rtrim($params, '&');

        $ch = curl_init();
        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); # timeout after 10 seconds, you can increase it

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  # Set curl to return the data instead of printing it to the browser.
        // curl_setopt($ch,  CURLOPT_USERAGENT , "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)"); # Some server may refuse your request if you dont pass user agent
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //execute post
        $result = curl_exec($ch);


        return $result;

    }

    public function ipnHitCurlFunction($req)
    {
        $ch = curl_init('https://www.sandbox.paypal.com/cgi-bin/webscr');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

        if (!($res = curl_exec($ch))) {
            // error_log("Got " . curl_error($ch) . " when processing IPN data");
            curl_close($ch);
            exit;
        }
        curl_close($ch);
        return $res;
    }

    public function checkRecurringProfileCronFunction()
    {


    }

    public function apiError($status, $message)
    {
        $this->response->status = $status;
        $this->response->success = false;
        $this->response->message = $message;
        $this->response->data = null;

        return json_encode($this->response, true);
    }


}