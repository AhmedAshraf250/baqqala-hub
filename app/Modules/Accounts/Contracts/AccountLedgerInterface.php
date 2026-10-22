<?php

namespace App\Modules\Accounts\Contracts;

use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Contracts\Exceptions\EntryAlreadyVoided;
use App\Modules\Accounts\Contracts\Exceptions\NothingOwed;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Contracts\Exceptions\PriceChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\ProductUnavailable;
use App\Modules\Accounts\Contracts\Exceptions\ReversalCannotBeVoided;

/**
 * The writes another module may make to the Accounts module: goods taken on
 * account, money paid, and an entry cancelled.
 *
 * A cash-sales module putting a basket on account asks the container for
 * this, never for the actions behind it. It is not on the repository because
 * none of it is a read: each locks the account, keeps its rules, and posts
 * through the one action that writes the ledger. Reads go through
 * {@see AccountRepositoryInterface}.
 *
 * Every request carries an operation id, made once by whoever builds it; the
 * same operation arriving twice is posted once, and the second call returns
 * what the first posted. The account is opened on first use, so a caller
 * never has to ask first.
 */
interface AccountLedgerInterface
{
    /**
     * Goods taken on account, priced from the catalog line by line, with the
     * part paid on the spot if there was one.
     *
     * @throws ProductUnavailable
     * @throws PriceChangedMeanwhile
     * @throws CreditLimitExceeded
     * @throws OverpaymentNotAllowed when the part paid now is more than is owed
     */
    public function charge(ChargeRequest $request): PostedOperation;

    /**
     * Money handed over.
     *
     * @throws AccountChangedMeanwhile
     * @throws OverpaymentNotAllowed
     * @throws NothingOwed
     */
    public function payment(PaymentRequest $request): PostedOperation;

    /**
     * Cancel an entry with its reversal.
     *
     * @throws ReversalCannotBeVoided
     * @throws EntryAlreadyVoided
     * @throws AccountChangedMeanwhile
     */
    public function void(VoidRequest $request): PostedOperation;
}
