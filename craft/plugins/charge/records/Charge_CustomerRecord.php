<?php
namespace Craft;

class Charge_CustomerRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_customers';
    }

    protected function defineAttributes()
    {
        return [
            'stripeId' => [AttributeType::String],
            'mode'     => [AttributeType::Enum, 'values' => 'test,live', 'default' => 'test', 'required' => true],
            'userId'   => [AttributeType::Number],
            'email'    => [AttributeType::String],
            'name'     => [AttributeType::String],
        ];
    }
}



