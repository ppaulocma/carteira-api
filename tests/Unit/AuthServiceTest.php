<?php

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private UserRepository&MockInterface $userRepository;
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = Mockery::mock(UserRepository::class);
        $this->service = new AuthService($this->userRepository);
    }

    public function test_register_delegates_to_repository(): void
    {
        $user = new User();

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->with(['name' => 'João', 'email' => 'joao@test.com', 'password' => 'secret'])
            ->andReturn($user);

        $result = $this->service->register([
            'name'     => 'João',
            'email'    => 'joao@test.com',
            'password' => 'secret',
        ]);

        $this->assertSame($user, $result);
    }

    public function test_login_throws_when_user_not_found(): void
    {
        $this->userRepository
            ->shouldReceive('findByEmail')
            ->with('nao@existe.com')
            ->andReturn(null);

        $this->expectException(ValidationException::class);

        $this->service->login('nao@existe.com', 'qualquer');
    }

    public function test_login_throws_when_password_is_wrong(): void
    {
        $user = new User();
        $user->password = Hash::make('senha-correta');

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->andReturn($user);

        $this->expectException(ValidationException::class);

        $this->service->login('joao@test.com', 'senha-errada');
    }
}
