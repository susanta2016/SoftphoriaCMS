<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Kept for anything that still calls it by name. It used to write a
 * placeholder "privacy-policy" page and overwrite it on every run; the real
 * legal pages (Privacy Policy, Terms of Service, Cookie Policy) now come
 * from LegalPagesSeeder, which never overwrites an admin-edited page.
 */
class PrivacyPolicyPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LegalPagesSeeder::class);
    }
}
