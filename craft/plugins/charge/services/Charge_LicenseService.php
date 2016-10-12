<?php
namespace Craft;

class Charge_LicenseService extends BaseApplicationComponent
{
    const Ping = 'https://squarebit.co.uk/actions/licensor/edition/ping';
    const GetLicenseInfo = 'https://squarebit.co.uk/actions/licensor/edition/getLicenseInfo';
    const RegisterPlugin = 'https://squarebit.co.uk/actions/licensor/edition/registerPlugin';
    const UnregisterPlugin = 'https://squarebit.co.uk/actions/licensor/edition/unregisterPlugin';
    const TransferPlugin = 'https://squarebit.co.uk/actions/licensor/edition/transferPlugin';

    private $plugin;
    private $pingStateKey = 'chargePhonedHome';
    private $pingCacheTime = 86400;
    private $pluginHandle = 'Charge';
    private $pluginVersion;
    private $licenseKey;


    public function init()
    {
        require craft()->path->getPluginsPath() . 'charge/etc/Charge_Edition.php';
        $this->plugin = craft()->plugins->getPlugin('charge');
        $this->pluginVersion = $this->plugin->getVersion();
        $this->licenseKey = $this->getLicenseKey();
    }

    public function ping()
    {
        if(craft()->request->isCpRequest()) {
            if (!craft()->cache->get($this->pingStateKey)) {
                $et = new Charge_Edition(static::Ping, $this->pluginHandle, $this->pluginVersion, $this->licenseKey);
                $etResponse = $et->phoneHome();
                craft()->cache->set($this->pingStateKey, true, $this->pingCacheTime);

                return $this->handleEtResponse($etResponse);
            }
        }
        return null;
    }


    public function isProEdition()
    {
        if ($this->getEdition() == 1) return true;

        return false;
    }

    public function getEdition()
    {
        $edition = 0;
        if(craft()->plugins->getPluginLicenseKeyStatus('Charge') == LicenseKeyStatus::Valid) {
            $edition = 1;
        }
        return $edition;
    }


    public function getLicenseKey()
    {
        $licenseKey = null;

        $settings = $this->plugin->getSettings();
        if (!isset($settings->licenseKey)) return $licenseKey;
        $licenseKey = $settings->licenseKey;

        return $licenseKey;
    }


    public function setLicenseKey($licenseKey)
    {
        $settings = ['licenseKey' => $licenseKey];
        craft()->plugins->savePluginSettings($this->plugin, $settings);
    }


    public function getLicenseInfo()
    {
        $et = new Charge_Edition(static::GetLicenseInfo, $this->pluginHandle, $this->pluginVersion, $this->licenseKey);
        $etResponse = $et->phoneHome(true);

        return $this->handleEtResponse($etResponse);
    }


    /**
     * Creates a new EtModel with provided JSON, and returns it if it's valid.
     *
     * @param array $attributes
     *
     * @return EtModel|null
     */
    public function decodeEtModel($attributes)
    {
        if ($attributes) {
            $attributes = JsonHelper::decode($attributes);

            if (is_array($attributes)) {
                $etModel = new Charge_LicenseModel($attributes);

                // Make sure it's valid. (At a minimum, localBuild and localVersion
                // should be set.)
                if ($etModel->validate()) {
                    return $etModel;
                }
            }
        }
        return null;
    }


    public function unregisterLicenseKey()
    {
        $et = new Charge_Edition(static::UnregisterPlugin, $this->pluginHandle, $this->pluginVersion, $this->licenseKey);
        $etResponse = $et->phoneHome(true);

        craft()->charge_license->setLicenseKey(null);
        craft()->plugins->setPluginLicenseKeyStatus('Charge', LicenseKeyStatus::Unknown);

        return $this->handleEtResponse($etResponse);
    }

    public function transferLicenseKey()
    {
        $et = new Charge_Edition(static::TransferPlugin, $this->pluginHandle, $this->pluginVersion, $this->licenseKey);
        $etResponse = $et->phoneHome(true);

        return $etResponse;
    }

    public function registerPlugin($licenseKey)
    {
        $et = new Charge_Edition(static::RegisterPlugin, $this->pluginHandle, $this->pluginVersion, $licenseKey);
        $etResponse = $et->phoneHome(true);

        // Handle the response
        return $this->handleEtResponse($etResponse);
    }

    /**
     * Returns a response based on the EtService response.
     *
     * @return bool|string The resonse from EtService
     */

    private function handleEtResponse($etResponse)
    {
        if (!empty($etResponse->data['success'])) {
            // Set the local details
            craft()->plugins->setPluginLicenseKeyStatus('Charge',LicenseKeyStatus::Valid);
            return true;
        } else {
            if (!empty($etResponse->errors)) {
                switch ($etResponse->errors[0]) {
                    case 'nonexistent_plugin_license':
                        craft()->plugins->setPluginLicenseKeyStatus('Charge',LicenseKeyStatus::Invalid);
                        break;
                    case 'plugin_license_in_use':
                        craft()->plugins->setPluginLicenseKeyStatus('Charge',LicenseKeyStatus::Mismatched);
                        break;
                    default:
                        craft()->plugins->setPluginLicenseKeyStatus('Charge',LicenseKeyStatus::Unknown);
                }
            } else {
                //$error = Craft::t('An unknown error occurred.');
                return false;
            }

            return true;
        }
    }
}
