<?php
namespace Craft;

class Charge_SubscriptionRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_subscriptions';
    }

    protected function defineAttributes()
    {
        return [
            'customerId'            => [AttributeType::String],
            'chargeId'              => [AttributeType::Number],
            'stripeId'              => [AttributeType::String],
            'mode'                  => [AttributeType::Enum, 'values' => 'test, live', 'default' => 'test'],
            'status'                => [AttributeType::String],
            'start'                 => [AttributeType::Number],
            'cancelAtPeriodEnd'     => [AttributeType::Bool],
            'currentPeriodStart'    => [AttributeType::Number],
            'currentPeriodEnd'      => [AttributeType::Number],
            'endedAt'               => [AttributeType::Number],
            'trialStart'            => [AttributeType::Number],
            'trialEnd'              => [AttributeType::Number],
            'canceledAt'            => [AttributeType::Number],
            'quantity'              => [AttributeType::Number],
            'applicationFeePercent' => [AttributeType::Number],
            'discount'              => [AttributeType::Number],
            'taxPercent'            => [AttributeType::Number],

            'planAmount'          => [AttributeType::Number],
            'planName'            => [AttributeType::String],
            'planInterval'        => [AttributeType::String],
            'planIntervalCount'   => [AttributeType::Number],
            'planTrialPeriodDays' => [AttributeType::Number],
            'planCurrency'        => [AttributeType::String],
            'planStripeId'        => [AttributeType::String]];


    }


    public function defineRelations()
    {
        return [
            'charge'   => [static::BELONGS_TO, 'ChargeRecord', 'required' => true, 'onDelete' => static::CASCADE],
         /*   'customer' => [static::BELONGS_TO, 'Charge_CustomerRecord', 'required' => true, 'onDelete' =>
                static::CASCADE],*/
            'user'     => [static::BELONGS_TO, 'UserRecord', 'required' => false, 'onDelete' => static::SET_NULL]
        ];
    }

}

