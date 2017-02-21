# SaurabhBond-RecurringPayment
To create paypal recurring profile, make transactions, and get the profile details

# Installation 
in composer.json, write  

    "saurabh-bond/recurring-payment": "dev-master"
and then update the composer

add the location to psr-4 in your composer.json 

    "SaurabhBond\\RecurringPayment\\":"vendor/saurabh-bond/recurring-payment/src"
    
# Usages:

In your controller, add this line
  
    use SaurabhBond\RecurringPayment\PaymentController as Bond;

then in your function or method , create the Instance of PaymentController

    $paypalObj = Bond::createObject(); 
    
 *(1)* to create recurring profile 
 
 call createRecurringProfile() with params as below
 
 $description ====> Description of goods or services associated with the billing agreement. This field is required for each     recurring payment billing agreement.  For ex- Recurring Profile for Viralgram Package of $ 7
 
 $cancelUrl ====>  (Required) URL to which the buyer is returned, if something went wrong.
 
 $returnUrl ====>  (Required) URL to which the buyer is returned, after successfully payment.
 
 $ipnUrl ====>  mention IPN url to which IPN response will be send, For recurring Payment IPN handling is must.   
 
 *(2)* to confirm payment
 
 In your success function ,call getPaymentDetails() with parmas as below
 
 $token ====> generated in first step
 
 $description ====> give the description same given for the createRecurringProfile()
 
 $amount    ====> amount for what the recurring profiles is created.
 
 $initialAmount  ====> If you want to charge user at the very first time.
 
 $billingPeriod  ====>  (Required) Unit for billing during this subscription period. Value is: Day/Week/SemiMonth/Month/Year

 $billingFrequency ====> (Required) Number of billing periods that make up one billing cycle. 
 
 
 for example,

      $recurringProfileDetails = json_decode(Bond::getPaymentDetails($request['token'],$description,$amount,$billingPeriod,$billingFrequency), true);

      if ($recurringProfileDetails['status'] == 200) {  
        // store the profile details in database with the unique recurring profile id.
      } else {
        echo json_encode(['status' => 400, 'message' => $recurringProfileDetails['message']]);
      }

