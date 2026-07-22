<?php

namespace Tests\Unit\Rules;

use App\Rules\SafeEmail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeEmailTest extends TestCase
{
    public function test_accepts_normal_email(): void
    {
        $this->assertTrue(Validator::make(['email' => 'user@example.com'], ['email' => [new SafeEmail]])->passes());
    }

    /**
     * @dataProvider unsafeEmails
     */
    public function test_rejects_control_and_non_ascii_email(string $email): void
    {
        $this->assertFalse(Validator::make(['email' => $email], ['email' => [new SafeEmail]])->passes());
    }

    public static function unsafeEmails(): array
    {
        return [
            'crlf' => ["victim@example.com\r\nBcc:attacker@example.com"],
            'newline' => ["victim@example.com\nattacker@example.com"],
            'null-byte' => ["victim@example.com\0"],
            'unicode-control' => ["victim@example.com\u{2028}"],
        ];
    }
}
