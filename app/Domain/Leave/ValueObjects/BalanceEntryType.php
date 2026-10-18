<?php

namespace App\Domain\Leave\ValueObjects;

/**
 * leave.balance_entries.entry_type. The balance is the sum of the ledger; rows are never updated or deleted —
 * a mistake is corrected by a new adjustment with its reason.
 */
enum BalanceEntryType: int
{
    case Entitlement = 1;
    case Adjustment = 2;
    case Deduction = 3;
    case Restoration = 4;
}
