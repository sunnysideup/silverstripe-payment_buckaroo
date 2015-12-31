<?php

/**
 * Buckaroo Payment
 */
class BuckarooPayment extends Payment
{

    protected $debug = false;

    public static $db = array(
        'TransactionID' => 'Varchar',
        'Method' => 'Varchar'
    );

    // Logo & Privacy Link
    protected static $logo = 'payment_buckaroo/images/logo_small.png';
    public static function set_logo($s)
    {
        self::$logo = $s;
    }

    protected static $privacy_link = 'http://www.buckaroo.nl/zakelijk/over-ons/disclaimer.aspx';
    public static function set_privacy_link($s)
    {
        self::$privacy_link = $s;
    }

    // URLs
    protected static $live_url = 'https://checkout.buckaroo.nl/html/';
    protected static $test_url = 'https://testcheckout.buckaroo.nl/html/';

    // Settings
    protected static $website_key;
    protected static $signature_secret_key;
    protected static $test_mode = true;
    protected static $payment_methods = array();

    public static function set_settings($website_key, $signature_secret_key)
    {
        self::$website_key = $website_key;
        self::$signature_secret_key = $signature_secret_key;
    }

    public static function set_test_mode($test_mode)
    {
        self::$test_mode = $test_mode;
    }

    /**
     * List of options where the user can select their preferred payment method.
     * should be formatted like this:
     * [payment_method_code] => [long_name]
     * e.g.
     * "ideal" => "pay using your bank"
     * @var Array
     */
    protected static $payment_method_options_field_data = array();
    public static function set_payment_method_options_field_data($a)
    {
        self::$payment_method_options_field_data = $a;
    }
    public static function get_payment_method_options_field_data()
    {
        return self::$payment_method_options_field_data;
    }
/*
    static function set_payment_methods(array $payment_methods) {
        foreach($payment_methods as $payment_method) {
            if
        }
    }
*/
    public function populateDefaults()
    {
        parent::populateDefaults();
        $this->Status = 'Pending';
    }

    public function processPayment($data, $form)
    {

        // Checks credentials
        if (! self::$website_key || ! self::$signature_secret_key) {
            user_error('You are attempting to make a payment without the necessary credentials set', E_USER_ERROR);
        }

        $this->Method = Convert::raw2sql($data['BuckarooMethod']);
        $this->write();

        $page = new Page();

        $page->Title = 'Redirection to Buckaroo...';
        $page->Logo = '<img src="' . self::$logo . '" alt="Payments powered by Buckaroo" class="logo buckarooLogo"/>';
        $page->Form = $this->PaymentForm($data);

        $controller = new Page_Controller($page);

        $form = $controller->renderWith('PaymentProcessingPage');

        return new Payment_Processing($form);
    }

    protected function PaymentForm($data)
    {
        Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');

        $url = self::$test_mode ? self::$test_url : self::$live_url;

        $inputs['brq_browseragent'] = "XXX";
        $inputs['brq_websitekey'] = self::$website_key;
        $inputs['brq_amount'] = $this->Amount->Amount;
        $inputs['brq_currency'] = $this->Amount->Currency;
        $inputs['brq_invoicenumber'] = $this->ID;

        $inputs['brq_return'] = Director::absoluteURL(BuckarooPayment_Handler::confirm_link($this), true);
        $inputs['brq_returncancel'] = $inputs['brq_returnerror'] = $inputs['brq_returnreject'] = Director::absoluteURL(BuckarooPayment_Handler::cancel_link($this), true);

        if (strpos($data['BuckarooMethod'], ',') === false) {
            $inputs['brq_payment_method'] = $data['BuckarooMethod'];
        } else {
            $inputs['brq_requestedservices'] = $data['BuckarooMethod'];
        }


        $order = $this->Order();
        $items = $order->Items();
        $inputs['brq_description'] = "Order from ".Director::absoluteURL("/"); //implode("\n", $items->map('ID', 'TableTitle'));

        $signatureInput = $fields = '';
        ksort($inputs);
        foreach ($inputs as $name => $value) {
            $signatureInput .= "$name=$value";
            $ATT_value = Convert::raw2att($value);
            $fields .= "<input type=\"hidden\" name=\"$name\" value=\"$ATT_value\"/>";
        }

        $signature = sha1($signatureInput . self::$signature_secret_key);
        $fields .= "<input type=\"hidden\" name=\"brq_signature\" value=\"$signature\"/>";
        if ($this->debug) {
            echo "
			<pre>
			NOTE: ALL VALUES START AND END WITH |
			---SIGNATURE INPUT---";
            print_r("|".$signatureInput."|");
            echo "
			FORM FIELDS
			";
            print_r("|".$fields."|");
            echo "
			SIGNATURE
			";
            print_r("|".$signature."|");
            echo "
			SECRET KEY
			";
            print_r("|".self::$signature_secret_key."|");
            echo "
			</pre>";
            die("END IN BUCKAROOPAYMENT.PHP");
        }
        return <<<HTML
			<form id="PaymentForm" method="post" action="$url">
				$fields
				<input type="submit" value="Submit" />
			</form>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery("input[type='submit']").hide();
					jQuery('#PaymentForm').submit();
				});
			</script>
HTML;
    }


    public function getPaymentFormFields()
    {
        $ajaxObject = EcommerceConfigAjax::get_one(ShoppingCart::current_order());
        $js = "EcomCart.add_synonym(\"".$ajaxObject->TableTotalID()."\", \"#OrderForm_OrderForm_Amount, input[name='Amount']\");";
        Requirements::javascript("payment_buckaroo/javascript/BuckarooPayment.js");
        Requirements::customScript($js, "BuckarooPaymentModifier");
        $value = array_shift(array_flip(self::$payment_method_options_field_data));
        $order = ShoppingCart::current_order();
        if ($order) {
            if ($modifiers = $order->Modifiers("BuckarooPaymentModifier")) {
                foreach ($modifiers as $modifier) {
                    $value = $modifier->Name;
                }
            }
        }
        if (is_array(self::$payment_method_options_field_data) && count(self::$payment_method_options_field_data)) {
            return new FieldSet(
                new OptionsetField(
                    'BuckarooMethod',
                    '',
                    self::$payment_method_options_field_data,
                    $value
                )
            );
        } else {
            return new FieldSet();
        }
    }

    public function getPaymentFormRequirements()
    {
        //this is giving weird errors - needs to be checked.
        return null;
        if ($this->hasPaymentMethodSelectors()) {
            return array("BuckarooMethod");
        }
        return null;
    }

    protected function hasPaymentMethodSelectors()
    {
        return is_array(self::$payment_method_options_field_data) && count(self::$payment_method_options_field_data);
    }
}

class BuckarooPayment_Handler extends Controller
{

    public static $payment_param = 'payment';
    public static $order_param = 'order';

    protected $payment;

    public function init()
    {
        parent::init();
        $paymentID = $this->request->getVar(self::$payment_param);
        $orderID = $this->request->getVar(self::$order_param);
        if ($paymentID && $orderID) {
            $payment = DataObject::get_by_id('BuckarooPayment', $paymentID);
            if ($payment && $payment->OrderID == $orderID && $payment->Status == 'Pending') {
                $this->payment = $payment;
                $this->payment->Message = $this->request->getVar('brq_statusmessage');
                $this->payment->TransactionID = $this->request->getVar('brq_payment');
                $this->payment->Method = $this->request->getVar('brq_payment_method');
                $this->payment->write();
            }
        }
    }

    public function confirm()
    {
        if ($this->payment) {
            $this->payment->Status = 'Success';
            $this->payment->write();
            return $this->doRedirect();
        }
        return array();
    }

    public function cancel()
    {
        if ($this->payment) {
            $this->payment->Status = 'Failure';
            $this->payment->write();
            return $this->doRedirect();
        }
        return array();
    }

    public function doRedirect()
    {
        $object = $this->payment->PaidObject();
        $link = $object ? $object->Link() : Director::absoluteURL('home', true);
        return Director::redirect($link);
    }

    public static function confirm_link(BuckarooPayment $payment)
    {
        return self::action_link('confirm', $payment);
    }

    public static function cancel_link(BuckarooPayment $payment)
    {
        return self::action_link('cancel', $payment);
    }

    private static function action_link($action, BuckarooPayment $payment)
    {
        $values = array(self::$payment_param => $payment->ID, self::$order_param => $payment->OrderID);
        return "BuckarooPayment_Handler/$action?" . http_build_query($values);
    }
}
