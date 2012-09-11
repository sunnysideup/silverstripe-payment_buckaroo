<?php

/**
 * Buckaroo Payment
 */
class BuckarooPayment extends Payment {
	
	static $db = array(
		'TransactionID' => 'Varchar',
		'Method' => 'Varchar'
	);

	// Logo & Privacy Link
	protected static $logo = 'payment_buckaroo/images/logo.jpg';
	protected static $privacy_link = 'http://www.buckaroo.nl/zakelijk/over-ons/disclaimer.aspx';
	
	// URLs
	protected static $live_url = 'https://checkout.buckaroo.nl/html';
	protected static $test_url = 'https://testcheckout.buckaroo.nl/html';

	// Redirect URLs
	protected static $confirm_url = 'BuckarooPaymentPayment_Handler/confirm';
	protected static $cancel_url = 'BuckarooPaymentPayment_Handler/cancel';

	// Settings
	protected static $website_key;
	protected static $signature_secret_key;
	protected static $test_mode = true;
	
	static function set_settings($website_key, $signature_secret_key) {
		self::$website_key = $website_key;
		self::$signature_secret_key = $signature_secret_key;
	}
	
	static function set_test_mode($test_mode) {
		self::$test_mode = $test_mode;
	}
	
	function processPayment($data, $form) {
		
		// Checks credentials
		if(! self::$website_key || ! self::$signature_secret_key) {
			user_error('You are attempting to make a payment without the necessary credentials set', E_USER_ERROR);
		}

		$page = new Page();

		$page->Title = 'Redirection to Buckaroo...';
		$page->Logo = '<img src="' . self::$logo . '" alt="Payments powered by Buckaroo"/>';
		$page->Form = $this->PaymentForm();

		$controller = new Page_Controller($page);
		
		$form = $controller->renderWith('PaymentProcessingPage');

		return new Payment_Processing($form);
	}
	
	protected function PaymentForm() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');

		$url = self::$test_mode ? self::$test_url : self::$live_url;

		$inputs['brq_websitekey'] = self::$website_key;
		$inputs['brq_amount'] = $this->Amount->Amount;
		$inputs['brq_currency'] = $this->Amount->Currency;
		$inputs['brq_invoicenumber'] = $this->ID;

		$inputs['brq_return'] = Director::absoluteURL(self::$confirm_url, true);
		$inputs['brq_returncancel'] = $inputs['brq_returnerror'] = $inputs['brq_returnreject'] = Director::absoluteURL(self::$cancel_url, true);

		$order = $this->Order();
		$items = $order->Items();
		$inputs['brq_description'] = implode("\n", $items->map('ID', 'TableTitle'));

		ksort($inputs);
		foreach($inputs as $name => $value) {
			$signature .= "$name$value";
			$ATT_value = Convert::raw2att($value);
			$fields .= "<input type=\"hidden\" name=\"$name\" value=\"$ATT_value\"/>";
		}

		$signature = Convert::raw2att(sha1($signature . self::$signature_secret_key));
		$fields .= "<input type=\"hidden\" name=\"brq_signature\" value=\"$signature\"/>";

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

	
	function getPaymentFormFields() {
		$logo = '<img src="' . self::$logo . '" alt="'._t('BuckarooPayment.POWEREDBY',"Credit card payments powered by Buckaroo").'"/>';
		$privacyLink = '<a href="' . self::$privacy_link . '" target="_blank" title="'._t('BuckarooPayment.READPRIVACY',"Read Buckaroo's privacy policy").'">' . $logo . '</a><br/>';
		return new FieldSet(
			new LiteralField('BuckarooInfo', $privacyLink),
			new LiteralField(
				'BuckarooPaymentsList',
				
				//TODO: these methods aren't available in all countries
				'<img src="payment/images/payments/methods/visa.jpg" alt="Visa"/>' .
				'<img src="payment/images/payments/methods/mastercard.jpg" alt="MasterCard"/>' .
				'<img src="payment/images/payments/methods/american-express.gif" alt="American Express"/>' .
				'<img src="payment/images/payments/methods/discover.jpg" alt="Discover"/>' .
				'<img src="payment/images/payments/methods/paypal.jpg" alt="PayPal"/>'
			)
		);
	}

	function getPaymentFormRequirements() {return null;}
}

class BuckarooPayment_Handler extends Controller {

	protected $payment;
	
	function init() {
		parent::init();
		$paymentID = $this->request->getVar('brq_invoicenumber');
		if($paymentID) {
			$payment = DataObject::get_by_id('BuckarooPayment', $paymentID);
			if($payment && $payment->Status == 'Pending') {
				$this->payment = $payment;
				$this->payment->Message = $this->request->getVar('brq_statusmessage');
				$this->payment->TransactionID = $this->request->getVar('brq_payment');
				$this->payment->Method = $this->request->getVar('brq_payment_method');
				$this->payment->write();
			}
		}
	}
	
	function confirm() {
		if($this->payment) {
			$this->payment->Status = 'Success';
			$this->payment->write();
			return $this->doRedirect();
		}
		return array();
	}
	
	function cancel() {
		if($this->payment) {
			$this->payment->Status = 'Failure';
			$this->payment->write();
			return $this->doRedirect();
		}
		return array();
	}
	
	function doRedirect() {
		$object = $this->payment->PaidObject();
		$link = $object ? $object->Link() : Director::absoluteURL('home', true);
		return Director::redirect($link);
	}
}