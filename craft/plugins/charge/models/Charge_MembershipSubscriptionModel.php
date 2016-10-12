<?php
namespace Craft;

use Charge\Traits\Charge_ModelRelationsTrait;

class Charge_MembershipSubscriptionModel extends BaseModel
{
    use Charge_ModelRelationsTrait;

    /**
     * @return array
     */
    public function getSuccessEmailIds()
    {
        return array_map(function (Charge_EmailModel $email) {
            return $email->id;
        }, $this->successEmails);
    }

    public function activeMemberCount()
    {
        return $this->getActiveMemberCount();
    }

    public function getActiveMemberCount()
    {
        return '10';
    }


    /**
     * @return array
     */
    public function getRecurringEmailIds()
    {
        return array_map(function (Charge_EmailModel $email) {
            return $email->id;
        }, $this->recurringEmails);
    }

    /**
     * @return array
     */
    public function getFailureEmailIds()
    {
        return array_map(function (Charge_EmailModel $email) {
            return $email->id;
        }, $this->failureEmails);
    }


    protected function defineAttributes()
    {
        return [
            'id'              => [AttributeType::Number],
            'name'            => [AttributeType::String, 'required' => true],
            'handle'          => [AttributeType::String, 'required' => true],
            'enabled'         => [AttributeType::Bool, 'required' => true, 'default' => true],
            'activeUserGroup' => [AttributeType::String, 'required' => true]
        ];
    }


}
