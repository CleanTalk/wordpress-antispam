<?php

namespace ApbctWP;

use Cleantalk\ApbctWP\Transaction;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase
{
    private $transaction_instance;

    public function setUp()
    {
        $this->transaction_instance = Transaction::get('updater');
    }

    public function testIsRightInstance()
    {
        self::assertInstanceOf('\Cleantalk\ApbctWP\Transaction', $this->transaction_instance);
    }

    public function testPerform()
    {
        $perform = $this->transaction_instance->perform();
        self::assertIsInt($perform);

        $perform_again = $this->transaction_instance->perform();
        self::assertFalse($perform_again);
    }
}
