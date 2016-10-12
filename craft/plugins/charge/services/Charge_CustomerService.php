<?php
namespace Craft;

use Cartalyst\Stripe\Exception\NotFoundException;

class Charge_CustomerService extends BaseApplicationComponent
{
    public $customer = null;
    private $user = null;

    public function init()
    {
        $this->user = craft()->userSession->getUser();
    }

    public function findByCurrentUser()
    {
        if ($this->customer != null) return $this->customer;

        if ($this->user == null) return null;

        $this->customer = $this->findByUserId($this->user->id);

        return $this->customer;
    }

    public function findOrCreate($email = '', $extra = [], ChargeModel $chargeModel = null)
    {
        if ($chargeModel != null) {
            // Do we have a customerId?
            if ($chargeModel->customerId != '') {
                $this->customer = $this->findById($chargeModel->customerId);
                if ($this->customer != null) return $this->customer;
            }
        }
        // Just a quick check to see if we have a customer
        // If we can't find one, this will return null safely
        $this->customer = $this->find();

        // We need to create a customer
        if ($this->customer == null) {
            try {
                $this->customer = $this->create($email, $extra);
            } Catch (Exception $e) {
                // Failed to create
                craft()->charge_log->error('Failed to create a new customer');

                return false;
            }
        } else {
            craft()->charge_log->api('Found existing customer', ['customer' => $this->customer]);
        }

        // Update and flesh out
        $this->customer->update($extra);

        return $this->customer;
    }


    /*
     * Find
     *
     * Finds a customer record if one exists for the logged in user
     */
    public function find()
    {
        if ($this->user == null) return null;

        return $this->findByUserId($this->user->id);
    }


    public function create($email = '', $extra = [])
    {
        if ($email == '') {
            craft()->charge_log->exception('Cannot create a new Stripe customer without a valid email');
            throw new Exception(Craft::t('Cannot create a new Stripe customer without a valid email'));
        }

        $cus = [];
        $cus['email'] = $email;
        $cus['metadata'] = [];
        if ($this->user != null) {
            $cus['metadata']['Craft User Id'] = $this->user->id;
        } else {
            $cus['metadata']['Craft User Id'] = 'Guest';
        }
        if(isset($extra['metadata'])) {
            $cus['metadata'] = array_merge($cus['metadata'], $extra['metadata']);
        }

        $customer = craft()->charge->stripe->customers()->create($cus);

        $customerModel = Charge_CustomerModel::populateModel(
            ['email'    => $email,
             'name'     => (isset($extra['name']) ? $extra['name'] : ''),
             'stripeId' => $customer['id'],
             'stripe'   => $customer,
             'mode'     => ($customer['livemode'] ? 'live' : 'test')]);
        if ($this->user != null) {
            $customerModel->userId = $this->user->id;
        }

        // Also make a local record of this for later use
        $customerRecord = $this->getCustomerRecordById();
        $customerRecord->mode = $customerModel->mode;
        $customerRecord->email = $customerModel->email;
        $customerRecord->name = $customerModel->name;
        $customerRecord->userId = $customerModel->userId;
        $customerRecord->stripeId = $customerModel->stripeId;

        if ($customerRecord->validate()) {
            $customerRecord->save();
            $customerModel->id = $customerRecord->id;
        }

        craft()->charge_log->api('Created Customer', ['customer' => $customerModel]);

        return $customerModel;
    }

    public function findById($customerId, $updateStripe = false)
    {
        $customerRecord = Charge_CustomerRecord::model()->findByAttributes(
            ['id' => $customerId]);
        if ($customerRecord == null) return null;

        $customerModel = Charge_CustomerModel::populateModel($customerRecord);

        $stripeCustomer = $this->updateStripe($customerModel);
        if($stripeCustomer == null) {
            // the customer doesn't exist on the api, or there was another failure
            // either way, we need to create a new customer
            return null;
        }

        return $customerModel;
    }

    public function findByUserId($userId, $updateStripe = false)
    {
        $customerRecord = Charge_CustomerRecord::model()->findByAttributes(
            ['userId' => $userId,
             'mode'   => craft()->charge->getMode()]);
        if ($customerRecord == null) return null;

        $customerModel = Charge_CustomerModel::populateModel($customerRecord);

        $stripeCustomer = $this->updateStripe($customerModel);
        if($stripeCustomer == null) {
            // the customer doesn't exist on the api, or there was another failure
            // either way, we need to create a new customer
            return null;
        }

        return $customerModel;
    }

    private function updateStripe($customerModel)
    {
        // We have a record. ReVerify and pull from Stripe
        try {
            $stripeCustomer = craft()->charge->stripe->customers()->find($customerModel->stripeId);
        } catch (NotFoundExceptions $e) {
            // Customer wasn't found on the api
            // Mark as bad customer and return null
            // @todo - clear local record, or otherwise mark
            craft()->charge_log->exception('Cannot find customer on api', ['customer' => $customerModel]);

            return null;
        } catch (\Exception $e) {
            // Some other api error
            craft()->charge_log->exception('API Excpetion while finding customer', ['customer' => $customerModel]);

            return null;
        }

        $customerModel->stripe = $stripeCustomer;

        craft()->charge_log->api('Updated stripe customer details', ['customer' => $customerModel]);
        return $customerModel;
    }

    /**
     * Gets a customers's record.
     *
     * @param int $customerId
     *
     * @throws Exception
     * @return Charge_CustomerRecord
     */
    public function getCustomerRecordById($customerId = null)
    {
        if ($customerId) {
            $customerRecord = Charge_CustomerRecord::model()->findById($customerId);

            if (!$customerRecord) {
                throw new Exception(Craft::t('No customer exists with the ID “{id}”.', ['id' => $customerId]));
            }
        } else {
            $customerRecord = new Charge_CustomerRecord();
        }

        return $customerRecord;
    }
}