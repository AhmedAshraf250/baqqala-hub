<?php

namespace App\Modules\Accounts\Domain\Services;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Domain\Actions\OpenAccount;
use App\Modules\Accounts\Domain\Actions\PostAccountTransaction;
use Illuminate\Container\Attributes\Scoped;

/**
 * The published ledger, implemented over this module's own actions.
 *
 * Deliberately thin. Its job is to be the seam: other modules depend on the
 * interface, and the actions behind it stay free to change.
 */
#[Scoped]
final readonly class AccountLedger implements AccountLedgerInterface
{
    public function __construct(
        private OpenAccount $openAccount,
        private PostAccountTransaction $post,
    ) {}

    public function post(
        int $customerId,
        TransactionType $type,
        Money $amount,
        int $postedBy,
        ?string $description = null,
        ?string $reference = null,
    ): void {
        $this->post->handle(
            $this->openAccount->handle($customerId),
            $type,
            $amount,
            $postedBy,
            $description,
            $reference,
        );
    }
}
