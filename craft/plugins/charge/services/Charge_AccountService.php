<?php
namespace Craft;

class Charge_AccountService extends BaseApplicationComponent
{
    public function saveAccount(Charge_AccountModel $account)
    {
        $accountRecord = $this->_getAccountRecordById($account->id);
        $accountRecord->userId = $account->userId;
        $accountRecord->accessToken = $account->accessToken;
        $accountRecord->livemode = $account->livemode;
        $accountRecord->refreshToken = $account->refreshToken;
        $accountRecord->tokenType = $account->tokenType;
        $accountRecord->stripePublishableKey = $account->stripePublishableKey;
        $accountRecord->stripeUserId = $account->stripeUserId;
        $accountRecord->scope = $account->scope;
        $accountRecord->enabled = $account->enabled;


        $accountRecord->save(false);

        // Now that we have a coupon ID, save it on the model
        if (!$account->id) {
            $account->id = $accountRecord->id;
        }

        return true;
    }

    /**
     * Gets an accounts's record.
     *
     * @access private
     * @param int $accountId
     * @return Charge_AccountModel
     */
    private function _getAccountRecordById($accountId = null)
    {
        if ($accountId) {
            $accountRecord = Charge_AccountRecord::model()->findById($accountId);

            if (!$accountRecord) {
                $this->_noAccountExists($accountId);
            }
        } else {
            $accountRecord = new Charge_AccountRecord();
        }

        return $accountRecord;
    }

    /**
     * Throws a "No account exists" exception.
     *
     * @access private
     * @param int $accountId
     * @throws Exception
     */
    private function _noAccountExists($accountId)
    {
        throw new Exception(Craft::t('No account exists with the ID “{id}”', ['id' => $accountId]));
    }


}