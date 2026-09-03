<?php

namespace Tests\Unit\Architecture;

use App\Actions\Contact\DeleteContactRequestAction;
use App\Actions\Contact\SubmitContactRequestAction;
use App\Actions\Contact\UpdateContactRequestAction;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Http\Controllers\ContactController;
use App\Models\ContactRequest;
use ReflectionClass;
use Tests\TestCase;

/**
 * ADMIN-010 is a Softphoria Core feature (docs/ARCHITECTURE.md §1/§12b):
 * nothing it introduces may live under app/Modules (client-specific) or
 * reference a Jacob-specific concept, and it must not touch JacobCMS's own
 * (unrelated) contact_submissions table/model.
 */
class ContactRequestBoundaryTest extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    private function admin010Classes(): array
    {
        return [
            ContactRequest::class,
            ContactController::class,
            SubmitContactRequestAction::class,
            UpdateContactRequestAction::class,
            DeleteContactRequestAction::class,
            ContactRequestResource::class,
        ];
    }

    public function test_admin_010_classes_live_under_core_not_a_module(): void
    {
        foreach ($this->admin010Classes() as $class) {
            $this->assertStringStartsNotWith('App\\Modules\\', $class);
        }
    }

    public function test_admin_010_source_never_references_jacobs_parallel_contact_submissions_model(): void
    {
        foreach ($this->admin010Classes() as $class) {
            $source = file_get_contents((new ReflectionClass($class))->getFileName());

            $this->assertStringNotContainsString('ContactSubmission', $source);
        }
    }
}
