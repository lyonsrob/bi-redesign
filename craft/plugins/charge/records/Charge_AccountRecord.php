<?php
namespace Craft;

class Charge_AccountRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_accounts';
    }

    protected function defineAttributes()
    {
        return [
            'userId'               => [AttributeType::Number],
            'accessToken'          => [AttributeType::String],
            'livemode'             => [AttributeType::Bool, 'default' => false],
            'refreshToken'         => [AttributeType::String],
            'tokenType'            => [AttributeType::String],
            'stripePublishableKey' => [AttributeType::String],
            'stripeUserId'         => [AttributeType::String],
            'scope'                => [AttributeType::String],
            'enabled'              => [AttributeType::Bool, 'default' => false],
        ];
    }
}



