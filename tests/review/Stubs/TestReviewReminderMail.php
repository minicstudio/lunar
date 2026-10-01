<?php

namespace Lunar\Tests\Review\Stubs;

use Illuminate\Mail\Mailable;
use Lunar\Models\Order;

class TestReviewReminderMail extends Mailable
{
    /**
     * Create a new review reminder mailable for the given order.
     */
    public function __construct(public Order $order) {}
}
