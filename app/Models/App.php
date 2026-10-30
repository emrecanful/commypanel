<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Enums\Stack;
use Database\Factories\AppFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Stack|null $stack
 * @property SourceType $source_type
 * @property string|null $git_url
 * @property string $git_branch
 * @property string|null $deploy_key_private
 * @property string|null $deploy_key_public
 * @property int $container_port
 * @property string $health_path
 * @property int|null $memory_limit_mb
 * @property string|null $cpu_limit
 * @property int|null $active_deployment_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class App extends Model
{
    /** @use HasFactory<AppFactory> */
    use HasFactory;

    public const SLUG_MAX_LENGTH = 40;

    protected $guarded = [];

    protected $hidden = ['deploy_key_private'];

    protected function casts(): array
    {
        return [
            'stack' => Stack::class,
            'source_type' => SourceType::class,
            'deploy_key_private' => 'encrypted',
        ];
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return BelongsTo<Deployment, $this>
     */
    public function activeDeployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class, 'active_deployment_id');
    }

    /**
     * @return HasMany<AppEnvVar, $this>
     */
    public function envVars(): HasMany
    {
        return $this->hasMany(AppEnvVar::class);
    }

    /**
     * Environment for the container: user variables as key => decrypted value.
     *
     * @return array<string, string>
     */
    public function environment(): array
    {
        return $this->envVars->pluck('value', 'key')->all();
    }

    /**
     * A unique, container-safe slug derived from a display name.
     */
    public static function generateSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = Str::limit($base === '' ? 'app' : $base, self::SLUG_MAX_LENGTH - 5, '');
        $base = trim($base, '-');
        $base = strlen($base) < 2 ? 'app' : $base;

        $slug = $base;
        $suffix = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Allocate the next per-app deployment number.
     */
    public function nextDeploymentNumber(): int
    {
        return ((int) $this->deployments()->max('number')) + 1;
    }
}
