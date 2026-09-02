<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ContactSubmissions\Pages\ListContactSubmissions;
use App\Filament\Resources\ContactSubmissions\Pages\ViewContactSubmission;
use App\Models\ContactSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * List-only + View (submissions are created exclusively by
 * SubmitContactFormAction from the public Contact Us form) — no status/
 * workflow, unlike ResourceSubmission's review queue.
 */
class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_submissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/contact-submissions');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_list(): void
    {
        $submission = $this->createSubmission();

        Livewire::actingAs($this->admin())
            ->test(ListContactSubmissions::class)
            ->assertCanSeeTableRecords([$submission]);
    }

    public function test_no_create_or_edit_route_exists(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/contact-submissions/create');

        $response->assertNotFound();
    }

    public function test_view_page_shows_the_submissions_details(): void
    {
        $submission = $this->createSubmission(['message' => 'A very specific message body.']);

        Livewire::actingAs($this->admin())
            ->test(ViewContactSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertSee('A very specific message body.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSubmission(array $overrides = []): ContactSubmission
    {
        return ContactSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'message' => 'A message worth reading.',
            ...$overrides,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
