<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('operation')->index();
            $table->string('status')->default('preparing');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('is_legacy')->default(false);
            $table->string('contract_file')->nullable();
            $table->timestamp('documents_approved_at')->nullable();
            $table->foreignId('documents_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('deposit_initial_amount', 10, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->date('deposit_paid_at')->nullable();
            $table->string('deposit_payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['driver_id', 'starts_at', 'ends_at']);
        });
        Schema::create('participation_suspensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_participation_id')->constrained()->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('slot_packs', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->decimal('weekly_price', 10, 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->text('benefits');
            $table->timestamps();
            $table->unique(['code', 'valid_from']);
        });
        Schema::create('slot_pack_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_participation_id')->constrained()->restrictOnDelete();
            $table->foreignId('slot_pack_id')->constrained()->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['driver_participation_id', 'starts_at']);
        });
        Schema::create('vehicle_checkups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
        Schema::create('slot_accidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_participation_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->string('insurer')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->text('contacts')->nullable();
            $table->text('notes')->nullable();
            $table->json('documents')->nullable();
            $table->dateTime('immobilized_at')->nullable();
            $table->dateTime('repair_started_at')->nullable();
            $table->dateTime('repair_completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('slot_accident_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('slot_accident_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('status')->default('preparing');
            $table->date('requested_at')->nullable();
            $table->date('resolved_at')->nullable();
            $table->decimal('amount_requested', 10, 2)->nullable();
            $table->decimal('amount_awarded', 10, 2)->nullable();
            $table->text('result')->nullable();
            $table->timestamps();
        });
        Schema::table('drivers', function (Blueprint $table): void {
            $table->string('registration_operation')->default('rental');
        });
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('operation')->default('rental')->index();
            $table->foreignId('owner_driver_id')->nullable()->constrained('drivers')->restrictOnDelete();
        });
        foreach ($this->tables() as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('driver_participation_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('operation')->default('rental')->index();
            });
        }
        Schema::table('platform_driver_balances', function (Blueprint $table): void {
            $table->text('allocation_error')->nullable();
        });
        Schema::table('driver_balance_movements', function (Blueprint $table): void {
            $table->uuid('payment_reference')->nullable()->unique();
        });
        foreach (['driver_billing_profiles', 'driver_settlements', 'driver_balances'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index('driver_id', $tableName.'_slot_driver_lookup');
            });
        }
        Schema::table('driver_billing_profiles', function (Blueprint $table): void {
            $table->dropUnique(['driver_id']);
            $table->unique(['driver_participation_id', 'valid_from'], 'participation_profile_start_unique');
        });
        Schema::table('driver_settlements', function (Blueprint $table): void {
            $table->decimal('slot_fee', 10, 2)->default(0);
            $table->timestamp('platform_received_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->date('payment_due_at')->nullable();
            $table->text('payment_delay_reason')->nullable();
            $table->dropUnique('driver_period_unique');
            $table->unique(['driver_participation_id', 'period_start', 'period_end'], 'participation_period_unique');
        });
        Schema::table('driver_balances', function (Blueprint $table): void {
            $table->dropUnique(['driver_id']);
            $table->unique('driver_participation_id');
        });
        Schema::table('driver_week_statements', function (Blueprint $table): void {
            $table->decimal('slot_fee', 10, 2)->default(0);
            $table->json('rules_snapshot')->nullable();
        });
        \App\Models\Driver::query()->orderBy('id')->chunkById(100, function ($drivers): void {
            foreach ($drivers as $driver) {
                $dates = collect([$driver->created_at]);
                foreach (['driver_settlements' => 'period_start', 'driver_week_statements' => 'week_start_date', 'vehicle_allocations' => 'starts_at', 'driver_billing_profiles' => 'valid_from', 'driver_adjustments' => 'starts_at', 'platform_driver_balances' => 'period_start'] as $table => $column) {
                    $model = new class extends \Illuminate\Database\Eloquent\Model {};
                    $date = $model->setTable($table)->newQuery()->where('driver_id', $driver->id)->min($column);
                    if ($date) {
                        $dates->push(\Illuminate\Support\Carbon::parse($date));
                    }
                }
                $participation = \App\Models\DriverParticipation::query()->create([
                    'driver_id' => $driver->id, 'operation' => 'rental', 'status' => 'active', 'is_legacy' => true,
                    'starts_at' => $dates->filter()->sort()->first()?->toDateString() ?? '1970-01-01',
                    'contract_file' => $driver->contract_file, 'deposit_amount' => $driver->deposit_amount ?? 0,
                    'deposit_initial_amount' => $driver->deposit_initial_amount ?? 0, 'deposit_paid_at' => $driver->deposit_paid_at,
                    'deposit_payment_method' => $driver->deposit_payment_method,
                ]);
                foreach ($this->tables() as $table) {
                    $model = new class extends \Illuminate\Database\Eloquent\Model {};
                    $model->setTable($table)->newQuery()->where('driver_id', $driver->id)->update(['driver_participation_id' => $participation->id]);
                }
            }
        });
        $benefits = 'Check-up gratuito anual (primeiro na entrada), preços descontados e atendimento prioritário na oficina. Pagamentos à segunda-feira.';
        foreach (['base' => ['Base', 30], 'premium' => ['Premium', 50]] as $code => [$name, $price]) {
            \App\Models\SlotPack::query()->create(['code' => $code, 'name' => $name, 'weekly_price' => $price, 'valid_from' => '2026-10-07', 'benefits' => $benefits.($code === 'premium' ? ' Acompanhamento de acidentes 24/7; pedidos de indemnização por imobilização e de viatura de substituição.' : '')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\App\Models\DriverParticipation::query()->where('is_legacy', false)->exists()) {
            throw new \RuntimeException('O rollback exige arquivo prévio das novas participações para preservar os históricos.');
        }
        foreach (['driver_billing_profiles', 'driver_settlements', 'driver_balances'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->index('driver_participation_id', $tableName.'_slot_part_lookup');
            });
        }
        Schema::table('driver_settlements', function (Blueprint $table): void {
            $table->dropUnique('participation_period_unique');
            $table->unique(['driver_id', 'period_start', 'period_end'], 'driver_period_unique');
            $table->dropColumn(['slot_fee', 'platform_received_at', 'reconciled_at', 'payment_due_at', 'payment_delay_reason']);
        });
        Schema::table('driver_balances', function (Blueprint $table): void {
            $table->dropUnique(['driver_participation_id']);
            $table->unique('driver_id');
        });
        Schema::table('driver_week_statements', fn (Blueprint $table) => $table->dropColumn(['slot_fee', 'rules_snapshot']));
        Schema::table('driver_billing_profiles', function (Blueprint $table): void {
            $table->dropUnique('participation_profile_start_unique');
            $table->unique('driver_id');
        });
        Schema::table('platform_driver_balances', fn (Blueprint $table) => $table->dropColumn('allocation_error'));
        Schema::table('driver_balance_movements', function (Blueprint $table): void {
            $table->dropUnique(['payment_reference']);
            $table->dropColumn('payment_reference');
        });
        foreach ($this->tables() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign(['driver_participation_id']);
                if (in_array($tableName, ['driver_billing_profiles', 'driver_settlements', 'driver_balances'], true)) {
                    $table->dropIndex($tableName.'_slot_part_lookup');
                    $table->dropIndex($tableName.'_slot_driver_lookup');
                }
                $table->dropColumn('driver_participation_id');
                $table->dropIndex(['operation']);
                $table->dropColumn('operation');
            });
        }
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_driver_id');
            $table->dropIndex(['operation']);
            $table->dropColumn('operation');
        });
        Schema::table('drivers', fn (Blueprint $table) => $table->dropColumn('registration_operation'));
        foreach (['slot_accident_requests', 'slot_accidents', 'vehicle_checkups', 'slot_pack_assignments', 'slot_packs', 'participation_suspensions', 'driver_participations'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function tables(): array
    {
        return ['vehicle_allocations', 'driver_billing_profiles', 'driver_settlements', 'driver_week_statements', 'driver_adjustments', 'driver_balances', 'driver_balance_movements', 'driver_deposit_debits', 'platform_driver_balances'];
    }
};
