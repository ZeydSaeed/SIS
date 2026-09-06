<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS intelligence');
        }

        Schema::create(SchemaHelper::qualified('intelligence', 'monitoring_snapshots'), function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_type', 50);
            $table->decimal('database_size_mb', 14, 2)->nullable();
            $table->unsignedInteger('connection_count')->nullable();
            $table->unsignedInteger('active_connections')->nullable();
            $table->decimal('cache_hit_ratio', 5, 4)->nullable();
            $table->decimal('replication_lag_seconds', 10, 2)->nullable();
            $table->json('metrics')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['snapshot_type', 'captured_at']);
        });

        Schema::create(SchemaHelper::qualified('intelligence', 'table_metrics'), function (Blueprint $table) {
            $table->id();
            $table->string('schema_name', 63);
            $table->string('table_name', 63);
            $table->unsignedBigInteger('row_estimate')->default(0);
            $table->decimal('table_size_mb', 14, 2)->default(0);
            $table->decimal('index_size_mb', 14, 2)->default(0);
            $table->decimal('seq_scan_ratio', 5, 4)->nullable();
            $table->unsignedBigInteger('seq_scans')->default(0);
            $table->unsignedBigInteger('idx_scans')->default(0);
            $table->decimal('growth_rate_daily_pct', 8, 2)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['schema_name', 'table_name', 'captured_at']);
        });

        Schema::create(SchemaHelper::qualified('intelligence', 'query_metrics'), function (Blueprint $table) {
            $table->id();
            $table->string('query_fingerprint', 64);
            $table->string('query_label', 255)->nullable();
            $table->unsignedInteger('call_count')->default(0);
            $table->decimal('p50_ms', 12, 2)->nullable();
            $table->decimal('p95_ms', 12, 2)->nullable();
            $table->decimal('p99_ms', 12, 2)->nullable();
            $table->decimal('mean_ms', 12, 2)->nullable();
            $table->decimal('baseline_p95_ms', 12, 2)->nullable();
            $table->decimal('degradation_pct', 10, 2)->nullable();
            $table->string('workload_class', 50)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['query_fingerprint', 'captured_at']);
            $table->index(['p95_ms', 'captured_at']);
        });

        Schema::create(SchemaHelper::qualified('intelligence', 'detections'), function (Blueprint $table) {
            $table->id();
            $table->string('detection_code', 50);
            $table->string('rule_id', 50)->nullable();
            $table->unsignedTinyInteger('risk_tier')->default(0);
            $table->string('severity', 20)->default('info');
            $table->string('schema_name', 63)->nullable();
            $table->string('table_name', 63)->nullable();
            $table->string('query_fingerprint', 64)->nullable();
            $table->text('title');
            $table->text('diagnosis')->nullable();
            $table->json('evidence')->nullable();
            $table->json('context_fingerprint')->nullable();
            $table->decimal('degradation_score', 8, 2)->nullable();
            $table->string('status', 30)->default('open');
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'risk_tier']);
            $table->index(['detection_code', 'detected_at']);
        });

        Schema::create(SchemaHelper::qualified('intelligence', 'recommendations'), function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_code', 64)->unique();
            $table->foreignId('detection_id')->nullable()
                ->constrained(SchemaHelper::qualified('intelligence', 'detections'))
                ->nullOnDelete();
            $table->string('rule_id', 50)->nullable();
            $table->unsignedTinyInteger('risk_tier');
            $table->string('action_type', 50);
            $table->string('schema_name', 63)->nullable();
            $table->string('table_name', 63)->nullable();
            $table->text('what');
            $table->text('why');
            $table->json('evidence')->nullable();
            $table->json('alternatives')->nullable();
            $table->json('expected_impact')->nullable();
            $table->json('cost_analysis')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->decimal('context_similarity', 5, 4)->nullable();
            $table->unsignedTinyInteger('blast_radius_score')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'risk_tier']);
        });

        Schema::create(SchemaHelper::qualified('intelligence', 'optimization_events'), function (Blueprint $table) {
            $table->id();
            $table->string('event_code', 64)->unique();
            $table->foreignId('recommendation_id')->nullable()
                ->constrained(SchemaHelper::qualified('intelligence', 'recommendations'))
                ->nullOnDelete();
            $table->string('type', 50);
            $table->string('rule_id', 50)->nullable();
            $table->unsignedTinyInteger('risk_tier');
            $table->string('schema_name', 63)->nullable();
            $table->string('table_name', 63)->nullable();
            $table->json('trigger')->nullable();
            $table->text('action_taken');
            $table->json('evidence_before')->nullable();
            $table->json('evidence_after')->nullable();
            $table->json('results')->nullable();
            $table->string('outcome', 30)->default('pending');
            $table->boolean('rollback_required')->default(false);
            $table->boolean('rollback_verified')->nullable();
            $table->text('rollback_action')->nullable();
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('simulation_id', 64)->nullable();
            $table->json('context_fingerprint')->nullable();
            $table->decimal('recency_weight', 3, 2)->default(1.00);
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('executed_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'outcome']);
            $table->index(['schema_name', 'table_name']);
        });

        Schema::create(SchemaHelper::qualified('intelligence', 'baseline_snapshots'), function (Blueprint $table) {
            $table->id();
            $table->string('baseline_code', 64)->unique();
            $table->string('label', 100);
            $table->json('context_fingerprint');
            $table->json('table_sizes')->nullable();
            $table->json('query_baselines')->nullable();
            $table->json('active_patterns')->nullable();
            $table->string('knowledge_version', 32)->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create(SchemaHelper::qualified('intelligence', 'human_feedback_events'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_id')
                ->constrained(SchemaHelper::qualified('intelligence', 'recommendations'))
                ->cascadeOnDelete();
            $table->string('human_decision', 20);
            $table->text('human_decision_reason')->nullable();
            $table->text('human_chose_instead')->nullable();
            $table->string('outcome_of_human_choice', 30)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create(SchemaHelper::qualified('intelligence', 'self_healing_actions'), function (Blueprint $table) {
            $table->id();
            $table->string('action_code', 64)->unique();
            $table->string('playbook_id', 50);
            $table->unsignedTinyInteger('risk_tier')->default(1);
            $table->text('trigger_reason');
            $table->text('action_taken');
            $table->json('metrics_before')->nullable();
            $table->json('metrics_after')->nullable();
            $table->string('outcome', 30)->default('pending');
            $table->boolean('auto_executed')->default(true);
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('executed_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'self_healing_actions'));
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'human_feedback_events'));
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'baseline_snapshots'));
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'optimization_events'));
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'recommendations'));
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'detections'));
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'query_metrics'));
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'table_metrics'));
        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'monitoring_snapshots'));

        if (SchemaHelper::isPostgreSql()) {
            DB::statement('DROP SCHEMA IF EXISTS intelligence CASCADE');
        }
    }
};
