<?php
namespace Craft;

/**
 * Charge element type
 */
class ChargeElementType extends BaseElementType
{
    /**
     * Returns the element type name.
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Charges');
    }

    /**
     * Returns whether this element type has content.
     *
     * @return bool
     */
    public function hasContent()
    {
        return true;
    }

    /**
     * @inheritDoc IElementType::hasStatuses()
     *
     * @return bool
     */
    public function hasStatuses()
    {
        return false;
    }

    /**
     * @inheritDoc IElementType::getStatuses()
     *
     * @return array|null
     */
    public function getStatuses()
    {
        return [
            'live' => Craft::t('Live'),
            'test' => Craft::t('Test'),
        ];
    }

    /**
     * @param null $source
     *
     * @return array
     */
    public function getAvailableActions($source = null)
    {
        $actions = [];

        if (craft()->userSession->checkPermission('charge-manageCharges')) {
            $deleteAction = craft()->elements->getAction('Delete');
            $deleteAction->setParams([
                'confirmationMessage' => Craft::t('Are you sure you want to delete the selected charges? This will not refund any payments, cancel subscriptions or alter data on Stripe'),
                'successMessage'      => Craft::t('Charges deleted.'),
            ]);
            $actions[] = $deleteAction;
        }

        return $actions;
    }


    /**
     * Returns this element type's sources.
     *
     * @param string|null $context
     * @return array|false
     */
    public function getSources($context = null)
    {
        $sources = [
            '*' => [
                'label' => Craft::t('All Charges'),
                'defaultSort' => ['dateOrdered', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('Charge Type')];
        $sources['type:one-time'] = ['label' => Craft::t('One-time'), 'criteria' => ['type' => 'one-off']];
        $sources['type:recurring'] = ['label' => Craft::t('Recurring'), 'criteria' => ['type' => 'recurring']];

        $sources[] = ['heading' => Craft::t('Mode')];
        $sources['mode:live'] = ['label' => Craft::t('Live'), 'criteria' => ['mode' => 'live']];
        $sources['mode:test'] = ['label' => Craft::t('Test'), 'criteria' => ['mode' => 'test']];

        return $sources;

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
            'id'        => Craft::t('ID'),
            'mode'      => Craft::t('Mode'),
            'type'      => Craft::t('Type'),
            'customerId'  => Craft::t('Customer'),
            'type'      => Craft::t('Type'),
            'payment'   => Craft::t('Payment'),
            'timestamp' => Craft::t('Date'),
            'status'    => Craft::t('Status')];
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
            'status',
            'planAmount',
            'sourceUrl',
            'customerEmail',
            'type',
            'hash',
            'meta',];
    }


    /**
     * Returns the table view HTML for a given attribute.
     *
     * @param BaseElementModel $element
     * @param string $attribute
     * @return string
     */
    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        switch ($attribute) {


            case 'mode': {
                return '<span class="modeLabel ' . $element->mode .'">'.$element->mode.'</span>';
            }

            case 'status' : {
                return $element->getHtmlStatusLabel();
            }

            case 'customerId' : {
                $customer = $element->customer();
                if ($customer == null) return '';

                return $customer->email;
            }

            case 'payment' : {

                $payments = $element->payments();

                $ret = [];
                foreach ($payments as $payment) {
                    $ret[] = '<li><a href="' . $element->getCpEditUrl() . '#payments">' . $payment->formatAmount() . '</a></li>';
                }

                return '<ul>' . implode($ret) . '</ul>';

            }

            case 'planAmount': {
                if ($element->type == 'recurring') {
                    return $element->formatPlanName();
                } else return $element->formatPlanAmount();

            }

            /*   case 'customerName': {
                   return $element->customerName . ' <a href="mailto:' . $element->customerEmail . '">' . $element->customerEmail . '</a>';
               }
            */

            case 'cardLast4': {
                return '<span class="cardType type' . $element->cardType . '"></span> ' . $element->formatCard();
            }

            case 'type': {
                if ($element->type == 'recurring') {
                    return ucwords($element->type);
                } else return 'One-time';
            }

            case 'timestamp': {
                if ($element->timestamp) {
                    return $element->timestamp->localeDate();
                } else {
                    return '';
                }
            }

            default: {
                return parent::getTableAttributeHtml($element, $attribute);
            }
        }
    }

    /**
     * Defines any custom element criteria attributes for this element type.
     *
     * @return array
     */
    public function defineCriteriaAttributes()
    {
        return [
            'mode'          => [AttributeType::Mixed],
            'userId'        => [AttributeType::Mixed],
            'timestamp'     => [AttributeType::Mixed],
            'hash'          => [AttributeType::String],
            'order'         => [AttributeType::String, 'default' => 'timestamp desc'],
            'customerId'    => [AttributeType::String],
            'customerEmail' => [AttributeType::Email],
            'type'          => [AttributeType::Enum],
            'sourceUrl'     => [AttributeType::String],
            'meta'          => [AttributeType::Mixed]];
    }

    /**
     * @inheritDoc IElementType::getElementQueryStatusCondition()
     *
     * @param DbCommand $query
     * @param string $status
     *
     * @return array|false|string|void
     */
    public function getElementQueryStatusCondition(DbCommand $query, $status)
    {
        switch ($status) {
            case 'live' : {
                return [
                    'and',
                    'charges.mode = "live"'
                ];
            }

            case 'test' : {
                return [
                    'and',
                    'charges.mode = "test"'
                ];
            }
        }
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
            ->addSelect('charges.userId, charges.sourceUrl, charges.type, charges.customerId,charges.mode, charges.description, charges.timestamp, charges.hash, charges.notes,
            charges.meta, charges.request, charges.actions')
            ->join('charges charges', 'charges.id = elements.id');

        if ($criteria->userId) {
            $query->andWhere(DbHelper::parseParam('charges.userId', $criteria->userId, $query->params));
        }

        if ($criteria->sourceUrl) {
            $query->andWhere(DbHelper::parseParam('charges.sourceUrl', $criteria->sourceUrl, $query->params));
        }

        if ($criteria->timestamp) {
            $query->andWhere(DbHelper::parseDateParam('charges.timestamp', $criteria->timestamp, $query->params));
        }

        if ($criteria->hash) {
            $query->andWhere(DbHelper::parseParam('charges.hash', $criteria->hash, $query->params));
        }

        if ($criteria->customerId) {
            $query->andWhere(DbHelper::parseParam('charges.customerId', $criteria->customerId, $query->params));
        }

        if ($criteria->mode) {
            $query->andWhere(DbHelper::parseParam('charges.mode', $criteria->mode, $query->params));
        }

        if ($criteria->meta) {
            $query->andWhere(DbHelper::parseParam('charges.meta', $criteria->meta, $query->params));
        }
    }

    /**
     * Populates an element model based on a query result.
     *
     * @param array $row
     * @return array
     */
    public function populateElementModel($row)
    {
        return ChargeModel::populateModel($row);
    }

    /**
     * Routes the request when the URI matches an element.
     *
     * @param BaseElementModel $element
     *
     * @return array|bool|mixed
     */
    public function routeRequestForMatchedElement(BaseElementModel $element)
    {
        $template = craft()->charge_charge->getElementTemplate();

        return array(
            'action' => 'templates/render',
            'params' => array(
                'template'  => $template,
                'variables' => array(
                    'charge' => $element
                )
            )
        );
    }

}
