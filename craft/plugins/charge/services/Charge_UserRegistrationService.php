<?php
namespace Craft;

class Charge_UserRegistrationService extends BaseApplicationComponent
{
    public $plugin;
    public $settings;
    public $enabled = false;
    public $account = null;
    public $registerUser = false;


    public function init()
    {
        $this->plugin = craft()->plugins->getPlugin('charge');
        $this->settings = (isset($this->plugin->settings['userreg']) ? $this->plugin->settings['userreg'] : []);

        if (isset($this->settings['enabled']) && $this->settings['enabled'] == true) {
            if(craft()->charge_license->isProEdition()) {
                $this->enabled = true;
            }
        }
    }

    public function postRegisterGuest(ChargeModel &$chargeModel)
    {
        if(!$this->registerUser) return;

        // We need to update the state of the user account
        // Send any activation emails necessary, and update the charge model and record
        if($this->account == null) return false;

        craft()->charge_log->note('Finishing of guest registration after charge successfully completed');

        $user = $this->account;

        // Make our guest record marker for later end-of-life handling
        $this->recordGuestAssociation($user->id, $chargeModel->id);

        craft()->users->unsuspendUser($user);

        if(craft()->systemSettings->getSetting('users', 'requireEmailVerification')) {
            $user->pending = true;
            craft()->users->saveUser($user);

            craft()->charge_log->note('User activation email sent out for new user account');
            try {
                craft()->users->sendActivationEmail($user);
            } catch (\phpmailerException $e) {
                craft()->userSession->setError(Craft::t('User saved, but couldn’t send verification email. Check your email settings.'));
            }
        } else {
            craft()->charge_log->note('User account activated');
            craft()->users->activateUser($user);
        }

        // Is this public registration, and was the user going to be activated automatically?

        $this->_maybeLoginUserAfterAccountActivation($user);

        // Now we also need to mark the charge as by that user
        $this->_updateRecordsWithUserAssociation($chargeModel, $user);

        craft()->charge_log->success('Guest registration complete. Newly created user is now associated to the parent charge.', ['userId' => $chargeModel->userId]);
    }

    public function removePartialGuestRegisterIfPresent()
    {
        if($this->account == null) return;

        craft()->charge_log->note('Removing partially created guest account after charge failed');

        // Looks like there's a user account partially created. Clear it completely just to be safe
        craft()->users->deleteUser($this->account);

        return;
    }

    public function preRegisterGuestIfNeeded(ChargeModel &$chargeModel)
    {
        if(!$this->enabled) return false;

        // Only need this if the user is a guest anyway
        if(!craft()->userSession->isGuest()) return false;

        // Ok. Let's see if we actually _want_ to register them
        if(!$this->proceedWithRegistration()) return false;


        craft()->charge_log->action('Guest registration triggered. Registering a new user for this charge');

        $this->registerUser = true;

        // Ok, we're going to register the user
        $result = $this->registerGuestPending($chargeModel);

        return $result;
    }



    private function registerGuestPending(ChargeModel $chargeModel)
    {
        $newEmail = $chargeModel->customerEmail;

        $user = new UserModel();
        $user->email = $newEmail;
        $user->newPassword = craft()->request->getPost('password', '');
        // Is the site set to use email addresses as usernames?
        if (craft()->config->get('useEmailAsUsername'))
        {
            $user->username    =  $user->email;
        }
        else
        {
            $user->username    = craft()->request->getPost('username', ($user->username ? $user->username : $user->email));
        }


        $user->firstName       = craft()->request->getPost('firstName', $user->firstName);
        $user->lastName        = craft()->request->getPost('lastName', $user->lastName);
        $user->preferredLocale = craft()->request->getPost('preferredLocale', $user->preferredLocale);
        $user->weekStartDay    = craft()->request->getPost('weekStartDay', $user->weekStartDay);
        $user->suspended = true; // Lock the user for now, unlock it post payment

        // If email verification is required, then new users will be saved in a pending state,
        // even if an admin is doing this and opted to not send the verification email
        $requireEmailVerification = craft()->systemSettings->getSetting('users', 'requireEmailVerification');
        $verifyNewEmail = false;

        if ($requireEmailVerification)
        {
            $verifyNewEmail = true;
        }

        // If this is Craft Pro, grab any profile content from post
        $craftEdition = craft()->getEdition();
        if ($craftEdition == Craft::Pro)
        {
            $user->setContentFromPost('user');
        }
        // Validate and save!
        // ---------------------------------------------------------------------
        $imageValidates = true;
        $userPhoto = UploadedFile::getInstanceByName('userPhoto');

        if ($userPhoto && !ImageHelper::isImageManipulatable($userPhoto->getExtensionName()))
        {
            $imageValidates = false;
            $user->addError('userPhoto', Craft::t("The user photo provided is not an image."));
        }

        if ($imageValidates && craft()->users->saveUser($user))
        {
            // Save the user's photo, if it was submitted
            $this->_processUserPhoto($user);

            // Assign them to the default user group
            craft()->userGroups->assignUserToDefaultGroup($user);

            // We'll only send out the verification email if needed later
            // right now we're setting them to a pending state only
            // if the payment succeeds, we'll go and test the state at the end.
            $this->account = $user;
            craft()->charge_log->success('New User account created in a suspended state, waiting for payment to complete', ['account' => $user]);
            return true;
        }
        else
        {
            craft()->charge_log->error('Failed to properly create the user account.', ['errors' => $user->getErrors()]);
            $user->addError('general', Craft::t("Couldn't save your new user account"));
            $this->account = $user;
            return false;
        }

    }


    private function proceedWithRegistration()
    {
        $defaultBehaviour = null;

        if(isset($this->settings['defaultBehaviour'])) {
            $defaultBehaviour = $this->settings['defaultBehaviour'];
        }

        if($defaultBehaviour == 'always') return true;

        if($defaultBehaviour == 'yes') {
            $value = craft()->request->getPost('createAccount');
            if($value == 'no' || $value == 'n' || $value == '0' || $value == 'false') {
                return false;
            }

            return true;
        }

        if($defaultBehaviour == 'no') {

            $value = craft()->request->getPost('createAccount');

            if($value == 'yes' || $value == 'y' || $value == '1' || $value == 'true') {
                return true;
            }
            return false;
        }

        return false;
    }


    /**
     * @param $user
     *
     * @return null
     */
    private function _processUserPhoto($user)
    {
        // Delete their photo?
        if (craft()->request->getPost('deleteUserPhoto'))
        {
            craft()->users->deleteUserPhoto($user);
        }

        // Did they upload a new one?
        if ($userPhoto = UploadedFile::getInstanceByName('userPhoto'))
        {
            craft()->users->deleteUserPhoto($user);
            $image = craft()->images->loadImage($userPhoto->getTempName());
            $imageWidth = $image->getWidth();
            $imageHeight = $image->getHeight();

            $dimension = min($imageWidth, $imageHeight);
            $horizontalMargin = ($imageWidth - $dimension) / 2;
            $verticalMargin = ($imageHeight - $dimension) / 2;
            $image->crop($horizontalMargin, $imageWidth - $horizontalMargin, $verticalMargin, $imageHeight - $verticalMargin);

            craft()->users->saveUserPhoto(AssetsHelper::cleanAssetName($userPhoto->getName()), $image, $user);

            IOHelper::deleteFile($userPhoto->getTempName());
        }
    }

    /**
     * Possibly log a user in right after they were activate, if Craft is configured to do so.
     *
     * @param UserModel $user The user that was just activated
     *
     * @return bool Whether the user was just logged in
     */
    private function _maybeLoginUserAfterAccountActivation(UserModel $user)
    {
        if (craft()->config->get('autoLoginAfterAccountActivation') === true)
        {
            craft()->charge_log->note('autoLoginAfterAccountActivation is enabled, logging new user into their account.');
            return craft()->userSession->loginByUserId($user->id, false, true);
        }
        else
        {
            return false;
        }
    }

    private function recordGuestAssociation($userId, $chargeId)
    {
        $record = new Charge_GuestRegisterRecord();
        $record->userId = $userId;
        $record->chargeId = $chargeId;

        $record->insert();
    }


    public function triggerEndOfLife(ChargeModel $chargeModel)
    {
        // See if we have an associated user first
        $record = Charge_GuestRegisterRecord::model()->findByAttributes(['chargeId' => $chargeModel->id]);
        if($record == null) return;

        // Get the user account
        $account = craft()->users->getUserById($record->userId);
        if($account == null) return; // Can't find that user!!

        // We have a user to end-of-life
        // Now lets check what the settings say we should do
        if(!isset($this->settings['endOfLifeBehaviour'])) return; // No behaviour set.
        $behaviour = $this->settings['endOfLifeBehaviour'];

        craft()->charge_log->note('End-of-life triggered for associated user account to Charge, with behaviour : '.$behaviour);

        $result = $this->preformEndOfLifeBehaviour($behaviour, $account);
        if($result) {
            // Remove our guest reg marker
            $record->delete();
        }
        return $result;
    }


    private function preformEndOfLifeBehaviour($behaviour, UserModel $account)
    {
        $result = false;

        switch ($behaviour) {
            case 'delete' : {
                craft()->charge_log->note('Deleting User account for charge', ['account' => $account]);
                try {
                    $result = craft()->users->deleteUser($account);

                    if ($result) {
                        craft()->charge_log->success('User account deleted', ['account' => $account]);
                    } else {
                        craft()->charge_log->error('Tried to delete user account but failed.', ['account' => $account]);
                    }

                } catch (Exception $e) {
                    craft()->charge_log->error('Tried to delete user account but failed with exception', ['exception' => $e->getMessage()]);

                    $result = false;
                }

                break;
            }
            case 'suspend' : {
                craft()->charge_log->note('Suspending User account for charge', ['account' => $account]);
                try {
                    $result = craft()->users->suspendUser($account);
                    if ($result) {
                        craft()->charge_log->success('User account suspended', ['account' => $account]);
                    } else {
                        craft()->charge_log->error('Tried to suspend user account but failed.', ['account' => $account]);
                    }

                } catch (Exception $e) {
                    craft()->charge_log->error('Tried to suspend user account but failed with exception', ['exception' => $e->getMessage()]);

                    $result = false;
                }

                break;
            }
            case 'ignore' :
            default : {
                craft()->charge_log->note('Ignoring end of life trigger for account based on guest registration settings');
                $result = true;
            }
        }

        return $result;
    }


    /*
     * This will associate the passed Charge model to the user,
     * and any subscriptions or other records related to the Charge too
     */
    private function _updateRecordsWithUserAssociation(ChargeModel $chargeModel, $user)
    {
        // Update the Base Charge
        $userId = $user->id;
        $chargeId = $chargeModel->id;

        $chargeRecord = ChargeRecord::model()->findById($chargeId);
        if($chargeRecord) {
            $chargeRecord->userId = $userId;
            $chargeRecord->update();
        }

        // Update the Charge Customer
        $customerRecord = Charge_CustomerRecord::model()->findById($chargeModel->customerId);
        if($customerRecord) {
            $customerRecord->userId = $userId;
            $customerRecord->update();
        }

        // Payments
        $paymentRecords = Charge_PaymentRecord::model()->findAllByAttributes(['chargeId' => $chargeId]);
        foreach($paymentRecords as $paymentRecord) {
            $paymentRecord->userId = $userId;
            $paymentRecord->update();
        }

        // Subscriptions
        $subscriptionRecords = Charge_SubscriptionRecord::model()->findAllByAttributes(['chargeId' => $chargeId]);
        foreach($subscriptionRecords as $subscriptionRecord) {
            $subscriptionRecord->userId = $userId;
            $subscriptionRecord->update();
        }


        // Subscriber
        $subscriberRecords = Charge_SubscriberRecord::model()->findAllByAttributes(['chargeId' => $chargeId]);
        foreach($subscriberRecords as $subscriberRecord) {
            $subscriberRecord->userId = $userId;
            $subscriberRecord->update();
        }

    }




}