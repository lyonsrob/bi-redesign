<?php
namespace Craft;

class Charge_PaymentModel extends BaseElementModel
{
    protected $elementType = 'Charge_Payment';

    protected function defineAttributes()
    {
        $attributes = array_merge(parent::defineAttributes(), [
            'id'                 => [AttributeType::Number, 'required' => true],
            'stripeId'           => [AttributeType::Number, 'required' => true],
            'customerId'         => [AttributeType::String],
            'chargeId'           => [AttributeType::String],
            'userId'             => [AttributeType::String],
            'mode'               => [AttributeType::Enum, 'values' => 'test, live', 'default' => 'test'],
            'user'               => [AttributeType::String],
            'amount'             => [AttributeType::Number],
            'amountRefunded'     => [AttributeType::Number],
            'status'             => [AttributeType::String],
            'refunded'           => [AttributeType::String],
            'paid'               => [AttributeType::String],
            'captured'           => [AttributeType::String],
            'invoiceId'          => [AttributeType::String],
            'status'             => [AttributeType::String],
            'receiptEmail'       => [AttributeType::String],
            'failureCode'        => [AttributeType::String],
            'failureMessage'     => [AttributeType::String],
            'currency'           => [AttributeType::String],
            'cardName'           => [AttributeType::String],
            'cardAddressLine1'   => [AttributeType::String],
            'cardAddressLine2'   => [AttributeType::String],
            'cardAddressCity'    => [AttributeType::String],
            'cardAddressState'   => [AttributeType::String],
            'cardAddressZip'     => [AttributeType::String],
            'cardAddressCountry' => [AttributeType::String],
            'cardLast4'          => [AttributeType::String],
            'cardType'           => [AttributeType::String],
            'cardExpMonth'       => [AttributeType::String],
            'cardExpYear'        => [AttributeType::String]
        ]);

        return $attributes;
    }


    /**
     * @return string
     */
    function __toString()
    {
        return $this->id;
    }

    /**
     * Returns the element's CP edit URL.
     *
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('charge/payment/' . $this->id);
    }


    public function amountFormatted()
    {
        return $this->formatAmount('symbol');
    }

    public function formatAmount($format = 'symbol')
    {
        $currency = $this->currency;
        $amount = $this->amount;
        return ChargePlugin::formatAmount($amount, $currency, $format);
    }


    public function cardFormatted($char = '&#183;')
    {
        return $this->formatCard();
    }

    public function formatCard($char = '&#183;')
    {
        return craft()->charge->formatCard($this->cardLast4, $this->cardType, $char);
    }

    public function stripeLink()
    {
        $base = 'https://dashboard.stripe.com/';
        if ($this->mode == 'test') {
            $base .= 'test/';
        }
        $base .= 'payments/';


        return $base . $this->stripeId;
    }
    
    public function getStatusLabel()
    {
        if($this->status == 'succeeded') {
            return Charge_PaymentStatus::Paid;
        }

        if($this->status == 'refunded') {
            return Charge_PaymentStatus::Refunded;
        }
        
        return '';
    }

}
