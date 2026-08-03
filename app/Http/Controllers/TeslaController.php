<?php

namespace App\Http\Controllers;

use App\Filament\Pages\TeslaIntegration;
use App\Models\TeslaAccount;
use App\Services\TeslaService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TeslaController extends Controller
{
    public function __construct(private readonly TeslaService $teslaService) {}

    public function redirectToTesla(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()
                ->to(TeslaIntegration::getUrl())
                ->with('error', 'Configura as credenciais Tesla antes de ligar uma conta.');
        }

        $state = Str::random(48);

        $request->session()->put('tesla_oauth_state', $state);
        $request->session()->put('tesla_oauth_user_id', Auth::id());
        Cache::put($this->stateCacheKey($state), [
            'user_id' => Auth::id(),
        ], now()->addMinutes(15));

        return redirect()->away($this->teslaService->getAuthorizationUrl($state));
    }

    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        $state = (string) $request->query('state');
        $expectedState = $request->session()->get('tesla_oauth_state');
        $cachedState = $state !== '' ? Cache::get($this->stateCacheKey($state)) : null;

        if (! $this->isValidState($state, $expectedState, $cachedState)) {
            return redirect()
                ->to(TeslaIntegration::getUrl())
                ->with('error', 'O estado OAuth da Tesla e invalido. Tenta ligar a conta novamente.');
        }

        $request->session()->forget('tesla_oauth_state');
        Cache::forget($this->stateCacheKey($state));

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect()
                ->to(TeslaIntegration::getUrl())
                ->with('error', 'A Tesla nao devolveu um codigo de autorizacao.');
        }

        try {
            $token = $this->teslaService->exchangeCodeForToken($code);
        } catch (RequestException $exception) {
            return redirect()
                ->to(TeslaIntegration::getUrl())
                ->with('error', $this->messageFromTeslaException($exception, 'Nao foi possivel trocar o codigo OAuth por tokens.'));
        }

        $cachedUserId = is_array($cachedState) ? $cachedState['user_id'] ?? null : null;

        $teslaUserId = $this->teslaUserIdFromToken($token['id_token'] ?? null);
        $account = $teslaUserId !== null
            ? TeslaAccount::query()->firstOrNew(['tesla_user_id' => $teslaUserId])
            : new TeslaAccount;

        $account->fill([
            'user_id' => $request->session()->pull('tesla_oauth_user_id') ?: $cachedUserId ?: Auth::id(),
            'tesla_user_id' => $teslaUserId,
            'access_token' => encrypt((string) $token['access_token']),
            'refresh_token' => encrypt((string) $token['refresh_token']),
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'scopes' => $this->teslaService->scopesFrom($token['scope'] ?? config('services.tesla.scopes')),
        ]);

        if (! $account->exists) {
            $account->fill([
                'email' => null,
                'owner_email' => 'unknown',
            ]);
        }

        $account->save();

        try {
            $profile = $this->teslaService->getUserMe($account);
            $ownerEmail = $profile['email'] ?? $profile['user']['email'] ?? null;

            if (is_string($ownerEmail) && $ownerEmail !== '') {
                $account->forceFill([
                    'email' => $ownerEmail,
                    'owner_email' => $ownerEmail,
                ])->save();
            }
        } catch (RequestException $exception) {
            Log::info('Tesla account connected without user profile email.', [
                'tesla_account_id' => $account->getKey(),
                'status' => $exception->response?->status(),
            ]);
        }

        return redirect()
            ->to(TeslaIntegration::getUrl())
            ->with('success', 'Conta Tesla ligada com sucesso.');
    }

    public function syncVehicles(): \Illuminate\Http\RedirectResponse
    {
        $accounts = TeslaAccount::query()->get();

        if ($accounts->isEmpty()) {
            return redirect()
                ->to(TeslaIntegration::getUrl())
                ->with('error', 'Liga uma conta Tesla antes de sincronizar veiculos.');
        }

        $synced = 0;

        try {
            foreach ($accounts as $account) {
                $synced += $this->teslaService->syncVehicles($account);
            }
        } catch (RequestException $exception) {
            return redirect()
                ->to(TeslaIntegration::getUrl())
                ->with('error', $this->messageFromTeslaException($exception, 'Nao foi possivel sincronizar os veiculos Tesla.'));
        }

        return redirect()
            ->to(TeslaIntegration::getUrl())
            ->with('success', "{$synced} veiculo(s) Tesla sincronizado(s).");
    }

    protected function isConfigured(): bool
    {
        return filled(config('services.tesla.client_id'))
            && filled(config('services.tesla.client_secret'))
            && filled(config('services.tesla.redirect_uri'));
    }

    protected function messageFromTeslaException(RequestException $exception, string $fallback): string
    {
        $body = $exception->response?->json();

        if (is_array($body)) {
            $message = $body['error_description'] ?? $body['error'] ?? $body['message'] ?? null;

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return $fallback;
    }

    protected function isValidState(string $state, mixed $expectedState, mixed $cachedState): bool
    {
        if ($state === '') {
            return false;
        }

        if (is_string($expectedState) && hash_equals($expectedState, $state)) {
            return true;
        }

        return is_array($cachedState);
    }

    protected function stateCacheKey(string $state): string
    {
        return "tesla:oauth-state:{$state}";
    }

    protected function teslaUserIdFromToken(mixed $idToken): ?string
    {
        if (! is_string($idToken) || substr_count($idToken, '.') < 2) {
            return null;
        }

        $payload = explode('.', $idToken)[1] ?? '';
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode(strtr($payload, '-_', '+/'), true);

        if (! is_string($decoded)) {
            return null;
        }

        $claims = json_decode($decoded, true);
        $subject = is_array($claims) ? $claims['sub'] ?? null : null;

        return is_string($subject) && strlen($subject) <= 255 ? $subject : null;
    }
}
