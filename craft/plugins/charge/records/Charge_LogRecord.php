<?php
namespace Craft;

class Charge_LogRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_logs';
    }

    protected function defineAttributes()
    {
        return [
            'mode'        => [AttributeType::Enum, 'values' => 'test,live,unset', 'default' => 'test', 'required' => true],
            'level'       => [AttributeType::String],
            'requestKey'  => [AttributeType::String],
            'type'        => [AttributeType::String],
            'source'      => [AttributeType::String],
            'extra'       => [AttributeType::Mixed]
        ];
    }
}



