<?php
namespace Craft;


class m160129_000000_charge_fixMissingDefaults extends BaseMigration
{
    public function safeUp()
    {
        $this->alterColumn('charge_coupons', 'percentageOff', array('column' => ColumnType::Int, 'default' => null));
        $this->alterColumn('charge_coupons', 'amountOff', array('column' => ColumnType::Int, 'default' => null));
        $this->alterColumn('charge_coupons', 'currency', array('column' => ColumnType::Varchar, 'default' => null));
        $this->alterColumn('charge_coupons', 'durationInMonths', array('column' => ColumnType::Int, 'default' => null));
        $this->alterColumn('charge_coupons', 'maxRedemptions', array('column' => ColumnType::Int, 'default' => null));

        return true;
    }
}
