<?php
namespace Craft;

class Charge_SubscriberModel extends BaseElementModel
{
    protected $elementType = 'Charge_Subscriber';

    protected function defineAttributes()
    {
        $attributes = array_merge(parent::defineAttributes(), [
            'id'                       => [AttributeType::Number],
            'userId'                   => [AttributeType::Number, 'required' => true],
            'status'                   => [AttributeType::String, 'required' => true, 'default' => 'active'],
            'membershipSubscriptionId' => [AttributeType::Number, 'required' => true],
            'chargeId'                 => [AttributeType::Number]
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
        return UrlHelper::getCpUrl('charge/subscribers/' . $this->id);
    }


    public function user()
    {
        if ($this->userId)
        {
            return craft()->users->getUserById($this->userId);
        }
    }

    public function subscription()
    {
        if ($this->membershipSubscriptionId)
        {
            return craft()->charge_membershipSubscription->getMembershipSubscriptionById($this->membershipSubscriptionId);
        }
    }


    public function charge()
    {
        if ($this->chargeId)
        {
            return craft()->charge_charge->getChargeById($this->chargeId);
        }
    }

    public function customer()
    {
        if ($this->chargeId)
        {
            $charge = craft()->charge_charge->getChargeById($this->chargeId);
            return $charge->customer();
        }
    }


}
