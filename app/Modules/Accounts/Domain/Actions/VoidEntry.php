<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Modules\Accounts\Contracts\Data\PostedOperation;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\EntryAlreadyVoided;
use App\Modules\Accounts\Contracts\Exceptions\ReversalCannotBeVoided;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Services\OperationLog;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an entry by posting its reversal: the same amount the other way,
 * pointing at it, with the reason. The entry stays where it was, so the
 * balances that followed it are still the balances that were true then — and
 * the reversal, dated now, moves today's balance back.
 *
 * Who may void what — their own entry for a short while, anyone's with
 * `accounts.correct` — is the screen's question, asked through the entry's
 * policy before it gets here.
 */
final readonly class VoidEntry
{
    public function __construct(
        private PostAccountTransaction $post,
        private OperationLog $operations,
    ) {}

    /**
     * @throws ReversalCannotBeVoided
     * @throws EntryAlreadyVoided
     * @throws AccountChangedMeanwhile when someone posted since the person looked
     */
    public function handle(VoidRequest $request): PostedOperation
    {
        return DB::transaction(function () use ($request): PostedOperation {
            $entry = AccountTransaction::query()->findOrFail($request->entryId);
            $locked = CustomerAccount::query()->whereKey($entry->account_id)->lockForUpdate()->firstOrFail();

            if ($done = $this->operations->previously($request->operationId, $locked)) {
                return $done;
            }

            $locked->assertUnchangedSince($request->expectedLastEntryId);
            $entry->assertVoidable();

            $reversal = $this->post->handle(
                $locked,
                EntryKind::Reversal,
                $entry->amount,
                $request->postedBy,
                type: $entry->type->inverse(),
                description: $request->note,
                reason: $request->reason,
                reverses: $entry->getKey(),
                operationId: $request->operationId,
            );

            return $this->operations->posted($request->operationId, [$reversal], $locked);
        });
    }
}
