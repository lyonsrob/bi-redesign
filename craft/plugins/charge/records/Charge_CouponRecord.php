<?php
namespace Craft;

class Charge_CouponRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_coupons';
    }

    protected function defineAttributes()
    {
        return [
            'stripeId'          => [AttributeType::String],
            'name'   	        => [AttributeType::String, 'required' => true],
            'code'              => [AttributeType::String, 'required' => true, 'unique' => true],
            'paymentType'       => [AttributeType::String, 'required' => true],
            'couponType'        => [AttributeType::Enum, 'values' => 'amount,percentage', 'required' => true],
            'percentageOff'     => [AttributeType::Number, 'min' => 0],
            'amountOff'         => [AttributeType::Number, 'min' => 0],
            'currency'          => [AttributeType::String],
            'duration'          => [AttributeType::Enum, 'values' => 'forever,once,repeating'],
            'durationInMonths'  => [AttributeType::Number, 'min' => 0],
            'maxRedemptions'    => [AttributeType::Number, 'min' => 0],
            'redeemBy'          => [AttributeType::Number]];
    }
}



