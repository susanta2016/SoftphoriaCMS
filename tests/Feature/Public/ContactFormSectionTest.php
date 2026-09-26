<?php

namespace Tests\Feature\Public;

use App\Actions\Page\CreatePageAction;
use App\Enums\PageTemplate;
use App\Models\ContactRequest;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassesFormTimeTrap;
use Tests\TestCase;

/**
 * WEB-101 item D — PageSectionType::ContactForm, previously an inert
 * placeholder, now renders the shared x-site.contact-form component. It
 * must post to the exact same route/controller as the dedicated Contact
 * page — no second contact-processing implementation.
 */
class ContactFormSectionTest extends TestCase
{
    use PassesFormTimeTrap;
    use RefreshDatabase;

    public function test_a_contact_form_section_renders_the_shared_form(): void
    {
        $page = $this->publishedPage([
            'title' => 'Get In Touch',
            'slug' => 'get-in-touch',
            'sections' => [
                ['section_type' => 'contact_form', 'title' => 'Send Us a Message', 'is_enabled' => true, 'content_json' => []],
            ],
        ]);

        $response = $this->get('/'.$page->slug);

        $response->assertOk();
        $response->assertSee('Send Us a Message');
        $response->assertSee('hp_website', false);
        $response->assertSee(route('contact.submit'), false);
        $response->assertDontSee('no rendering yet for this block type');
    }

    public function test_the_embedded_forms_action_posts_through_the_real_contact_route(): void
    {
        $page = $this->publishedPage([
            'title' => 'Get In Touch',
            'slug' => 'get-in-touch',
            'sections' => [
                ['section_type' => 'contact_form', 'is_enabled' => true, 'content_json' => []],
            ],
        ]);

        $this->get('/'.$page->slug);

        $response = $this->post('/contact', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello from the embedded section.',
            '_started' => $this->formStartedToken(),
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('contact_requests', [
            'email' => 'jane@example.com',
            'message' => 'Hello from the embedded section.',
        ]);
        $this->assertSame(1, ContactRequest::query()->count());
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPage(array $overrides): Page
    {
        $admin = $this->admin();

        $page = app(CreatePageAction::class)->handle(array_merge([
            'title' => 'Untitled',
            'slug' => 'untitled',
            'template' => PageTemplate::Standard->value,
        ], $overrides), $admin);

        $page->update(['status' => 'published']);

        return $page->fresh();
    }
}
