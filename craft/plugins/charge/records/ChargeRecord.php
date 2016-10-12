<?php

namespace Craft;

class ChargeRecord extends BaseRecord
{
    private $currencySymbol = ['usd' => '&#36;', 'gbp' => '&#163;', 'eur' => '&#128;'];

    public function getTableName()
    {
        return 'charges';
    }

    public function defineAttributes()
    {
        return [
            'type'        => [AttributeType::Enum, 'values' => 'one-off,recurring', 'required' => true],
            'customerId'  => [AttributeType::Number],
            'mode'        => [AttributeType::Enum, 'values' => 'test,live'],
            'sourceUrl'   => [AttributeType::Url],
            'hash'        => [AttributeType::String, 'label' => 'Transaction Hash'],
            'request'     => [AttributeType::Mixed],
            'actions'     => [AttributeType::Mixed],
            'meta'        => [AttributeType::Mixed, 'label' => 'Meta Array'],
            'notes'       => [AttributeType::String, 'column' => ColumnType::Text],
            'description' => [AttributeType::String, 'label' => 'Description'],
            'timestamp'   => [AttributeType::DateTime, 'label' => 'Time'],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'element' => [
                static::BELONGS_TO,
                'ElementRecord',
                'id',
                'required' => true,
                'onDelete' => static::CASCADE],
            'user'    => [
                static::BELONGS_TO,
                'UserRecord',
                'required' => false,
                'onDelete' => static::SET_NULL]
        ];
    }


    /**
     * @return array
     */
    public function defineIndexes()
    {
        return array(
            ['columns' => ['hash'], 'unique' => true],
            ['columns' => ['customerId']],
            ['columns' => ['mode']],
            ['columns' => ['timestamp']]
        );
    }

}
