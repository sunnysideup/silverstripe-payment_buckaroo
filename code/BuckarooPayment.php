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
	protected static $logo = 'payment_buckaroo/images/logo_small.png';
	protected static $privacy_link = 'http://www.buckaroo.nl/zakelijk/over-ons/disclaimer.aspx';

	// URLs
	protected static $live_url = 'https://checkout.buckaroo.nl/html/';
	protected static $test_url = 'https://testcheckout.buckaroo.nl/html/';

	// Settings
	protected static $website_key;
	protected static $signature_secret_key;
	protected static $test_mode = true;
	protected static $payment_methods = array();

	static function set_settings($website_key, $signature_secret_key) {
		self::$website_key = $website_key;
		self::$signature_secret_key = $signature_secret_key;
	}

	static function set_test_mode($test_mode) {
		self::$test_mode = $test_mode;
	}
/*
	static function set_payment_methods(array $payment_methods) {
		foreach($payment_methods as $payment_method) {
			if
		}
	}
*/
	function populateDefaults() {
		parent::populateDefaults();
		$this->Status = 'Pending';
 	}

	function processPayment($data, $form) {

		// Checks credentials
		if(! self::$website_key || ! self::$signature_secret_key) {
			user_error('You are attempting to make a payment without the necessary credentials set', E_USER_ERROR);
		}

		$this->Method = $data['Method'];
		$this->write();

		$page = new Page();

		$page->Title = 'Redirection to Buckaroo...';
		$page->Logo = '<img src="' . self::$logo . '" alt="Payments powered by Buckaroo" class="logo buckarooLogo"/>';
		$page->Form = $this->PaymentForm($data);

		$controller = new Page_Controller($page);

		$form = $controller->renderWith('PaymentProcessingPage');

		return new Payment_Processing($form);
	}

	protected function PaymentForm($data) {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');

		$url = self::$test_mode ? self::$test_url : self::$live_url;

		$inputs['brq_websitekey'] = self::$website_key;
		$inputs['brq_amount'] = $this->Amount->Amount;
		$inputs['brq_currency'] = $this->Amount->Currency;
		$inputs['brq_invoicenumber'] = $this->ID;

		$inputs['brq_return'] = Director::absoluteURL(BuckarooPayment_Handler::confirm_link($this), true);
		$inputs['brq_returncancel'] = $inputs['brq_returnerror'] = $inputs['brq_returnreject'] = Director::absoluteURL(BuckarooPayment_Handler::cancel_link($this), true);

		$inputs['brq_payment_method'] = $data['Method'];

		$order = $this->Order();
		$items = $order->Items();
		$inputs['brq_description'] = implode("\n", $items->map('ID', 'TableTitle'));

		$signature = $fields = '';
		ksort($inputs);
		foreach($inputs as $name => $value) {
			$signature .= "$name=$value";
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
		return new FieldSet(
			new OptionsetField('Method', '', array(
				'ideal' => '<span class="methodTitle">iDEAL <span class="fees">+ &euro; 0.50</span></span><span class="methodImages"><img src="http://buckaroo.nl/ims/logo_abn_s.gif"><img src="http://buckaroo.nl/ims/logo_asn_s.gif"><img src="http://buckaroo.nl/ims/logo_friesland_s.gif"><img src="http://buckaroo.nl/ims/logo_ing_s.gif"><img src="http://demo.buckaroo.nl/ims/logo_lanschot_s.gif"><img src="http://buckaroo.nl/ims/logo_rabo_s.gif"><img src="http://buckaroo.nl/ims/logo_sns_s.gif"><img src="http://buckaroo.nl/ims/logo_triodos.gif"></span>',
				'paypal' => '<span class="methodTitle">Paypal <span class="fees">+ &euro; 0.90</span></span><span class="methodImages"><img src="http://buckaroo.nl/ims/logo_paypal_s.gif"></span>'
			),
			'ideal')
		);
	}

	function getPaymentFormRequirements() {}
}

class BuckarooPayment_Handler extends Controller {

	static $payment_param = 'payment';
	static $order_param = 'order';

	protected $payment;

	function init() {
		parent::init();
		$paymentID = $this->request->getVar(self::$payment_param);
		$orderID = $this->request->getVar(self::$order_param);
		if($paymentID && $orderID) {
			$payment = DataObject::get_by_id('BuckarooPayment', $paymentID);
			if($payment && $payment->OrderID == $orderID && $payment->Status == 'Pending') {
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

	static function confirm_link(BuckarooPayment $payment) {
		return self::action_link('confirm', $payment);
	}

	static function cancel_link(BuckarooPayment $payment) {
		return self::action_link('cancel', $payment);
	}

	private static function action_link($action, BuckarooPayment $payment) {
		$values = array(self::$payment_param => $payment->ID, self::$order_param => $payment->OrderID);
		return "BuckarooPayment_Handler/$action?" . http_build_query($values);
	}
}
