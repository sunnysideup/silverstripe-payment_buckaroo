<?php

/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * @package: ecommerce
 * @sub-package: examples
 * @description: This is an example modifier that developers can use
 * as a starting point for writing their own modifiers.
 *
 **/
class BuckarooPaymentModifier extends OrderModifier {

// ######################################## *** model defining static variables (e.g. $db, $has_one)

	/**
	 * set the charge per payment type
	 * @var Array
	 */
	protected static $charges = array(
		'Paypal' => 1,
		'Ideal' => 2
	);
	static function set_charges($a) {self::$charges = $a;}
	static function get_charges() {return self::$charges;}
	static function add_charge($key, $value) {self::$charges[$key] = $value;}
	static function remove_charge($key) {unset(self::$charges[$key]);}

// ######################################## *** cms variables + functions (e.g. getCMSFields, $searchableFields)

	public static $singular_name = "Buckaroo Payment Surcharge";
		function i18n_singular_name() { return _t("BuckarooPaymentModifier.BUCKAROOPAYMENTMODIFIER", "Buckaroo Payment Surcharge");}

	public static $plural_name = "Modifier Examples";
		function i18n_plural_name() { return _t("BuckarooPaymentModifier.BUCKAROOPAYMENTMODIFIERS", "Buckaroo Payment Surcharges");}

// ######################################## *** other (non) static variables (e.g. protected static $special_name_for_something, protected $order)


// ######################################## *** CRUD functions (e.g. canEdit)
// ######################################## *** init and update functions


	/**
	 * allows you to save a new value to MyField
	 * @param String $s
	 * @param Boolean $write - write to database (you may want to set this to false if you do several updates)
	 */
	public function updateName($s, $write = true) {
		$this->Name = $s;
		if(isset(self::$charges[$s])) {
			$this->CalculatedTotal = self::$charges[$s];
		}
		if($write) {
			$this->write();
		}
	}

// ######################################## *** form functions (e. g. Showform and getform)

	/**
	 * standard OrderModifier Method
	 * Should we show a form in the checkout page for this modifier?
	 */
	public function ShowForm() {
		return false;
	}

	/**
	 * Should the form be included in the editable form
	 * on the checkout page?
	 * @return Boolean
	 */
	public function ShowFormInEditableOrderTable() {
		return false;
	}

	/**
	 * Should the form be included in the editable form
	 * on the checkout page?
	 * @return Boolean
	 */
	public function ShowFormOutsideEditableOrderTable() {
		return false;
	}
// ######################################## *** template functions (e.g. ShowInTable, TableTitle, etc...) ... USES DB VALUES

	/**
	 * standard OrderModifer Method
	 * Tells us if the modifier should take up a row in the table on the checkout page.
	 * @return Boolean
	 */
	public function ShowInTable() {
		if($this->Name) {
			return true;
		}
	}

	/**
	 * standard OrderModifer Method
	 * Tells us if the modifier can be removed (hidden / turned off) from the order.
	 * @return Boolean
	 */
	public function CanBeRemoved() {
		return false;
	}

// ######################################## ***  inner calculations.... USES CALCULATED VALUES



// ######################################## *** calculate database fields: protected function Live[field name]  ... USES CALCULATED VALUES

	function LiveName(){
		return $this->Name;
	}
// ######################################## *** Type Functions (IsChargeable, IsDeductable, IsNoChange, IsRemoved)



// ######################################## *** standard database related functions (e.g. onBeforeWrite, onAfterWrite, etc...)

// ######################################## *** AJAX related functions
	/**
	* some modifiers can be hidden after an ajax update (e.g. if someone enters a discount coupon and it does not exist).
	* There might be instances where ShowInTable (the starting point) is TRUE and HideInAjaxUpdate return false.
	*@return Boolean
	**/
	public function HideInAjaxUpdate() {
		//we check if the parent wants to hide it...
		//we need to do this first in case it is being removed.
		if(parent::HideInAjaxUpdate()) {
			return true;
		}
		// we do NOT hide it if values have been entered
		if($this->Name) {
			return false;
		}
		return true;
	}
// ######################################## *** debug functions

}
