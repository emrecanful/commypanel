<?php

namespace App\Models;

use App\Enums\DomainStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property DomainStatus $status
 * @property int|null $app_id
 * @property array<string, mixed>|null $last_check
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $verified_at
 */
class Domain extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'last_check' => 'array',
            'last_checked_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<App, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
