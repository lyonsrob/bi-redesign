<?php
namespace Craft;

class Charge_MembershipSubscriptionEmailRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'charge_membershipsubscription_emails';
    }

    protected function defineAttributes()
    {
        return [
            'type' => [AttributeType::String, 'required' => true]
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'membershipSubscription' => [
                static::BELONGS_TO,
                'Charge_MembershipSubscriptionRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
            'email'                  => [
                static::BELONGS_TO,
                'Charge_EmailRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
        ];
    }

}