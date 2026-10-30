<?php

namespace Database\Factories;

use App\Enums\SourceType;
use App\Enums\Stack;
use App\Models\App;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<App>
 */
class AppFactory extends Factory
{
    protected $model = App::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);
        $name = str_replace('-', ' ', $slug);

        return [
            'name' => $name,
            'slug' => $slug,
            'stack' => Stack::Go,
            'source_type' => SourceType::Git,
            'git_url' => "https://example.com/acme/{$slug}.git",
            'git_branch' => 'main',
            'container_port' => 8080,
            'health_path' => '/',
        ];
    }
}
