<?php
namespace Craft;

class m160509_000000_charge_update200 extends BaseMigration
{
    public function safeUp()
    {

        // 1. New Tables
        // Account Table
        // Customer Table
        // Email Table
        // Log Table
        // Membership Subscription Email Table
        // Membership Subscription Table
        // Payment Table
        // Subscriber Table
        // Subscription Record Table

        $this->createAccountTable();
        $this->createCustomerTable();
        $this->createEmailTable();
        $this->createLogTable();
        $this->createMembershipSubscriptionTable();
        $this->createMembershipSubscriptionEmailsTable();
        $this->createPaymentsTable();
        $this->createSubscriberTable();
        $this->createSubscriptionsTable();


        // 2. Updated Tables
        // Charge Update
        $this->updateChargesTable();
        $this->updateChargesContent();
        $this->updateCustomerRecords();
        $this->updateChargeRequest();

        return true;
    }


    private function createAccountTable()
    {
        Craft::log('Checking if the charge_accounts table exists');
        $accountTable = $this->dbConnection->schema->getTable('{{charge_accounts}}');

        if (is_null($accountTable)) {
            Craft::log('Creating charge_acounts table');

            $cols = [];
            $cols['userId'] = ['column' => ColumnType::Int];
            $cols['accessToken'] = ['column' => ColumnType::Varchar];
            $cols['livemode'] = ['column' => ColumnType::Bool, 'default' => false];
            $cols['refreshToken'] = ['column' => ColumnType::Varchar];
            $cols['tokenType'] = ['column' => ColumnType::Varchar];
            $cols['stripePublishableKey'] = ['column' => ColumnType::Varchar];
            $cols['stripeUserId'] = ['column' => ColumnType::Varchar];
            $cols['scope'] = ['column' => ColumnType::Varchar];
            $cols['enabled'] = ['column' => ColumnType::Bool, 'default' => false];

            craft()->db->createCommand()->createTable('charge_accounts', $cols, null, true);
        }
    }

    private function createCustomerTable()
    {
        Craft::log('Checking if the charge_customers table exists');
        $customerTable = $this->dbConnection->schema->getTable('{{charge_customers}}');

        if (is_null($customerTable)) {
            Craft::log('Creating charge_customers table');

            $cols = [];
            $cols['stripeId'] = ['column' => ColumnType::Varchar];
            $cols['mode'] = ['column' => ColumnType::Enum, 'values' => 'test,live', 'default' => 'test', 'required' => true];
            $cols['userId'] = ['column' => ColumnType::Int];
            $cols['email'] = ['column' => ColumnType::Varchar];
            $cols['name'] = ['column' => ColumnType::Varchar];

            craft()->db->createCommand()->createTable('charge_customers', $cols, null, true);
        }
    }

    private function createEmailTable()
    {
        Craft::log('Checking if the charge_emails table exists');
        $emailTable = $this->dbConnection->schema->getTable('{{charge_emails}}');

        if (is_null($emailTable)) {
            Craft::log('Creating charge_emails table');

            $cols = [];
            $cols['name'] = ['column' => ColumnType::Varchar, 'required' => true];
            $cols['handle'] = ['column' => ColumnType::Varchar, 'required' => true, 'unique' => true];
            $cols['subject'] = ['column' => ColumnType::Varchar, 'required' => true];
            $cols['to'] = ['column' => ColumnType::Varchar, 'required' => true];
            $cols['bcc'] = ['column' => ColumnType::Varchar];
            $cols['enabled'] = ['column' => ColumnType::Bool, 'required' => true];
            $cols['templatePath'] = ['column' => ColumnType::Varchar, 'required' => true];

            craft()->db->createCommand()->createTable('charge_emails', $cols, null, true);
            craft()->db->createCommand()->createIndex('charge_emails', 'handle', true);
        }
    }

    private function createLogTable()
    {
        Craft::log('Checking if the charge_logs table exists');
        $logTable = $this->dbConnection->schema->getTable('{{charge_logs}}');

        if (is_null($logTable)) {
            Craft::log('Creating charge_logs table');

            $cols = [];
            $cols['mode'] = ['column' => ColumnType::Enum, 'values' => 'test,live,unset', 'default' => 'test', 'required' => true];
            $cols['level'] = ['column' => ColumnType::Varchar];
            $cols['requestKey'] = ['column' => ColumnType::Varchar];
            $cols['type'] = ['column' => ColumnType::Varchar];
            $cols['source'] = ['column' => ColumnType::Varchar];
            $cols['extra'] = ['column' => ColumnType::Text];

            craft()->db->createCommand()->createTable('charge_logs', $cols, null, true);
        }
    }

    private function createMembershipSubscriptionTable()
    {
        Craft::log('Checking if the charge_membershipsubscriptions table exists');
        $membershipSubscriptionTable = $this->dbConnection->schema->getTable('{{charge_membershipsubscriptions}}');

        if (is_null($membershipSubscriptionTable)) {
            Craft::log('Creating charge_membershipsubscriptions table');

            $cols = [];
            $cols['name'] = ['column' => ColumnType::Varchar, 'required' => true];
            $cols['handle'] = ['column' => ColumnType::Varchar, 'required' => true, 'unique' => true];
            $cols['enabled'] = ['column' => ColumnType::Bool, 'required' => true];
            $cols['activeUserGroup'] = ['column' => ColumnType::Varchar, 'required' => true];

            craft()->db->createCommand()->createTable('charge_membershipsubscriptions', $cols, null, true);
            craft()->db->createCommand()->createIndex('charge_membershipsubscriptions', 'handle', true);
        }
    }

    private function createMembershipSubscriptionEmailsTable()
    {
        Craft::log('Checking if the charge_membershipsubscription_emails table exists');
        $membershipSubscriptionEmailTable = $this->dbConnection->schema->getTable('{{charge_membershipsubscription_emails}}');

        if (is_null($membershipSubscriptionEmailTable)) {
            Craft::log('Creating charge_membershipsubscription_emails table');

            $cols = [];
            $cols['membershipSubscriptionId'] = ['column' => ColumnType::Int, 'required' => true];
            $cols['emailId'] = ['column' => ColumnType::Int, 'required' => true];
            $cols['type'] = ['column' => ColumnType::Varchar, 'required' => true];

            craft()->db->createCommand()->createTable('charge_membershipsubscription_emails', $cols, null, true);

            craft()->db->createCommand()->addForeignKey(
                'charge_membershipsubscription_emails',
                'membershipSubscriptionId',
                'charge_membershipsubscriptions',
                'id',
                'CASCADE');

            craft()->db->createCommand()->addForeignKey(
                'charge_membershipsubscription_emails',
                'emailId',
                'charge_emails',
                'id',
                'CASCADE');
        }
    }

    private function createPaymentsTable()
    {
        Craft::log('Checking if the charge_payments table exists');
        $paymentTable = $this->dbConnection->schema->getTable('{{charge_payments}}');

        if (is_null($paymentTable)) {
            Craft::log('Creating charge_payments table');

            $cols = [];
            $cols['stripeId'] = ['column' => ColumnType::Varchar];
            $cols['customerId'] = ['column' => ColumnType::Varchar];
            $cols['mode'] = ['column' => ColumnType::Enum, 'values' => 'test,live', 'default' => 'test', 'required' => true];
            $cols['amount'] = ['column' => ColumnType::Int];
            $cols['amountRefunded'] = ['column' => ColumnType::Int];
            $cols['status'] = ['column' => ColumnType::Varchar];
            $cols['refunded'] = ['column' => ColumnType::Varchar];
            $cols['paid'] = ['column' => ColumnType::Varchar];
            $cols['captured'] = ['column' => ColumnType::Varchar];
            $cols['invoiceId'] = ['column' => ColumnType::Varchar];
            $cols['receiptEmail'] = ['column' => ColumnType::Varchar];
            $cols['failureCode'] = ['column' => ColumnType::Varchar];
            $cols['failureMessage'] = ['column' => ColumnType::Varchar];
            $cols['currency'] = ['column' => ColumnType::Varchar];
            $cols['userId'] = ['column' => ColumnType::Int];
            $cols['chargeId'] = ['column' => ColumnType::Int];

            $cols['cardName'] = ['column' => ColumnType::Varchar];
            $cols['cardAddressLine1'] = ['column' => ColumnType::Varchar];
            $cols['cardAddressLine2'] = ['column' => ColumnType::Varchar];
            $cols['cardAddressCity'] = ['column' => ColumnType::Varchar];
            $cols['cardAddressState'] = ['column' => ColumnType::Varchar];
            $cols['cardAddressZip'] = ['column' => ColumnType::Varchar];
            $cols['cardAddressCountry'] = ['column' => ColumnType::Varchar];
            $cols['cardLast4'] = ['column' => ColumnType::Varchar];
            $cols['cardType'] = ['column' => ColumnType::Varchar];
            $cols['cardExpMonth'] = ['column' => ColumnType::Varchar];
            $cols['cardExpYear'] = ['column' => ColumnType::Varchar];

            craft()->db->createCommand()->createTable('charge_payments', $cols, null, true);
            craft()->db->createCommand()->createIndex('charge_payments', 'userId');
            craft()->db->createCommand()->createIndex('charge_payments', 'chargeId');

        }
    }

    private function createSubscriptionsTable()
    {
        Craft::log('Checking if the charge_subscriptions table exists');
        $subscriptionsTable = $this->dbConnection->schema->getTable('{{charge_subscriptions}}');

        if (is_null($subscriptionsTable)) {
            Craft::log('Creating charge_subscriptions table');


            $cols = [];
            $cols['userId'] = ['column' => ColumnType::Int];
            $cols['customerId'] = ['column' => ColumnType::Varchar];
            $cols['chargeId'] = ['column' => ColumnType::Int];
            $cols['stripeId'] = ['column' => ColumnType::Varchar];
            $cols['mode'] = ['column' => ColumnType::Enum, 'values' => 'test,live', 'default' => 'test', 'required' => true];
            $cols['status'] = ['column' => ColumnType::Varchar];
            $cols['start'] = ['column' => ColumnType::Int];
            $cols['cancelAtPeriodEnd'] = ['column' => ColumnType::Bool];
            $cols['currentPeriodStart'] = ['column' => ColumnType::Int];
            $cols['currentPeriodEnd'] = ['column' => ColumnType::Int];
            $cols['endedAt'] = ['column' => ColumnType::Int];
            $cols['trialStart'] = ['column' => ColumnType::Int];
            $cols['trialEnd'] = ['column' => ColumnType::Int];
            $cols['canceledAt'] = ['column' => ColumnType::Int];
            $cols['quantity'] = ['column' => ColumnType::Int];
            $cols['applicationFeePercent'] = ['column' => ColumnType::Int];
            $cols['discount'] = ['column' => ColumnType::Int];
            $cols['taxPercent'] = ['column' => ColumnType::Int];
            $cols['planAmount'] = ['column' => ColumnType::Int];
            $cols['planName'] = ['column' => ColumnType::Varchar];
            $cols['planInterval'] = ['column' => ColumnType::Varchar];
            $cols['planIntervalCount'] = ['column' => ColumnType::Int];
            $cols['planTrialPeriodDays'] = ['column' => ColumnType::Int];
            $cols['planCurrency'] = ['column' => ColumnType::Varchar];
            $cols['planStripeId'] = ['column' => ColumnType::Varchar];


            craft()->db->createCommand()->createTable('charge_subscriptions', $cols, null, true);


            craft()->db->createCommand()->addForeignKey(
                'charge_subscriptions',
                'chargeId',
                'charges',
                'id',
                'CASCADE');

            craft()->db->createCommand()->addForeignKey(
                'charge_subscriptions',
                'userId',
                'users',
                'id',
                'CASCADE');
        }

    }

    private function createSubscriberTable()
    {
        Craft::log('Checking if the charge_subscriber table exists');
        $subscriberTable = $this->dbConnection->schema->getTable('{{charge_subscriber}}');

        if (is_null($subscriberTable)) {
            Craft::log('Creating charge_subscriber table');


            $cols = [];
            $cols['userId'] = ['column' => ColumnType::Int, 'required' => true];
            $cols['chargeId'] = ['column' => ColumnType::Int, 'required' => true];
            $cols['membershipSubscriptionId'] = ['column' => ColumnType::Int, 'required' => true];
            $cols['status'] = ['column' => ColumnType::Varchar, 'required' => true];


            craft()->db->createCommand()->createTable('charge_subscriber', $cols, null, true);

            craft()->db->createCommand()->addForeignKey(
                'charge_subscriber',
                'userId',
                'users',
                'id',
                'CASCADE');

            craft()->db->createCommand()->addForeignKey(
                'charge_subscriber',
                'chargeId',
                'charges',
                'id',
                'CASCADE');
            craft()->db->createCommand()->addForeignKey(
                'charge_subscriber',
                'membershipSubscriptionId',
                'charge_membershipsubscriptions',
                'id',
                'CASCADE');
        }

    }

    private function updateChargesTable()
    {
        $chargesTable = $this->dbConnection->schema->getTable('{{charges}}');

        if ($chargesTable->getColumn('type') === null) {
            $this->addColumnAfter('charges',
                'type',
                ['column' => ColumnType::Enum, 'values' => 'one-off,recurring', 'required' => true],
                'id');
        }

        if ($chargesTable->getColumn('customerId') === null) {
            $this->addColumnAfter('charges',
                'customerId',
                ['column' => ColumnType::Int],
                'id');
        }
        if ($chargesTable->getColumn('actions') === null) {
            $this->addColumnAfter('charges',
                'actions',
                ['column' => ColumnType::Text],
                'id');
        }
    }


    private function updateChargesContent()
    {
        // Get all the rows in the charges table without a content row
        $sql = "SELECT charges.id
            FROM craft_charges charges
                LEFT JOIN craft_content content ON charges.id = content.elementId
                WHERE content.id IS NULL";


        $results = craft()->db->createCommand($sql)->queryAll();

        $temp = [];
        $locale = craft()->language;
        foreach ($results as $row) {
            $uuid = StringHelper::UUID();

            $temp[] = '(' . $row['id'] . ', \'' . $locale . '\', \'' . $uuid . '\')';
        }

        if (empty($temp)) return true;

        $query = "INSERT INTO craft_content (elementId, locale, uid) VALUES ";
        $query .= implode(', ', $temp);


        $results = craft()->db->createCommand($query)->execute();
    }

    private function updateCustomerRecords()
    {

        // Only run this is we have a stripeCustomerId col on the charges table

        $chargesTable = $this->dbConnection->schema->getTable('{{charges}}');

        if ($chargesTable->getColumn('stripeCustomerId') !== null)
        {

            $sql = "SELECT charges.*
                    FROM craft_charges charges
                    WHERE stripeCustomerId != ''";
            $results = craft()->db->createCommand($sql)->queryAll();

            $cleanIds = [];
            $maps = [];

            foreach ($results as $row) {

                if (!isset($maps[$row['stripeCustomerId']])) {
                    // Create a new row in elements

                    // Make sure the customer isn't already in the table somehow
                    $existing = "SELECT * FROM craft_charge_customers WHERE stripeId = '".$row['stripeCustomerId']."'";
                    $existingRow = craft()->db->createCommand($existing)->queryRow();

                    if($existingRow != null ) {
                        $customerId = $existingRow['id'];
                    } else {

                        craft()->db->createCommand()->insert('charge_customers',
                            [
                                'name'     => $row['customerName'],
                                'email'    => $row['customerEmail'],
                                'stripeId' => $row['stripeCustomerId'],
                                'userId'   => $row['userId'],
                                'mode'     => $row['mode']
                            ]);
                        $customerId = craft()->db->getLastInsertID();
                    }

                    $maps[$row['stripeCustomerId']] = $customerId;
                }
                $cleanIds[] = $row['id'];
            }

            if (empty($cleanIds)) return true;

            // Set the customer Ids
            foreach ($maps as $stripeId => $customerId) {
                $sql = "UPDATE craft_charges SET customerId = " . $customerId . " WHERE stripeCustomerId = '" . $stripeId . "'";
                $results = craft()->db->createCommand($sql)->execute();
            }

            // Unmark the stripe Ids
            $query = "UPDATE craft_charges SET stripeCustomerId = '' WHERE id IN ('" . implode("','", array_keys($maps)) . "')";
            $results = craft()->db->createCommand($query)->execute();

        }

        return true;
    }


    private function updateChargeRequest()
    {
        // Wrap up any legacy card details into a clean request blob
        // and store on the record

        // This only applies for rows with planAmount values. Let's check against that
        $sql = "SELECT * FROM craft_charges WHERE request IS NULL";
        $results = craft()->db->createCommand($sql)->queryAll();

        if (empty($results)) return;

        $attr = ['planAmount', 'planAmountInCents', 'planCurrency', 'planInterval', 'planIntervalCount', 'planName', 'planDiscount', 'planFullAmount',
                'cardName', 'cardAddressLine1', 'cardAddressLine2', 'cardAddressCity', 'cardAddressState','cardAddressZip','cardAddressCountry',
                'cardLast4', 'cardType', 'cardExpMonth','cardExpYear'];

        foreach ($results as $row) {
            // Build up the request blob
            $request = [];

            foreach ($attr as $key) {
                $request[$key] = '';
                if (isset($row[$key])) $request[$key] = $row[$key];
            }

            if(isset($row['planAmount'])) {
                $request['planAmountInCents'] = $request['planAmount'];
                $request['planAmount'] = $request['planAmount'] / 100;
            }

            // Create a new row in elements
            craft()->db->createCommand()->update(
                'charges',
                ['request' => json_encode($request)],
                'id = '.$row['id']);
        }
    }
}
