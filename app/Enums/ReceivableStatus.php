<?php

namespace App\Enums;

enum ReceivableStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Canceled = 'canceled';
}
