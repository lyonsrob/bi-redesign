<?php
namespace Craft;

class Charge_EmailRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_emails';
    }

    protected function defineAttributes()
    {
        return [
            'name'         => [AttributeType::String, 'required' => true],
            'handle'       => [AttributeType::String, 'required' => true, 'unique' => true],
            'subject'      => [AttributeType::String, 'required' => true],
            'to'           => [AttributeType::String, 'required' => true],
            'bcc'          => [AttributeType::String],
            'enabled'      => [AttributeType::Bool, 'required' => true],
            'templatePath' => [AttributeType::String, 'required' => true],
        ];
    }
}



