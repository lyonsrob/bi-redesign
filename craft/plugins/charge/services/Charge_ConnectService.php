<?php
namespace Craft;

class Charge_ConnectService extends BaseApplicationComponent
{
    public $plugin;
    public $settings;
    public $enabled = false;

    private $connectBase = 'https://connect.stripe.com/oauth/authorize';
    private $authBase = 'https://connect.stripe.com/oauth/token';
    private $connectParams = ['response_type' => 'code', 'scope' => 'read_write', 'client_id' => '', 'redirect_uri' => ''];
    private $redirectPath = 'charge/connect/oauthCallback';
    private $clientId = '';

    public $account;

    public function init()
    {
        $this->plugin = craft()->plugins->getPlugin('charge');
        $this->settings = (isset($this->plugin->settings['connect']) ? $this->plugin->settings['connect'] : []);

        if (isset($this->settings['enabled']) && $this->settings['enabled'] == true) {

            $key = 'devClientId';
            if(craft()->charge->getMode() == 'live') {
                $key = 'prodClientId';
            }
            // Only enabled when we have an appropriate client_id
            if(isset($this->settings[$key]) && $this->settings[$key] != '') {
                $this->enabled = true;
                $this->clientId = $this->settings[$key];
            }
        }
    }

    public function getConnectEnabledStatus()
    {
        return $this->enabled;
    }

    public function getAccountStatus($userId = '')
    {
        if (!$this->enabled) {
            return 'disabled';
        }

        if($userId == '') {
            if (craft()->userSession->isGuest()) {
                return 'guest';
            }

            $user = craft()->userSession->getUser();
            $userId = $user->id;
        }

        // Ok. enabled, and not a guest. Actually check
        $this->account = $this->getAccountForUser($userId);

        if(!is_null($this->account)) return 'connected';
        return 'unconnected';
    }

    public function getAccountStatusByUserId($userId)
    {
        return $this->getAccountStatus($userId);
    }

    public function getConnectUrl()
    {
        if(!$this->getAccountStatus() == 'unconnected') {
            return ''; // You can only get a button if you can actually connect
        }

        $this->connectParams['client_id'] = $this->clientId;
        $this->connectParams['redirect_uri'] = $this->getRedirectUrl();

        $url = $this->connectBase;
        $params = [];
        foreach($this->connectParams as $key => $val) {
            if($val != '') {
                $params[] = $key.'='.$val;
            }
        }
        $url = $url . '?' . implode('&', $params);
        return $url;
    }

    public function getRedirectUrl()
    {
        $path = $this->redirectPath;
        $path = craft()->config->get('actionTrigger').'/'.trim($path, '/');

        return UrlHelper::getSiteUrl($path);
    }

    public function disconnectAccount(Charge_AccountModel $account)
    {
        // We just blindingly delete our local record of this.
        // No need to be fancy about things.
        $accountRecord = $this->_getAccountRecordById($account->id);

        return $accountRecord->deleteByPk($account->id);
    }

    public function handleOauthCallback($code)
    {
        // OAuths only if they're in the right mode
        if($this->getAccountStatus() != 'unconnected') {
            return false;
        }

        $user = craft()->userSession->getUser();
        if(is_null($user)) {
            return false;
        }

        $response = $this->getAuthCode($code);
        if($response == false) {
            return false;
        }

        // Valid. Create a new account record
        $account = new Charge_AccountModel();
        $account->userId = $user->id;

        $account->accessToken = $response['access_token'];
        $account->livemode = $response['livemode'];
        $account->refreshToken = $response['refresh_token'];
        $account->tokenType = $response['token_type'];
        $account->stripePublishableKey = $response['stripe_publishable_key'];
        $account->stripeUserId = $response['stripe_user_id'];
        $account->scope = $response['scope'];
        $account->enabled = true;

        $saved = craft()->charge_account->saveAccount($account);
        if(!$saved) {
            return false;
        }

        return $account;
    }


    private function getAuthCode($code)
    {
        $body = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'code' => $code,
            'client_secret' => craft()->charge_stripe->stripeSK
        ];


        try {
            $client = new \Guzzle\Http\Client();
            $request = $client->post($this->authBase);
            $request->setBody($body);

            $response = $request->send();

            if ($response->isSuccessful()) {
                $body = $response->json();
                return $body;
            }

        } catch(Exception $e) {
            // Nope
            ChargePlugin::log("Exception while performing connect callback");
            return false;
        }

        return false;
    }


    public function getAll()
    {
        $accountRecords = Charge_AccountRecord::model()->findAll();

        return Charge_AccountModel::populateModels($accountRecords);
    }


    public function getAccountById($id)
    {
        $accountModel = $this->_getAccountModelById($id);

        return $accountModel;
    }


    public function getAccountForUser($userId)
    {
        $livemode = false;
        if(craft()->charge->getMode() == 'live') $livemode = true;

        $account = Charge_AccountRecord::model()->findByAttributes([
            'userId' => $userId,
            'livemode' => $livemode
        ]);

        if ($account) {
            return Charge_AccountModel::populateModel($account);
        }

        return null;
    }

    public function getAllActiveAccounts()
    {
        $livemode = false;
        if(craft()->charge->getMode() == 'live') $livemode = true;

        $accounts = Charge_AccountRecord::model()->findAllByAttributes([
            'livemode' => $livemode
        ]);

        if ($accounts) {
            return Charge_AccountModel::populateModels($accounts);
        }

        return [];
    }

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
        throw new Exception(Craft::t('No stripe accounts exists with the ID “{id}”', ['id' => $accountId]));
    }




}