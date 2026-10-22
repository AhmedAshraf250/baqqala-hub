<?php

namespace App\Foundation\Audit;

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use Illuminate\Support\Facades\Date;

/**
 * Writes the change log: who changed what, and how — for everything but
 * money, which the ledger keeps entry by entry.
 *
 * Whoever changes something records it, in the step that changes it: a name
 * or a phone, a price, a credit limit, a setting. The subject is namespaced
 * by the layer that owns it (`catalog.product`), its kind is a translation
 * key in that layer's strings, and the person is whoever is signed in to the
 * admin area — nobody, when a command did it.
 */
final class AuditTrail
{
    /**
     * Record one change. An update that changed nothing is not recorded.
     *
     * @param  list<AuditChange>  $changes
     */
    public function record(string $subject, ?int $subjectId, string $label, string $kind, AuditAction $action, array $changes = []): void
    {
        if ($action === AuditAction::Updated && $changes === []) {
            return;
        }

        AuditEntry::query()->create([
            'user_id' => Area::Admin->user()?->getKey(),
            'subject' => $subject,
            'subject_id' => $subjectId,
            'label' => mb_substr($label, 0, 255),
            'kind' => $kind,
            'action' => $action,
            'fields' => $changes === [] ? null : array_map(static fn (AuditChange $change): array => $change->toArray(), $changes),
            'created_at' => Date::now(),
        ]);
    }

    /**
     * Who last did this to each of these — who deleted each thing in the
     * trash, say — by name, under its id. One read, however many there are;
     * a thing nobody is recorded against is left out.
     *
     * @param  list<int>  $subjectIds
     * @return array<int, string>
     */
    public function lastBy(string $subject, AuditAction $action, array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        $people = AuditEntry::query()
            ->where('subject', $subject)
            ->where('action', $action->value)
            ->whereIn('subject_id', $subjectIds)
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->pluck('user_id', 'subject_id');

        $names = AdminUser::query()->whereKey($people->unique()->values()->all())->pluck('name', 'id');

        return $people
            ->map(static fn (mixed $person): ?string => $names[$person] ?? null)
            ->filter()
            ->mapWithKeys(static fn (?string $name, int|string $id): array => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * The fields whose values differ, before and after, each under its
     * label. Values are compared and kept as the strings a person reads.
     *
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @param  array<string, string>  $labels  Field => translation key.
     * @param  list<string>  $translated  Fields whose values are translation keys.
     * @return list<AuditChange>
     */
    public static function differences(array $before, array $after, array $labels, array $translated = []): array
    {
        $changes = [];

        foreach ($labels as $field => $label) {
            $from = $before[$field] ?? null;
            $to = $after[$field] ?? null;

            if ($from !== $to) {
                $changes[] = new AuditChange($label, $from, $to, in_array($field, $translated, true));
            }
        }

        return $changes;
    }
}
