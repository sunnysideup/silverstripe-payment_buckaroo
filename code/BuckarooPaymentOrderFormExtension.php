<?php

/**
 * extends the OrderForm to allow the BP Modifier
 * to be updated just before the order is submitted.
 *
 */
class BuckarooPaymentOrderFormExtension extends Extension {


	function OrderFormBeforeFinalCalculation($data, $form, $request) {
		$order = ShoppingCart::current_order();
		//this is the standard way for getting a specific Order Modifier, allowing
		//you to work on multiple modifiers of the same kind .
		$modifiers = $order->Modifiers('BuckarooPaymentModifier');
		if(!isset($data['BuckarooMethod']) || !$data["BuckarooMethod"]) {
			return "ERROR";
		}
		foreach($modifiers as $modifier) {
			$modifier->updateName(Convert::raw2sql($data['BuckarooMethod'], $write = true));
		}
		return null;
	}




}
