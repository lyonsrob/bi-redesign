<?php

namespace Craft;

class ChargeVariable
{
    public function getPublicKey()
    {
        return craft()->charge->getPublicKey();
    }

    public function getMode()
    {
        return craft()->charge->getMode();
    }

    public function charges($criteria = null)
    {
    	return craft()->elements->getCriteria('Charge', $criteria);
    }

    public function getChargesByUser($userId = null)
    {
        if($userId == null) {
            $user = craft()->userSession->getUser();
            if($user != null) $userId = $user->id;
        }

        return craft()->charge->getChargesByUserId($userId);
    }

    public function getChargeByHash($hash = null)
    {
        return craft()->charge->getChargeByHash($hash);
    }

    public function hash($hash = null)
    {
        return $this->getChargeByHash($hash);
    }

    public function setPaymentOptions($options = [])
    {
        $encoded = craft()->charge_security->encode($options);
        return $this->returnRaw('<input type="hidden" name="opts" value="'.$encoded.'"/>');
    }

    public function setCheckoutOptions($options = [])
    {
        $ret = '<div class="charge-checkout-container">';

        $options['checkoutRequest'] = true;
        $encoded = craft()->charge_security->encode($options);
        $ret .= '<input type="hidden" name="opts" value="'.$encoded.'"/>';

        $checkoutKeys = [];

        $checkoutKeys['key'] = $this->getPublicKey();

        if(isset($options['planAmount']) && is_numeric($options['planAmount'])) {
            $checkoutKeys['amount'] = floor($options['planAmount'] * 100);
        }
        if(isset($options['planCurrency']) && $options['planCurrency'] != '') {
            $checkoutKeys['currency'] = $options['planCurrency'];
        }

        if(isset($options['description'])) {
            $checkoutKeys['description'] = $options['description'];
        }

        if(isset($options['checkout']) && is_array($options['checkout'])) {
            foreach($options['checkout'] as $key => $val) {
                if(!is_array($val) && $val != '') {
                    $checkoutKeys[$key] = $val;
                }
            }
        }

        $dataStr = [];
        foreach($checkoutKeys as $key => $val) {
            $dataStr[] = 'data-'.$key.'="'.$val.'"';
        }

        $includeButton = true;
        if(isset($options['includeButton']) && $options['includeButton'] == false){
            $includeButton = false;
        }
        if($includeButton) {
            $button = '<script src="https://checkout.stripe.com/checkout.js" class="stripe-button"' . implode(' ', $dataStr) . '></script>';
            $ret .= $button;
        }

        $ret .= '</div>';

        return $this->returnRaw($ret);
    }

    private function returnRaw($rawMarkup)
    {
        return new \Twig_Markup(html_entity_decode($rawMarkup,ENT_QUOTES), craft()->templates->getTwig()->getCharset());
    }


    /*
     * Stripe Connect Variables
     */
    public function isConnectEnabled()
    {
        return craft()->charge_connect->getConnectEnabledStatus();
    }

    public function connectAccountStatus()
    {
        return craft()->charge_connect->getAccountStatus();
    }

    public function connectButtonUrl()
    {
        return craft()->charge_connect->getConnectUrl();
    }

    public function customer()
    {
        return craft()->charge_customer->findByCurrentUser();
    }
}
