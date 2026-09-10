<?php

use App\Models\RentalVan;
use App\Models\User;
use App\Models\VanReservation;
use App\Services\VanRentalService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('serializes simultaneous confirmations on an isolated MySQL database', function (): void {
    if (getenv('VAN_RENTAL_MYSQL_TEST') !== '1' || ! function_exists('pcntl_fork')) {
        $this->markTestSkipped('Opt in with VAN_RENTAL_MYSQL_TEST=1 and a local MySQL connection.');
    }
    $mysql = config('database.connections.mysql');
    expect($mysql['host'])->toBeIn(['127.0.0.1', 'localhost']);
    $database = 'zentrum_van_test_'.bin2hex(random_bytes(6));
    $default = config('database.default');
    $manager = app('db');
    config(['database.connections.van_test_admin' => [...$mysql, 'url' => null, 'database' => null]]);
    $admin = $manager->connection('van_test_admin');
    $admin->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    config(['database.connections.van_test' => [...$mysql, 'url' => null, 'database' => $database], 'database.default' => 'van_test']);
    try {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
        });
        (require database_path('migrations/2026_09_10_052931_create_van_rental_tables.php'))->up();
        CarbonImmutable::setTestNow('2026-09-10 06:00:00 UTC');
        $van = RentalVan::factory()->published()->create();
        $user = User::factory()->create();
        $service = app(VanRentalService::class);
        $data = ['mode' => 'self_drive', 'starts_at' => '2026-09-12T09:00', 'ends_at' => '2026-09-12T11:00', 'name' => 'Concurrency test', 'email' => 'concurrency@example.com', 'phone' => '912345678', 'purpose' => 'goods'];
        $reservations = [
            $service->reserve($van, [...$data, 'submission_key' => (string) Str::uuid()]),
            $service->reserve($van, [...$data, 'submission_key' => (string) Str::uuid()]),
        ];
        $manager->disconnect('van_test');
        $manager->disconnect('van_test_admin');
        $children = [];
        foreach ($reservations as $reservation) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('Unable to fork MySQL test worker.');
            }
            if ($pid === 0) {
                fclose($sockets[0]);
                fread($sockets[1], 1);
                $manager->purge('van_test');
                try {
                    $service->transition($reservation, 'confirmed', $user, 'Concurrent confirmation');
                    $exitCode = 0;
                } catch (ValidationException) {
                    $exitCode = 10;
                } catch (Throwable $exception) {
                    fwrite(STDERR, $exception->getMessage());
                    $exitCode = 20;
                }
                $manager->disconnect('van_test');
                fclose($sockets[1]);
                exit($exitCode);
            }
            fclose($sockets[1]);
            $children[] = [$pid, $sockets[0]];
        }
        foreach ($children as [, $socket]) {
            fwrite($socket, '1');
            fclose($socket);
        }
        $codes = [];
        foreach ($children as [$pid]) {
            pcntl_waitpid($pid, $status);
            $codes[] = pcntl_wexitstatus($status);
        }
        sort($codes);
        expect($codes)->toBe([0, 10]);
        expect(VanReservation::query()->where('status', 'confirmed')->count())->toBe(1);
        expect(VanReservation::query()->where('status', 'pending')->count())->toBe(1);
    } finally {
        CarbonImmutable::setTestNow();
        $manager->purge('van_test');
        config(['database.default' => $default]);
        $manager->connection('van_test_admin')->statement('DROP DATABASE `'.$database.'`');
        $manager->purge('van_test_admin');
    }
});
