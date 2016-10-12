<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_migrationName
 */
class m150313_000000_charge_addMetaData extends BaseMigration
{
    /**
     * Any migration code in here is wrapped inside of a transaction.
     *
     * @return bool
     */
    public function safeUp()
    {
        $chargesTable = $this->dbConnection->schema->getTable('{{charges}}');

        if ($chargesTable->getColumn('meta') === null)
        {
            // Add the 'hash' column to the charges table
            $this->addColumnAfter('charges', 'meta', array('column' => ColumnType::Text), 'notes');
        }

        return true;
    }
}
