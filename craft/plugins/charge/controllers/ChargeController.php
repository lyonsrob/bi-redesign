<?php
namespace Craft;

class ChargeController extends Charge_BaseController
{
    protected $allowAnonymous = true;
    private $charge;
    private $plugin;

    public function init()
    {
        $this->plugin = craft()->plugins->getPlugin('charge');
        craft()->charge_license->ping();

        if (!$this->plugin) {
            throw new Exception('Couldn’t find the Charge plugin!');
        }
    }

    public function actionCharge()
    {
        $this->handleCharge();
    }

    public function actionEndSubscription()
    {
        $this->requirePostRequest();
        craft()->charge_log->request('Subscription end request started');

        $subscriptionId = craft()->request->getRequiredPost('subscriptionId');

        try {
            if (craft()->charge_subscription->endSubscription($subscriptionId)) {
                if (craft()->request->isAjaxRequest()) {
                    $this->returnJson(['success' => true]);
                } else {
                    craft()->userSession->setNotice(Craft::t('Subscription ended.'));
                    $this->redirectToPostedUrl();
                }
            } else {
                if (craft()->request->isAjaxRequest()) {
                    $this->returnJson(['success' => false]);
                } else {
                    craft()->userSession->setError(Craft::t('Couldn’t end subscription.'));
                }
            }

        } catch(Exception $e) {
            throw new Exception($e);
        }


    }

    private function handleCharge()
    {
        $this->requirePostRequest();
        craft()->charge_log->request('Charge request started ');

        $this->charge = new ChargeModel();
        $valid = $this->_collectData();


        if($valid) {
            if ($this->charge->validate()) {

                $result = craft()->charge_userRegistration->preRegisterGuestIfNeeded($this->charge);
                if(craft()->charge_userRegistration->registerUser != false && $result == false) {

                    $this->charge->user = craft()->charge_userRegistration->account;

                    // We have some validation errors on the user model
                    foreach(craft()->charge_userRegistration->account->getErrors('general') as $key => $val) {
                        $this->charge->addError('general', $val);
                    }
                } else {

                    if (craft()->charge->handlePayment($this->charge)) {

                        $this->redirectToSuccess($this->charge);

                    } else {

                        craft()->charge_userRegistration->removePartialGuestRegisterIfPresent();
    
                        if (!empty(craft()->charge->errors)) {
                            foreach (craft()->charge->errors as $error) {
                                $this->charge->addError('general', $error);
                            }
                        } else {
                            $this->charge->addError('general', 'There was a problem with payment');
                        }

                        // Also remove any card details
                        $this->charge->cardToken = null;
                        $this->charge->cardLast4 = null;
                        $this->charge->cardType = null;
                    }
                }
            } else {
                $this->charge->addError('general', 'There was a problem with your details, please check the form and try again');
            }
        }

        $errors = [];
        foreach ($this->charge->getErrors() as $key => $errs) {
            foreach ($errs as $error) {
                if ($key != 'general') $errors[] = $key . ' : ' . $error;
                else $errors[] = $error;
            }
        }

        craft()->charge_log->note('Returned to user with error', ['extra' => ['errors' => $errors]]);

        craft()->urlManager->setRouteVariables([
            'charge'    => $this->charge,
            'allErrors' => $errors
        ]);
    }


    private function _collectData()
    {
        $this->safetyCheck();

        $requestType = 'regular';

        // Decode any encoded data
        $opts = craft()->charge_security->decode(craft()->request->getPost('opts',''));

        if(!is_array($opts)) {
            // You Shall Not Pass
            if(craft()->userSession->isAdmin()) {
                $this->charge->addError('general', 'Your payment form is missing the required payment options input. You need to use the {{ craft.charge.setPaymentOptions() }} tag within your payment form. Please consult the Charge documentation for details on properly configuring your payment form.');
            } else {
                $this->charge->addError('general', 'The required payment options field is missing. Please contact the site admin for assistance.');
            }
            return false;
        } else {

            // Ok. Let's collect our data
            if(isset($opts['allowDynamic']) && $opts['allowDynamic'] == true) {
                $this->charge->planAmount = craft()->request->getPost('planAmount');
                $this->charge->planInterval = craft()->request->getPost('planInterval');
                $this->charge->planIntervalCount = craft()->request->getPost('planIntervalCount');
                $this->charge->planCurrency = craft()->request->getPost('planCurrency');
            } else {
                if(isset($opts['planAmount'])) $this->charge->planAmount = $opts['planAmount'];
                if(isset($opts['planInterval'])) $this->charge->planInterval = $opts['planInterval'];
                if(isset($opts['planIntervalCount'])) $this->charge->planIntervalCount = $opts['planIntervalCount'];
                if(isset($opts['planCurrency'])) $this->charge->planCurrency = $opts['planCurrency'];
            }

            // Take a post value first, override if set in the opts
            $this->charge->description = craft()->request->getPost('description');
            if(isset($opts['description'])) $this->charge->description = $opts['description'];

            $this->charge->meta = craft()->request->getPost('meta',[]);
            if(isset($opts['meta'])) $this->charge->meta = $opts['meta'];

            $this->charge->coupon = craft()->request->getPost('coupon', craft()->request->getPost('planCoupon'));

            if(isset($opts['checkoutRequest']) && $opts['checkoutRequest'] === true) {
                // Woah there. We have to apply a difference set of rules for you
                $requestType = 'checkout';
            }

            if(isset($opts['actions']) && is_array($opts['actions'])) {
                $this->charge->actions = $opts['actions'];
            }

            if(isset($opts['planChoices']) && is_array($opts['planChoices'])) {
                if(empty($opts['planChoices'])) {
                    if(craft()->userSession->isAdmin()) {
                        $this->charge->addError('general', 'Your payment form is passing a planChoices array that is empty. Please consult the Charge documentation for details on properly configuring your payment form.');
                    } else {
                        $this->charge->addError('general', 'There is a problem with this payment form configuration. Please contact the site admin for assistance.');
                    }
                    return false;
                }

                $planChoices = $opts['planChoices'];

                // Do we have a 'planChoiceDefault' or 'planChoice' with matching option?
                $choice = false;
                if(isset($opts['planChoiceDefault']) && !is_array($opts['planChoiceDefault'])) {
                    $choice = $opts['planChoiceDefault'];
                }

                $choice = craft()->request->getPost('planChoice', $choice);

                if($choice == false || !isset($planChoices[$choice])) {
                    $this->charge->addError('planChoice', 'You must pick a plan choice');
                } else {

                    // Ok. Now assign
                    $planChoice = $planChoices[$choice];
                    foreach($planChoice as $key => $val) {
                        $this->charge->setAttribute($key, $val);
                    }
                }
            }
        }
        
        // Always dynamic details
        if($requestType == 'checkout') {
            /*
             * CHECKOUT FIELDS :
                stripeToken	The ID of the token you need to create a charge or a customer.
                stripeEmail	The email address the user entered during the Checkout process.
                stripeBillingName
                stripeBillingAddressLine1
                stripeBillingAddressZip
                stripeBillingAddressState
                stripeBillingAddressCity
                stripeBillingAddressCountry	Billing address details (if enabled).
                stripeShippingName
                stripeShippingAddressLine1
                stripeShippingAddressZip
                stripeShippingAddressState
                stripeShippingAddressCity
                stripeShippingAddressCountry	Shipping address details (if enabled).
            */

            $this->charge->cardToken = craft()->request->getPost('stripeToken');
            $this->charge->customerEmail = craft()->request->getPost('stripeEmail');

        } else {

            $this->charge->cardToken = craft()->request->getPost('cardToken');
            $this->charge->cardLast4 = craft()->request->getPost('cardLast4');
            $this->charge->cardType = craft()->request->getPost('cardType');
            $this->charge->cardName = craft()->request->getPost('cardName');
            $this->charge->cardExpMonth = craft()->request->getPost('cardExpMonth');
            $this->charge->cardExpYear = craft()->request->getPost('cardExpYear');
            $this->charge->cardAddressLine1 = craft()->request->getPost('cardAddressLine1');
            $this->charge->cardAddressLine2 = craft()->request->getPost('cardAddressLine2');
            $this->charge->cardAddressCity = craft()->request->getPost('cardAddressCity');
            $this->charge->cardAddressState = craft()->request->getPost('cardAddressState');
            $this->charge->cardAddressZip = craft()->request->getPost('cardAddressZip');
            $this->charge->cardAddressCountry = craft()->request->getPost('cardAddressCountry');

            $this->charge->customerName = craft()->request->getPost('customerName');
            $this->charge->customerEmail = craft()->request->getPost('customerEmail');
            $this->charge->planName = craft()->request->getPost('planName');
        }

        $this->charge->createAccount = craft()->request->getPost('createAccount');
        $this->charge->setContent(craft()->request->getPost('fields'));
        return true;
    }

    private function redirectToSuccess($chargeModel)
    {
        if(craft()->request->getPost('redirect') != '') {
            $this->redirectToPostedUrl($this->charge);
        } else {
            $this->redirect($this->charge->uri);
        }

    }

    /*
     * Safety Check
     *
     * Does a quick check to see if cardNumber, or cardCvc are visible to the server
     * and if they are, immediately throws an exception.
     * We don't ever want to see those details for proper PCI compliance
     *
     */
    private function safetyCheck()
    {
        $escapeInputs = ['cardNumber', 'cardCvc', 'cardCvv'];

        foreach($escapeInputs as $key) {
            if(craft()->request->getPost($key) != '' ) {

                // Add a mark in the logs
                craft()->charge_log->exception('Security Error - Request Stopped. CODE "Charge 403 PCI keys"', ['message' => 'Invalid Request, code "Charge 403 PCI keys".   The request included a forbidden input. For PCI compliance card details must never be passed to the server directly, and must first be tokenized via javascript. Please consult the usage documentation for Charge.', 'invalidKey' => $key]);

                if(craft()->userSession->isAdmin()) {
                    throw new HttpException(403, 'Invalid Request, code "Charge 403 PCI keys".   The request included a forbidden input. For PCI compliance card details must never be passed to the server directly, and must first be tokenized via javascript. Please consult the usage documentation for Charge. The invalid key was : '.$key);
                } else {
                    throw new HttpException(403, Craft::t('This request is invalid. Please contact the site admin, quoting the code - "Charge 403 PCI keys"'));
                }

            }
        }
    }
}
