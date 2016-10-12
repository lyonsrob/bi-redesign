<?php
namespace Craft;

class Charge_MembershipSubscriptionRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_membershipsubscriptions';
    }


    protected function defineAttributes()
    {
        return [
            'name'            => [AttributeType::String, 'required' => true],
            'handle'          => [AttributeType::String, 'required' => true, 'unique' => true],
            'enabled'         => [AttributeType::Bool, 'required' => true],
            'activeUserGroup' => [AttributeType::String, 'required' => true],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'successEmails' => [
                static::MANY_MANY,
                'Charge_EmailRecord',
                'charge_membershipsubscription_emails(membershipSubscriptionId, emailId)',
                'condition' => 'type = \'success\''
            ],
            'recurringEmails' => [
                 static::MANY_MANY,
                'Charge_EmailRecord',
                'charge_membershipsubscription_emails(membershipSubscriptionId, emailId)',
                'condition' => 'type = \'recurring\''
            ],
            'failureEmails'   => [
                static::MANY_MANY,
                'Charge_EmailRecord',
                'charge_membershipsubscription_emails(membershipSubscriptionId, emailId)',
                'condition' => 'type = \'failure\''
            ]
        ];
    }

}



