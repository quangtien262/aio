<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct_exports', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('definition_version', 80)->default('document-register.v1')->after('report_type');
            $table->string('idempotency_key', 64)->nullable()->unique()->after('status');
            $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh')->after('filters');
            $table->string('mime_type', 120)->nullable()->after('artifact_path');
            $table->string('original_name')->nullable()->after('mime_type');
            $table->unsignedBigInteger('byte_size')->nullable()->after('checksum');
            $table->unsignedBigInteger('row_count')->default(0)->after('byte_size');
            $table->timestamp('snapshot_at')->nullable()->after('requested_by');
            $table->timestamp('started_at')->nullable()->after('snapshot_at');
            $table->timestamp('expires_at')->nullable()->after('completed_at');
        });

        Schema::table('acct_email_deliveries', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->foreignId('organization_id')->nullable()->after('uuid')->constrained('acct_organizations')->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique()->after('status');
            $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->string('recipient_name')->nullable()->after('recipient_email');
            $table->string('subject')->nullable()->after('template_key');
            $table->json('payload_snapshot')->nullable()->after('subject');
            $table->string('provider', 50)->nullable()->after('attempt_count');
            $table->unsignedBigInteger('requested_by')->nullable()->after('last_error');
            $table->timestamp('started_at')->nullable()->after('queued_at');
            $table->timestamp('completed_at')->nullable()->after('sent_at');
        });

        Schema::create('acct_email_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained('acct_email_deliveries')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('status', 30)->default('sending')->index();
            $table->string('provider', 50)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['delivery_id', 'attempt_no'], 'acct_email_attempt_delivery_no_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acct_email_delivery_attempts');

        Schema::table('acct_email_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn([
                'uuid',
                'organization_id',
                'idempotency_key',
                'request_fingerprint',
                'recipient_name',
                'subject',
                'payload_snapshot',
                'provider',
                'requested_by',
                'started_at',
                'completed_at',
            ]);
        });

        Schema::table('acct_exports', function (Blueprint $table): void {
            $table->dropColumn([
                'uuid',
                'definition_version',
                'idempotency_key',
                'request_fingerprint',
                'timezone',
                'mime_type',
                'original_name',
                'byte_size',
                'row_count',
                'snapshot_at',
                'started_at',
                'expires_at',
            ]);
        });
    }
};
