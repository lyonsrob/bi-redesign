<?php
namespace Craft;

class m160426_000000_charge_addChargeRequest extends BaseMigration
{
    public function safeUp()
    {
        $chargesTable = $this->dbConnection->schema->getTable('{{charges}}');

        if ($chargesTable->getColumn('request') === null) {
            $this->addColumnAfter('charges', 'request', array('column' => ColumnType::Text), 'hash');
        }

        if ($chargesTable->getColumn('actions') === null) {
            $this->addColumnAfter('charges', 'actions', array('column' => ColumnType::Text), 'request');
        }

        if ($chargesTable->getColumn('plan') !== null) {
            $this->dropColumn('charges', 'plan');
        }


        return true;
    }
}
