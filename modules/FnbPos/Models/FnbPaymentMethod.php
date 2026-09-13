<?php

namespace Modules\FnbPos\Models;

final class FnbPaymentMethod extends FnbModel
{
    protected $table = 'fnb_payment_methods';

    protected function casts(): array
    {
        return ['requires_reference' => 'boolean', 'settings' => 'array', 'sort_order' => 'integer', 'version' => 'integer'];
    }
}
