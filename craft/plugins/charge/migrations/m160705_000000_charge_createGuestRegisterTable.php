<?php
namespace Craft;

class m160705_000000_charge_createGuestRegisterTable extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        // Create the craft_charge_guestRegister table
        craft()->db->createCommand()->createTable('charge_guestregister', [
            'userId'   => ['column' => ColumnType::Int],
            'chargeId' => ['column' => ColumnType::Int]]);

        return true;
    }
}
