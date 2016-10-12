<?php
namespace Craft;

use Cartalyst\Stripe\Stripe;

class Charge_StripeService extends BaseApplicationComponent
{
    protected $stripeRecord;

    public $stripeMode = 'test';
    public $stripePK = '';
    public $stripeSK = '';
    public $defaultCurrency = 'usd';
    private $stripeApiVersion = '2016-03-07';

    public $stripe = null;

    public function init($forceMode = '')
    {
        $plugin = craft()->plugins->getPlugin('charge');
        $plugin->initAutoloader();
        $pluginSettings = $plugin->getSettings();

        $defaultCurrency = $this->defaultCurrency;
        if (isset($pluginSettings->credentials['stripeDefaultCurrency'])) {
            $defaultCurrency = $pluginSettings->credentials['stripeDefaultCurrency'];
        }
        $this->defaultCurrency = $defaultCurrency;

        $mode = 'test';
        if (isset($pluginSettings->mode['stripeAccountMode']) && ($pluginSettings->mode['stripeAccountMode'] == 'live')) {
            $mode = 'live';
        }

        if ($forceMode != '') {
            $mode = $forceMode;
        }

        $this->stripeMode = $mode;
        $settingsKeyPK = 'stripe' . ucwords($this->stripeMode) . 'CredentialsPK';
        $settingsKeySK = 'stripe' . ucwords($this->stripeMode) . 'CredentialsSK';

        if (isset($pluginSettings->credentials[$settingsKeyPK])) {
            $this->stripePK = trim($pluginSettings->credentials[$settingsKeyPK]);
        }
        if (isset($pluginSettings->credentials[$settingsKeySK])) {
            $this->stripeSK = trim($pluginSettings->credentials[$settingsKeySK]);
        }

        if ($this->stripeSK != '') {
            $this->stripe = Stripe::make($this->stripeSK, $this->stripeApiVersion);
        }

        $this->validateConnection();
    }

    public function getMode()
    {
        return $this->stripeMode;
    }

    public function getStripePk()
    {
        return $this->stripePK;
    }

    public function validateConnectionModes()
    {
        if (!craft()->cache->get('chargeStripeConnectionModes')) {

            $status = ['test' => false, 'live' => false];
            $realMode = $this->stripeMode;

            $this->init('test');
            $status['test'] = $this->validateConnection('test');
            $this->init('live');
            $status['live'] = $this->validateConnection('live');
            $this->init($realMode);

            craft()->cache->set('chargeStripeConnectionModes', $status, 43200);
        } else {
            $status = craft()->cache->get('chargeStripeConnectionModes');
        }

        return $status;

    }

    private function validateConnection($key = '')
    {
        $key = 'chargeStripeConnection-'.$key;
        if (!craft()->cache->get($key)) {

            if ($this->stripe == null) $status = false;
            else {

                try {
                    $this->stripe->balance()->current();
                    craft()->charge_log->info('Validating connection details.', [], 'unset');

                    $status = true;

                } catch (\Exception $e) {
                    craft()->charge_log->info('Failed validating connection details.', [], 'unset');

                    $status = false;
                }
            }
            craft()->cache->set($key, $status, 43200);

        } else {
            $status = craft()->cache->get($key);
        }

        return $status;
    }
}