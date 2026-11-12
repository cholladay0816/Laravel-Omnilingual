<?php

namespace Tests\Unit;

use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenGuardTest extends TestCase
{
    #[DataProvider('nonStringTokens')]
    public function test_non_string_query_tokens_never_reach_the_user_provider(mixed $token): void
    {
        $provider = $this->createMock(UserProvider::class);
        $provider->expects($this->never())->method('retrieveByCredentials');
        $guard = new TokenGuard($provider, new Request(['api_token' => $token]));

        $this->assertNull($guard->user());
    }

    #[DataProvider('nonStringTokens')]
    public function test_non_string_credentials_never_reach_the_user_provider(mixed $token): void
    {
        $provider = $this->createMock(UserProvider::class);
        $provider->expects($this->never())->method('retrieveByCredentials');
        $guard = new TokenGuard($provider, new Request);

        $this->assertFalse($guard->validate(['api_token' => $token]));
    }

    public function test_valid_string_query_token_still_authenticates(): void
    {
        $user = $this->createStub(Authenticatable::class);
        $provider = $this->createMock(UserProvider::class);
        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['api_token' => 'valid-test-token'])
            ->willReturn($user);
        $guard = new TokenGuard($provider, new Request(['api_token' => 'valid-test-token']));

        $this->assertSame($user, $guard->user());
    }

    public function test_valid_string_credentials_still_validate(): void
    {
        $provider = $this->createMock(UserProvider::class);
        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['api_token' => 'valid-test-token'])
            ->willReturn($this->createStub(Authenticatable::class));
        $guard = new TokenGuard($provider, new Request);

        $this->assertTrue($guard->validate(['api_token' => 'valid-test-token']));
    }

    public static function nonStringTokens(): array
    {
        return [
            'integer' => [123],
            'boolean' => [true],
            'float' => [1.5],
            'array' => [['unexpected-token']],
            'null' => [null],
            'false' => [false],
            'zero' => [0],
        ];
    }
}
