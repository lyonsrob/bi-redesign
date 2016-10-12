<?php
namespace Craft;

class Charge_SubscriberRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_subscriber';
    }


    protected function defineAttributes()
    {
        return [
            'status' => [AttributeType::String, 'required' => true],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'element'                => [
                static::BELONGS_TO,
                'ElementRecord',
                'id',
                'required' => true,
                'onDelete' => static::CASCADE],
            'user'                   => [
                static::BELONGS_TO,
                'UserRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
            'charge'                 => [
                static::BELONGS_TO,
                'ChargeRecord',
                'required' => false,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
            'membershipSubscription' => [
                static::BELONGS_TO,
                'Charge_MembershipSubscriptionRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
        ];
    }


}



