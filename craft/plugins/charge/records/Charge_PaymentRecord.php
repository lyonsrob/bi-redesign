<?php
namespace Craft;

class Charge_PaymentRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'charge_payments';
    }

    protected function defineAttributes()
    {
        return [
            'stripeId'           => [AttributeType::String],
            'customerId'         => [AttributeType::String],
            'mode'               => [AttributeType::Enum, 'values' => 'test, live', 'default' => 'test'],
            'amount'             => [AttributeType::Number],
            'amountRefunded'     => [AttributeType::Number],
            'status'             => [AttributeType::String],
            'refunded'           => [AttributeType::String],
            'paid'               => [AttributeType::String],
            'captured'           => [AttributeType::String],
            'invoiceId'          => [AttributeType::String],
            'status'             => [AttributeType::String],
            'receiptEmail'       => [AttributeType::String],
            'failureCode'        => [AttributeType::String],
            'failureMessage'     => [AttributeType::String],
            'currency'           => [AttributeType::String],
            'userId'             => [AttributeType::Number],
            'chargeId'           => [AttributeType::Number],
            'cardName'           => [AttributeType::String],
            'cardAddressLine1'   => [AttributeType::String],
            'cardAddressLine2'   => [AttributeType::String],
            'cardAddressCity'    => [AttributeType::String],
            'cardAddressState'   => [AttributeType::String],
            'cardAddressZip'     => [AttributeType::String],
            'cardAddressCountry' => [AttributeType::String],
            'cardLast4'          => [AttributeType::String],
            'cardType'           => [AttributeType::String],
            'cardExpMonth'       => [AttributeType::String],
            'cardExpYear'        => [AttributeType::String]];

    }

    public function defineRelations()
    {
        return [
            'element' => [static::BELONGS_TO,
                'ElementRecord',
                'id',
                'required' => true,
                'onDelete' => static::CASCADE],
            'charge'  => [
                static::BELONGS_TO,
                'ChargeRecord',
                'required' => true,
                'onDelete' => static::CASCADE],
            'user'    => [
                static::BELONGS_TO,
                'UserRecord',
                'required' => false,
                'onDelete' => static::SET_NULL]
        ];
    }

}

