<?php

use App\Models\User;
use App\Notifications\Auth\QueuedResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

test('password reset email is queued to reduce account enumeration timing', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function ($notification) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class);

        return true;
    });
});

test('password recovery does not reveal whether an account exists', function (bool $json) {
    Notification::fake();
    $user = User::factory()->create(['email' => 'existente@example.com']);

    $method = $json ? 'postJson' : 'post';
    $existing = $this->$method(route('password.email'), ['email' => $user->email]);
    if ($json) $existing->assertOk()->assertJsonStructure(['message'])->assertHeaderMissing('Location');
    else $existing->assertSessionHas('status');
    $genericMessage = $json ? $existing->json('message') : session('status');

    $missing = $this->$method(route('password.email'), ['email' => 'no-existe@example.com']);
    if ($json) $missing->assertOk()->assertExactJson(['message' => $genericMessage]);
    else $missing->assertSessionHas('status', $genericMessage);

    Notification::assertSentTo($user, QueuedResetPassword::class);
})->with(['formulario' => false, 'validación interna JSON' => true]);

test('password recovery recognizes a canonicalized email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'recuperar@example.com']);

    $this->post(route('password.email'), ['email' => ' RECUPERAR@EXAMPLE.COM '])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->createToken('antes-de-recuperar-clave');
    DB::table('sessions')->insert([
        'id' => 'sesion-anterior-reset',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Nueva-Segura-2026!',
            'password_confirmation' => 'Nueva-Segura-2026!',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        expect($user->tokens()->count())->toBe(0)
            ->and(Hash::check('Nueva-Segura-2026!', $user->fresh()->password))->toBeTrue();
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-anterior-reset']);

        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Reutilizada-Segura-2026!',
            'password_confirmation' => 'Reutilizada-Segura-2026!',
        ])->assertSessionHasErrors('email');
        expect(Hash::check('Nueva-Segura-2026!', $user->fresh()->password))->toBeTrue();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Nueva-Segura-2026!',
        ])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);

        return true;
    });
});

test('recovery traverses the queue and renders a usable Spanish email only when authorized', function (bool $validacion, bool $permitido, string $dominio): void {
    config([
        'hubdigital.validation_mode' => $validacion,
        'hubdigital.allow_auth_emails_during_validation' => $permitido,
        'hubdigital.validation_queue' => 'validation-auth-prueba',
        'app.url' => $dominio,
        'mail.default' => 'array',
    ]);
    Queue::fake();
    $user = User::factory()->create(['email' => 'recuperar+alterno@example.test']);
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $transport->flush();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

    Queue::assertPushed(SendQueuedNotifications::class, function ($job) use ($validacion): bool {
        expect($job->notification)->toBeInstanceOf(QueuedResetPassword::class)
            ->and($job->queue)->toBe($validacion ? 'validation-auth-prueba' : null)
            ->and($job->tries)->toBe(3);

        return true;
    });
    $job = Queue::pushed(SendQueuedNotifications::class)->sole();
    $job->handle(app(ChannelManager::class));
    $debeEnviar = ! $validacion || $permitido;
    expect($transport->messages())->toHaveCount($debeEnviar ? 1 : 0);
    if (! $debeEnviar) return;

    $email = $transport->messages()->sole()->getOriginalMessage();
    expect($email->getSubject())->toBe('Restablece tu contraseña de HubDigital')
        ->and($email->getTo()[0]->getAddress())->toBe($user->email)
        ->and($email->getHtmlBody())->toContain('Restablecer mi contraseña', '60 minutos', 'Si no solicitaste este cambio')
        ->and($email->getTextBody())->toContain('solo puede utilizarse una vez', $dominio.'/');
    preg_match('~https?://[^\s]+~', $email->getTextBody(), $urls);
    $url = $urls[0];
    parse_str(parse_url($url, PHP_URL_QUERY), $parametros);
    expect($parametros['email'])->toBe($user->email)
        ->and(Password::broker()->tokenExists($user, basename(parse_url($url, PHP_URL_PATH))))->toBeTrue();
    $this->get($url)->assertOk()->assertSee('Restablecer contraseña')->assertSee($user->email);
})->with([
    'producción' => [false, false, 'https://labinvepn.org'],
    'dev con permiso de autenticación' => [true, true, 'https://dev.labinvepn.org'],
    'validación sin permiso' => [true, false, 'https://dev.labinvepn.org'],
]);

test('validation keeps operational mail blocked and preserves authorized verification mail', function (): void {
    config(['hubdigital.validation_mode' => true, 'hubdigital.allow_auth_emails_during_validation' => true, 'mail.default' => 'array']);
    $user = User::factory()->unverified()->create();
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $transport->flush();
    Notification::sendNow($user, new class extends \Illuminate\Notifications\Notification {
        public function via($notifiable): array { return ['mail']; }
        public function toMail($notifiable): \Illuminate\Notifications\Messages\MailMessage
        {
            return (new \Illuminate\Notifications\Messages\MailMessage)->subject('Operación no autorizada')->line('No enviar.');
        }
    });
    expect($transport->messages())->toHaveCount(0);
    Notification::sendNow($user, new \Illuminate\Auth\Notifications\VerifyEmail);
    expect($transport->messages())->toHaveCount(1);
    config(['hubdigital.allow_auth_emails_during_validation' => false]);
    Notification::sendNow($user, new \Illuminate\Auth\Notifications\VerifyEmail);
    expect($transport->messages())->toHaveCount(1);
});

test('a delayed recovery job does not send an expired or replaced token', function (bool $reemplazar): void {
    config(['mail.default' => 'array', 'hubdigital.validation_mode' => false]);
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    if ($reemplazar) Password::broker()->createToken($user);
    else $this->travel(61)->minutes();
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $transport->flush();
    Notification::sendNow($user, new QueuedResetPassword($token));
    expect($transport->messages())->toHaveCount(0);
    $this->post(route('password.update'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'Invalida-Segura-2026!', 'password_confirmation' => 'Invalida-Segura-2026!',
    ])->assertSessionHasErrors('email');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with(['reemplazado' => true, 'caducado' => false]);

test('recovery cannot change a password when its confirmation differs', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $this->post(route('password.update'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'Nueva-Segura-2026!', 'password_confirmation' => 'Distinta-Segura-2026!',
    ])->assertSessionHasErrors('password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and(Password::broker()->tokenExists($user, $token))->toBeTrue();
});

test('password reset accepts internal JSON validation without redirecting the document', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $this->postJson(route('password.update'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'Nueva-Interna-2026!', 'password_confirmation' => 'Nueva-Interna-2026!',
    ])->assertOk()->assertJsonStructure(['message'])->assertHeaderMissing('Location');
    expect(Hash::check('Nueva-Interna-2026!', $user->fresh()->password))->toBeTrue();
});
