<?php
namespace Craft;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_migrationName
 */
class m150227_000000_charge_addStripeIdsToRecord extends BaseMigration
{
	/**
	 * Any migration code in here is wrapped inside of a transaction.
	 *
	 * @return bool
	 */
	public function safeUp()
	{
		$chargesTable = $this->dbConnection->schema->getTable('{{charges}}');

		if ($chargesTable->getColumn('stripeCustomerId') === null)
		{
			// Add the 'hash' column to the charges table
			$this->addColumnAfter('charges', 'stripeCustomerId', array('column' => ColumnType::Varchar), 'userId');
			$this->addColumnAfter('charges', 'stripeChargeId', array('column' => ColumnType::Varchar), 'userId');
		}

		return true;
	}
}
