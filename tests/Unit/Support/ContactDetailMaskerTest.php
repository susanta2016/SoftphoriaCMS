<?php

namespace Tests\Unit\Support;

use App\Shared\Support\Contact\ContactDetailMasker;
use PHPUnit\Framework\TestCase;

class ContactDetailMaskerTest extends TestCase
{
    public function test_emails_keep_only_a_short_prefix_and_suffix(): void
    {
        $this->assertSame('co•••••@•••••ia.com', ContactDetailMasker::maskEmail('contact@softphoria.com'));
        $this->assertSame('a•••••@•••••co.uk', ContactDetailMasker::maskEmail('ab@example.co.uk'));
        $this->assertSame('•••••', ContactDetailMasker::maskEmail('not-an-email'));
    }

    public function test_the_mask_does_not_reveal_the_hidden_length(): void
    {
        $this->assertSame(
            ContactDetailMasker::maskEmail('contact@softphoria.com'),
            ContactDetailMasker::maskEmail('cooperation-team@my-long-domainia.com'),
        );
    }

    public function test_phones_keep_the_country_code_and_last_two_digits(): void
    {
        $this->assertSame('+91 91•••••94', ContactDetailMasker::maskPhone('+91 9163270494'));
        $this->assertSame('+1 555 •••••00', ContactDetailMasker::maskPhone('+1 555 010 0100'));
        $this->assertSame('•••••34', ContactDetailMasker::maskPhone('1234'));
        $this->assertSame('+9191•••••94', ContactDetailMasker::mask('whatsapp', '919163270494'));
    }

    public function test_masked_values_never_contain_the_original(): void
    {
        foreach (['email' => 'contact@softphoria.com', 'phone' => '+91 9163270494', 'whatsapp' => '919163270494'] as $channel => $value) {
            $this->assertStringNotContainsString($value, ContactDetailMasker::mask($channel, $value));
        }
    }

    public function test_reveal_builds_safe_links(): void
    {
        $this->assertSame(['display' => '+1 555 010 0100', 'href' => 'tel:+15550100100'], ContactDetailMasker::reveal('phone', '+1 555 010 0100'));
        $this->assertSame(['display' => '+919163270494', 'href' => 'https://wa.me/919163270494'], ContactDetailMasker::reveal('whatsapp', '+91 91632 70494'));
    }
}
