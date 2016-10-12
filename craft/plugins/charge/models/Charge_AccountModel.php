<?php
namespace Craft;

class Charge_AccountModel extends BaseModel
{
    protected function defineAttributes()
    {
        $attributes = [
            'id'                   => [AttributeType::Number, 'required' => true],
            'accessToken'          => [AttributeType::String],
            'livemode'             => [AttributeType::Bool, 'default' => false],
            'refreshToken'         => [AttributeType::String],
            'tokenType'            => [AttributeType::String],
            'stripePublishableKey' => [AttributeType::String],
            'stripeUserId'         => [AttributeType::String],
            'scope'                => [AttributeType::String],
            'userId'               => [AttributeType::Number],
            'enabled'              => [AttributeType::Bool, 'default' => false],
            'dateCreated'          => [AttributeType::DateTime],
            'owner'                => [AttributeType::Mixed]
        ];

        return $attributes;
    }

    public static function populateModels($data, $indexBy = null)
    {
        $return = parent::populateModels($data, $indexBy);

        // Grab all the owners in a single event for performance
        $owners = [];
        foreach($return as $key => $ret) {
            if(!isset($owners[$ret['userId']])) {
                $return[$key]['owner'] = craft()->users->getUserById($ret['userId']);
            }
        }

        return $return;
    }
}
