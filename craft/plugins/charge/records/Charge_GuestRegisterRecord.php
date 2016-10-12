<?php
namespace Craft;

class Charge_GuestRegisterRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_guestregister';
    }

    protected function defineAttributes()
    {
        return [
            'userId'   => [AttributeType::Number],
            'chargeId' => [AttributeType::Number],
        ];
    }
}



