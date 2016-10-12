<?php
namespace Craft;

class Charge_PaymentElementType extends BaseElementType
{
    /**
     * Returns the element type name.
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Charge Payment');
    }

    /**
     * Returns this element type's sources.
     *
     * @param string|null $context
     * @return array|false
     */
    public function getSources($context = null)
    {
        return ['*' => ['label' => Craft::t('All payments')]];
    }


    /**
     * Populates an element model based on a query result.
     *
     * @param array $row
     * @return array
     */
    public function populateElementModel($row)
    {
        return Charge_PaymentModel::populateModel($row);
    }


    /**
     * Returns the attributes that can be shown/sorted by in table views.
     *
     * @param string|null $source
     * @return array
     */
    public function defineTableAttributes($source = null)
    {
        return [
            'id'         => Craft::t('ID'),
            'mode'       => Craft::t('Mode'),
            'stripeId'   => Craft::t('Stripe Id'),
            'customerId' => Craft::t('Customer'),
            'chargeId'   => Craft::t('Parent Charge'),
            'userId'     => Craft::t('Charge'),
            'amount'     => Craft::t('Amount')];
    }

    /**
     * Defines which model attributes should be searchable.
     *
     * @return array
     */
    public function defineSearchableAttributes()
    {
        return [
            'mode',
            'chargeId',
            'stripeId',
            'customerId',
            'userId',
            'amount'];
    }

    public function defineCriteriaAttributes()
    {
        return [
            'chargeId' => [AttributeType::String]
        ];
    }


    /**
     * Modifies an element query targeting elements of this type.
     *
     * @param DbCommand $query
     * @param ElementCriteriaModel $criteria
     * @return mixed
     */
    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
    {
        $query
            ->addSelect('charge_payments.chargeId, charge_payments.userId, charge_payments.stripeId, charge_payments.mode,
            charge_payments.amount, charge_payments.currency, charge_payments.cardName, charge_payments.cardAddressLine1,
            charge_payments.cardAddressLine2, charge_payments.cardAddressCity, charge_payments.cardAddressState, charge_payments.cardAddressZip,
            charge_payments.cardAddressCountry, charge_payments.cardLast4, charge_payments.cardType, charge_payments.cardExpMonth,
            charge_payments.cardExpYear, charge_payments.amountRefunded,
            charge_payments.status,
            charge_payments.refunded,
            charge_payments.paid,
            charge_payments.captured,
            charge_payments.invoiceId,
            charge_payments.receiptEmail,
            charge_payments.failureCode,
            charge_payments.failureMessage')
            ->join('charge_payments charge_payments', 'charge_payments.id = elements.id');

        if ($criteria->chargeId) {
            $query->andWhere(DbHelper::parseParam('charge_payments.chargeId', $criteria->chargeId, $query->params));
        }

    }

    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        switch ($attribute) {

            case 'mode': {
                $mode = 'yellow';
                if ($element->mode == 'live') $mode = 'green';

                return '<span class="status ' . $mode . '"></span> <span class="title">' . $element->mode . '</span>';
            }

            case 'chargeId' : {
                return '<a href="#">One-off</a>';
            }

            case 'amount': {
                return $element->formatAmount();
            }

            default : {
                return $element->$attribute;
            }
        }
    }
}
