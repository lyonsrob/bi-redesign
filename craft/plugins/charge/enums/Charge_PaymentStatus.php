<?php
namespace Craft;


abstract class Charge_PaymentStatus extends BaseEnum
{
    // Constants
    // =========================================================================

    const Failed = 'failed';
    const Paid = 'paid';
    const Refunded = 'refunded';
    const Authorized = 'authorized';
}
