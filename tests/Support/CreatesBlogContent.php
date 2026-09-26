<?php

namespace Tests\Support;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesBlogContent
{
    protected function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $user;
    }

    protected function member(string $name = 'Jane Member'): User
    {
        return User::factory()->create(['status' => 'active', 'name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function livePost(array $attributes = []): BlogPost
    {
        $title = $attributes['title'] ?? 'Post '.Str::random(6);

        return BlogPost::query()->create([
            'title' => $title,
            'slug' => $attributes['slug'] ?? Str::slug($title),
            'excerpt' => 'An excerpt.',
            'body' => '<p>Intro.</p><h2>First section</h2><p>Text.</p><h3>Detail</h3><p>More.</p>',
            'status' => BlogPostStatus::Published,
            'published_at' => now()->subDay(),
            'allow_comments' => true,
            ...$attributes,
        ]);
    }

    protected function category(string $name = 'Cloud'): BlogCategory
    {
        return BlogCategory::query()->create(['name' => $name, 'slug' => Str::slug($name), 'description' => "All about {$name}."]);
    }
}
