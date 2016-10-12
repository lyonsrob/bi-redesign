<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_pluginHandle_migrationName
 */
class m150521_000000_charge_createCustomerTable extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        // Create the craft_charge_customers table
        craft()->db->createCommand()->createTable('charge_customers', array(
            'stripeId' => array(),
            'mode'     => array('values' => 'test,live', 'column' => 'enum', 'required' => true, 'default' => 'test'),
            'userId'   => array(),
            'name'     => array(),
            'email'    => array()), null, true);

        // Add indexes to craft_charge_customers
        craft()->db->createCommand()->createIndex('charge_customers', 'stripeId', true);

        return true;
    }
}
