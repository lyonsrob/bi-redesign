<?php
namespace Craft;

class Charge_SubscriberElementType extends BaseElementType
{
    /**
     * Returns the element type name.
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Charge Subscriber');
    }

    /**
     * Returns whether this element type has content.
     *
     * @return bool
     */
    public function hasContent()
    {
        return false;
    }

    /**
     * @inheritDoc IElementType::hasStatuses()
     *
     * @return bool
     */
    public function hasStatuses()
    {
        return true;
    }

    /**
     * @inheritDoc IElementType::hasTitles()
     *
     * @return bool
     */
    public function hasTitles()
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
            'active'   => Craft::t('Active'),
            'expired'  => Craft::t('Expired'),
            'trialing' => Craft::t('Trialing'),
        ];
    }

    /**
     * Returns this element type's sources.
     *
     * @param string|null $context
     * @return array|false
     */
    public function getSources($context = null)
    {
        $sources = [];
        $sources['*'] = ['label' => Craft::t('All Subscribers')];

        $sources[] = ['heading' => Craft::t('Subscriptions')];
        foreach (craft()->charge_membershipSubscription->getAllMembershipSubscriptions() as $subscription) {
            $sources['membershipSubscriptionId:' . $subscription->id] = ['label' => $subscription->name, 'criteria' => ['membershipSubscriptionId' => $subscription->id]];
        }

        return $sources;
    }


    /**
     * Populates an element model based on a query result.
     *
     * @param array $row
     * @return array
     */
    public function populateElementModel($row)
    {
        return Charge_SubscriberModel::populateModel($row);
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
            'id'           => Craft::t('ID'),
            'user'         => Craft::t('User'),
            'subscription' => Craft::t('Subscription'),
            'charge'       => Craft::t('Charge'),
            'dateCreated'  => Craft::t('Date Joined')
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
            ->addSelect('charge_subscriber.membershipSubscriptionId, charge_subscriber.userId, charge_subscriber.chargeId, charge_subscriber.status')
            ->join('charge_subscriber charge_subscriber', 'charge_subscriber.id = elements.id');


        if ($criteria->membershipSubscriptionId) {
            $query->andWhere(DbHelper::parseParam('charge_subscriber.membershipSubscriptionId', $criteria->membershipSubscriptionId, $query->params));
        }

        if ($criteria->chargeId) {
            $query->andWhere(DbHelper::parseParam('charge_subscriber.chargeId', $criteria->chargeId, $query->params));
        }

        if ($criteria->userId) {
            $query->andWhere(DbHelper::parseParam('charge_subscriber.userId', $criteria->userId, $query->params));
        }

        if ($criteria->status) {
            $query->andWhere(DbHelper::parseParam('charge_subscriber.status', $criteria->status, $query->params));
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
            'userId'                   => [AttributeType::Number],
            'chargeId'                 => [AttributeType::Number],
            'membershipSubscriptionId' => [AttributeType::Number],
            'status'                   => [AttributeType::String]
        ];
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
            case 'subscription': {
                $subscription = $element->subscription();

                return $subscription->name;
            }
            case 'user' : {
                $user = $element->user();
                if ($user == null) return '';

                return '<a href="' . $user->getCpEditUrl() . '">' . $user->getName() . '</a>';
            }

            case 'charge' : {
                $charge = $element->charge();
                if ($charge == null) return '';

                return '<a href="' . $charge->getCpEditUrl() . '">' . $charge->getShortname() . '</a>';
            }

            case 'dateCreated': {
                if ($element->dateCreated) {
                    return $element->dateCreated->localeDate();
                } else {
                    return '';
                }
            }
        }
    }
}
