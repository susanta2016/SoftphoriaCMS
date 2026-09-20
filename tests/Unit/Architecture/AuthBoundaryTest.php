<?php

namespace Tests\Unit\Architecture;

use App\Actions\Account\ChangeAccountPasswordAction;
use App\Actions\Account\UpdateAccountProfileAction;
use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Actions\Auth\ResendVerificationEmailAction;
use App\Actions\Auth\VerifyEmailAction;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Middleware\EnsureAccountIsUsable;
use ReflectionClass;
use Tests\TestCase;

/**
 * AUTH-001→005 is Softphoria Core, built natively on develop after a
 * read-only comparison against the isolated JacobCMS branch (never
 * switched to, merged, cherry-picked, or copied from): nothing it
 * introduces may live under app/Modules (client-specific), and none of it
 * may reference JacobCMS-specific concepts — Free/Pro membership, Stripe,
 * subscriptions, Light Posts, or any config('beta.*')/config('features.*')
 * key that codebase introduced.
 */
class AuthBoundaryTest extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    private function authClasses(): array
    {
        return [
            RegisterUserAction::class,
            AuthenticateUserAction::class,
            VerifyEmailAction::class,
            ResendVerificationEmailAction::class,
            UpdateAccountProfileAction::class,
            ChangeAccountPasswordAction::class,
            RegisteredUserController::class,
            AuthenticatedSessionController::class,
            PasswordResetLinkController::class,
            NewPasswordController::class,
            EmailVerificationController::class,
            ProfileController::class,
            PasswordController::class,
            EnsureAccountIsUsable::class,
        ];
    }

    public function test_auth_classes_live_under_core_not_a_module(): void
    {
        foreach ($this->authClasses() as $class) {
            $this->assertStringStartsNotWith('App\\Modules\\', $class);
        }
    }

    public function test_auth_source_never_references_jacobcms_specific_concepts(): void
    {
        $forbidden = ['Stripe', 'RegisterProUserAction', 'RegisterFreeUserAction', 'LightPost', 'ProMember', 'beta.enabled', 'GlobalPricingResolver'];

        foreach ($this->authClasses() as $class) {
            $source = file_get_contents((new ReflectionClass($class))->getFileName());

            foreach ($forbidden as $term) {
                $this->assertStringNotContainsString($term, $source, "{$class} unexpectedly references \"{$term}\".");
            }
        }
    }

    public function test_registration_has_no_membership_or_payment_fields(): void
    {
        $source = file_get_contents((new ReflectionClass(RegisteredUserController::class))->getFileName());

        $this->assertStringNotContainsString('subscription', strtolower($source));
        $this->assertStringNotContainsString('checkout', strtolower($source));
    }
}
