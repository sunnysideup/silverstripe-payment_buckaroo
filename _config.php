<?php

Director::addRules(50, array(
    'updatebuckaroopaymentchoice//$Action/$ID/$Batch' => 'BuckarooPaymentModifier_Controller',
));

//===================---------------- START ecommerce MODULE ----------------===================
//MUST SET
//BuckarooPayment::set_settings($website_key = "bla", $signature_secret_key = "foo");
//BuckarooPayment::set_test_mode(true);

//MAY SET
//BuckarooPayment::set_logo("themes/bla");
//BuckarooPayment::set_privacy_link("http//");
//BuckarooPaymentModifier::set_charges(array("paypal" => 0.99));

//ADD SURCHARGES
//BuckarooPayment::set_payment_method_options_field_data(array("paypal" => "pay using Pay pal (credit card)"));
//Object::add_extension('OrderForm', 'BuckarooPaymentOrderFormExtension');
//===================---------------- START ecommerce MODULE ----------------===================
