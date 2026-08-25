<?php

namespace Tests\Feature\InspirationalResources;

use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
use App\Shared\Mail\TemplatedNotificationMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The public Inspirational Resources page — an introductory/informational
 * section plus the submission form only (client-confirmed, final). No
 * public listing/library of submissions, no per-submission public detail
 * page, no separate public "Inspirational Resource" editorial model. A
 * submission is always a private administrative record; the page itself is
 * a normal, indexable public page.
 */
class InspirationalResourceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_publicly_accessible_with_an_intro_and_a_form(): void
    {
        $response = $this->get(route('inspirational-resources.index'));

        $response->assertOk();
        $response->assertSee('Inspirational Resources');
        $response->assertSee('Share your story');
    }

    public function test_the_page_is_never_marked_noindex_by_default(): void
    {
        $response = $this->get(route('inspirational-resources.index'));

        $response->assertDontSee('noindex', false);
    }

    public function test_a_guest_can_submit_the_form(): void
    {
        $response = $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'My Story',
            'category' => 'Testimony',
            'message' => 'Something meaningful happened.',
        ]);

        $response->assertRedirect(route('inspirational-resources.index'));
        $response->assertSessionHas('status');

        $submission = ResourceSubmission::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertNull($submission->user_id);
        $this->assertSame('new', $submission->status->value);
    }

    public function test_an_authenticated_user_submission_records_their_user_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Testimony',
            'message' => 'Something meaningful happened.',
        ]);

        $response->assertRedirect(route('inspirational-resources.index'));

        $submission = ResourceSubmission::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame($user->id, $submission->user_id);
    }

    public function test_submitting_without_required_fields_is_rejected(): void
    {
        $response = $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
        ]);

        $response->assertSessionHasErrors(['email', 'category', 'message']);
        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_submitting_notifies_every_admin_via_the_templated_mailer(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $admin = $this->admin();

        $this->post(route('inspirational-resources.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Testimony',
            'message' => 'Something meaningful happened.',
        ]);

        Mail::assertSent(TemplatedNotificationMail::class, fn (TemplatedNotificationMail $mail): bool => $mail->hasTo($admin->email));
    }

    public function test_there_is_no_public_listing_of_submissions(): void
    {
        ResourceSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'A Very Distinctive Subject Line',
            'category' => 'Testimony',
            'message' => 'A private message that must never appear publicly.',
        ]);

        $response = $this->get(route('inspirational-resources.index'));

        $response->assertOk();
        $response->assertDontSee('A Very Distinctive Subject Line');
        $response->assertDontSee('A private message that must never appear publicly.');
    }

    public function test_a_submission_has_no_public_show_route(): void
    {
        $submission = ResourceSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'category' => 'Testimony',
            'message' => 'Private message.',
        ]);

        $response = $this->get("/inspirational-resources/{$submission->id}");

        $response->assertNotFound();
    }

    public function test_submissions_never_appear_in_the_sitemap(): void
    {
        ResourceSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'A Very Distinctive Sitemap Subject',
            'category' => 'Testimony',
            'message' => 'Private message.',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee('A Very Distinctive Sitemap Subject', false);
        $response->assertDontSee('inspirational-resources/', false);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
