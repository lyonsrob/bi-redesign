<?php
namespace Craft;

class ChargeService extends BaseApplicationComponent
{
    public $errors = [];
    private $activeCoupon;
    public $stripe;

    private $supportedCurrencies = ['usd' => ['name' => 'American Dollar', 'symbol' => '&#36;', 'symbol_long' => 'US&#36;', 'default' => true],
                                    'gbp' => ['name' => 'British Pound Sterling', 'symbol' => '&#163;', 'symbol_long' => '&#163;'],
                                    'eur' => ['name' => 'Euro', 'symbol' => '&#128;', 'symbol_long' => '&#128;'],
                                    'cad' => ['name' => 'Canadian Dollars', 'symbol' => '&#36;', 'symbol_long' => 'CA&#36;'],
                                    'aud' => ['name' => 'Australian Dollar', 'symbol' => '&#36;', 'symbol_long' => 'AU&#36;'],
                                    'hkd' => ['name' => 'Hong Kong Dollar', 'symbol' => '&#36;', 'symbol_long' => 'HK&#36;'],
                                    'sek' => ['name' => 'Swedish Krona', 'symbol' => ':-', 'symbol_long' => 'kr'],
                                    'dkk' => ['name' => 'Danish Krone', 'symbol' => ',-', 'symbol_long' => 'dkr'],
                                    'pen' => ['name' => 'Peruvian NueDdkvo Sol', 'symbol' => 'S/.', 'symbol_long' => 'S/.'],
                                    'jpy' => ['name' => 'Japanese Yen', 'symbol' => '&#165;', 'symbol_long' => '&#165;'],
                                    'nok' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'symbol_long' => 'kr'],
                                    'chf' => ['name' => 'Swiss Franc', 'symbol' => 'chf', 'symbol_long' => 'chf']];

    public function init()
    {
        $this->stripe = craft()->charge_stripe->stripe;
    }

    public function getChargeById($id)
    {
        return craft()->elements->getElementById($id, 'Charge');
    }

    public function getChargeByHash($hash)
    {
        $criteria = craft()->elements->getCriteria('Charge');
        $criteria->hash = $hash;

        return $criteria->first();
    }

    public function getChargesByUserId($userId)
    {
        if ($userId == null) return [];

        $criteria = craft()->elements->getCriteria('Charge');
        $criteria->userId = $userId;

        return $criteria->find();
    }

    public function getPublicKey()
    {
        return craft()->charge_stripe->getStripePk();
    }

    public function getMode()
    {
        return craft()->charge_stripe->getMode();
    }

    /**
     * Fires an 'onBeforeCharge' event.
     *
     * @param Event $event
     */
    public function onBeforeCharge(Event $event)
    {
        $this->raiseEvent('onBeforeCharge', $event);
    }


    /**
     * Fires an 'onCharge' event.
     *
     * @param Event $event
     */
    public function onCharge(Event &$event)
    {
        $this->raiseEvent('onCharge', $event);
    }

    /**
     * Fires an 'onValidate' event.
     *
     * @param Event $event
     */
    public function onValidate(Event &$event)
    {
        $this->raiseEvent('onValidate', $event);
    }

    public function addRequestError($type, $message)
    {
        $this->errors[$type] = $message;
    }

    public function handlePayment(ChargeModel &$model, $chargeModifiers = [])
    {
        $event = new Event($this, ['charge' => $model]);

        $this->onBeforeCharge($event);
        if($event->performAction === false) {
            craft()->charge_log->error('Request stopped from plugin via the onBeforeCharge event');
            return false;
        }

        $type = $model->paymentType();

        // Step 1. Get our Customer (will create if needed)
        $customer = craft()->charge_customer->findOrCreate($model->customerEmail, ['name' => $model->customerName, 'metadata' => $model->meta], $model);
        $model->customerId = $customer->id;

        // Step 2. Make sure we have some payment method ready
        $paymentCard = $customer->getPaymentSource($model);

        if ($paymentCard === false || $paymentCard == null) {
            craft()->charge_log->error('Failed to find payment source', ['customer' => $customer->id]);
            return false;
        }

        if (!empty($chargeModifiers)) {
            craft()->charge_charge->extraChargeAttrs = $chargeModifiers;
        }


        // Step 3. Branch based on charge type
        $success = craft()->charge_charge->create($type, $customer, $paymentCard, $model);

        if ($success) {
            // Fire an 'onCharge' event
            craft()->charge_log->success('Charge Successfully Completed', ['charge' => $model]);

            $event = new Event($this, ['charge' => $model]);
            $this->onCharge($event);
            if($event->performAction === false) {
                craft()->charge_log->error('Request stopped from plugin via the onCharge event. Payments have already been completed, only success actions have been prevented.');
                return $success;
            }

            // Fire to the onSuccess action with the model
            craft()->charge_actions->fireOnSuccess($model);
        } else {

            // Fire to the onFailure action with the model
            craft()->charge_actions->fireOnFailure($model);
        }

        return $success;
    }

    public function getCurrency($key)
    {
        if(!isset($this->supportedCurrencies[$key])) return false;
        return $this->supportedCurrencies[$key];
    }

    public function getCurrencies($key = 'all')
    {
        $key = strtolower($key);
        $defaultCurrency = 'usd';
        if ($key == 'all') return $this->supportedCurrencies;

        if (!isset($this->supportedCurrencies[$key])) return $this->supportedCurrencies[$defaultCurrency];
        return $this->supportedCurrencies[$key];
    }

    public function getCurrencySymbol($key)
    {
        $key = strtolower($key);

        $info = $this->getCurrencies($key);

        return $info['symbol'];
    }

    public function formatCard($last4, $brand, $char = '&#183;')
    {
        $ret = '';
        $charset = craft()->templates->getTwig()->getCharset();

        switch ($brand) {
            case 'American Express' :
                $ret = $char . $char . $char . $char . ' ' . $char . $char . $char . $char . $char . $char . ' ' . $char . $char . $last4;
                break;
            case 'Diners Club' :
                $ret = $char . $char . $char . $char . ' ' . $char . $char . $char . $char . ' ' . $char . $char . $last4;
                break;
            case 'Visa' :
            case 'MasterCard' :
            case 'Discover' :
            case 'JCB' :
                $ret = $char . $char . $char . $char . ' ' . $char . $char . $char . $char . ' ' . $char . $char . $char . $char . ' ' . $last4;
                break;
            default :
                $ret = $char . $char . $char . $char . ' ' . $char . $char . $char . $char . ' ' . $char . $char . $char . $char . ' ' . $char . $char . $char . $char;
                break;
        }

        return new \Twig_Markup($ret, $charset);
    }

}
