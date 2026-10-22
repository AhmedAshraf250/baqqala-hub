<?php

namespace App\Modules\Catalog\Contracts\Enums;

/**
 * How a product is sold, and so how its quantity is typed and stored.
 *
 * A quantity is an integer count of thousandths of the unit, the way money is
 * an integer count of minor units: two pieces are 2000, a quarter kilo is 250.
 * A piece is never split; a kilo or a litre is weighed or measured to three
 * decimal places.
 *
 * Published, so it carries meaning and not presentation: how a unit is drawn
 * is the screen's decision.
 */
enum SaleUnit: string
{
    /** Counted: a bottle, a pack, a sack of feed. */
    case Piece = 'piece';

    /** Weighed: loose rice, lentils, seeds, white cheese. */
    case Kilogram = 'kg';

    /** Measured: loose oil. */
    case Litre = 'liter';

    /**
     * How many stored units make one of this unit.
     */
    public const int SCALE = 1000;

    /**
     * Whether a quantity may be a fraction of the unit.
     */
    public function allowsFractions(): bool
    {
        return $this !== self::Piece;
    }

    /**
     * The unit's short name, as a line on a receipt shows it.
     */
    public function label(): string
    {
        return __('catalog::module.unit.'.$this->value);
    }
}
