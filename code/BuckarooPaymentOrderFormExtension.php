<?php

/**
 * extends the OrderForm to allow the BP Modifier
 * to be updated just before the order is submitted.
 *
 */
class BuckarooPaymentOrderFormExtension extends Extension
{

    /**
     *
     * extension check
     * return NULL if there is no problem and return true if there is a problem.
     */
    public function OrderFormBeforeFinalCalculation($data, $form, $request)
    {
        if ($data["PaymentMethod"] != "BuckarooPayment") {
            return null;
        }
        $order = ShoppingCart::current_order();
        //this is the standard way for getting a specific Order Modifier, allowing
        //you to work on multiple modifiers of the same kind .
        if (!isset($data['BuckarooMethod']) || !$data["BuckarooMethod"]) {
            return "ERROR";
        }
        $modifiers = $order->Modifiers('BuckarooPaymentModifier');
        if ($modifiers) {
            foreach ($modifiers as $modifier) {
                $modifier->updateName(Convert::raw2sql($data['BuckarooMethod'], $write = true));
            }
        } else {
            return "ERROR";
        }
        return null;
    }
}
