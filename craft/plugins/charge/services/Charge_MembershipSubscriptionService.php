<?php
namespace Craft;


class Charge_MembershipSubscriptionService extends BaseApplicationComponent
{
    private $user;

    public function saveSubscription(Charge_MembershipSubscriptionModel $model, $successEmailIds, $recurringEmailIds, $failureEmailIds)
    {
        if (!is_array($successEmailIds)) $successEmailIds = [];
        if (!is_array($recurringEmailIds)) $recurringEmailIds = [];
        if (!is_array($failureEmailIds)) $failureEmailIds = [];

        if ($model->id) {
            $record = Charge_MembershipSubscriptionRecord::model()->findById($model->id);
            if (!$record->id) {
                throw new Exception(Craft::t('No membership subscription exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Charge_MembershipSubscriptionRecord();
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->enabled = $model->enabled;
        $record->activeUserGroup = $model->activeUserGroup;

        $record->validate();
        $model->addErrors($record->getErrors());

        //validating emails ids
        $criteria = new \CDbCriteria();
        $criteria->addInCondition('id', $successEmailIds);
        $exist = Charge_EmailRecord::model()->exists($criteria);
        $hasEmails = (boolean)count($successEmailIds);
        if (!$exist && $hasEmails) {
            $model->addError('successEmails',
                'One or more emails do not exist in the system.');
        }
        $criteria = new \CDbCriteria();
        $criteria->addInCondition('id', $recurringEmailIds);
        $exist = Charge_EmailRecord::model()->exists($criteria);
        $hasEmails = (boolean)count($recurringEmailIds);
        if (!$exist && $hasEmails) {
            $model->addError('recurringEmails',
                'One or more emails do not exist in the system.');
        }
        $criteria = new \CDbCriteria();
        $criteria->addInCondition('id', $failureEmailIds);
        $exist = Charge_EmailRecord::model()->exists($criteria);
        $hasEmails = (boolean)count($failureEmailIds);
        if (!$exist && $hasEmails) {
            $model->addError('failureEmails',
                'One or more emails do not exist in the system.');
        }

        //saving
        if (!$model->hasErrors()) {

            $record->save(false);

            //Delete old links
            if ($model->id) {
                Charge_MembershipSubscriptionEmailRecord::model()->deleteAllByAttributes(['membershipSubscriptionId' => $model->id]);
            }

            //Save new links
            $rows = array_map(function ($id) use ($record) {
                return [$id, $record->id, 'success'];
            }, $successEmailIds);
            $cols = ['emailId', 'membershipSubscriptionId', 'type'];
            $table = Charge_MembershipSubscriptionEmailRecord::model()->getTableName();
            craft()->db->createCommand()->insertAll($table, $cols, $rows);

            $rows = array_map(function ($id) use ($record) {
                return [$id, $record->id, 'recurring'];
            }, $recurringEmailIds);
            $cols = ['emailId', 'membershipSubscriptionId', 'type'];
            $table = Charge_MembershipSubscriptionEmailRecord::model()->getTableName();
            craft()->db->createCommand()->insertAll($table, $cols, $rows);

            $rows = array_map(function ($id) use ($record) {
                return [$id, $record->id, 'failure'];
            }, $failureEmailIds);
            $cols = ['emailId', 'membershipSubscriptionId', 'type'];
            $table = Charge_MembershipSubscriptionEmailRecord::model()->getTableName();
            craft()->db->createCommand()->insertAll($table, $cols, $rows);

            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    public function deleteMembershipSubscriptionById($id)
    {
        Charge_MembershipSubscriptionRecord::model()->deleteByPk($id);

        return true;
    }

    public function getAllMembershipSubscriptions($criteria = [])
    {
        $membershipSubscriptionRecords = Charge_MembershipSubscriptionRecord::model()->findAll($criteria);

        return Charge_MembershipSubscriptionModel::populateModels($membershipSubscriptionRecords);
    }

    public function getMembershipSubscriptionById($id)
    {
        $result = Charge_MembershipSubscriptionRecord::model()->findById($id);

        if ($result) {
            return Charge_MembershipSubscriptionModel::populateModel($result);
        }

        return null;
    }

    public function getMembershipSubscriptionByHandle($handle)
    {
        $result = Charge_MembershipSubscriptionRecord::model()->findByAttributes(['handle' => $handle]);

        if ($result) {
            return Charge_MembershipSubscriptionModel::populateModel($result);
        }

        return null;
    }

    public function systemHasAnySubscriptions()
    {
        $subscriptions = $this->getAllMembershipSubscriptions();

        if(empty($subscriptions)) return false;

        return true;
    }
}
