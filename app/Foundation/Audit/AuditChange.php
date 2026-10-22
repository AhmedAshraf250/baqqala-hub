<?php

namespace App\Foundation\Audit;

/**
 * One field that changed: what it is called (a translation key in its
 * owner's strings), what it was, and what it became — as a person reads
 * them, ready to show. When `translated` is set, the values are translation
 * keys too, so the log reads in its reader's language: «قطعة», «مفعّل».
 */
final readonly class AuditChange
{
    public function __construct(
        public string $label,
        public ?string $from,
        public ?string $to,
        public bool $translated = false,
    ) {}

    /**
     * @return array{label: string, from: string|null, to: string|null, translated: bool}
     */
    public function toArray(): array
    {
        return ['label' => $this->label, 'from' => $this->from, 'to' => $this->to, 'translated' => $this->translated];
    }

    /**
     * @param  array{label?: mixed, from?: mixed, to?: mixed, translated?: mixed}  $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            label: (string) ($stored['label'] ?? ''),
            from: isset($stored['from']) ? (string) $stored['from'] : null,
            to: isset($stored['to']) ? (string) $stored['to'] : null,
            translated: (bool) ($stored['translated'] ?? false),
        );
    }
}
