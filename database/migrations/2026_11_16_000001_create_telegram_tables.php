<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اتصال هر کاربر به کلاینت تلگرامش (یک حساب برای هر کاربر در فاز اول)
        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('api_id', 20);
            $table->text('api_hash');                 // رمزنگاری‌شده (cast encrypted)
            $table->string('phone', 20)->nullable();  // شماره‌ی حساب تلگرام
            // new → code_sent → (password_needed) → authorized
            $table->string('status', 20)->default('new');
            $table->boolean('is_active')->default(false); // دکمه‌ی روشن/خاموش
            $table->string('tg_user_id', 30)->nullable();
            $table->string('tg_name')->nullable();
            $table->string('tg_username')->nullable();
            $table->string('password_hint')->nullable();
            $table->timestamp('connected_at')->nullable(); // لحظه‌ی روشن شدن؛ پیام‌ها از این به بعد
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('synced_total')->default(0);
            $table->timestamps();
        });

        // آخرین شناسه‌ی پیام ذخیره‌شده‌ی هر گفتگو (برای دریافت فقط پیام‌های جدید)
        Schema::create('telegram_chat_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_account_id')->constrained()->cascadeOnDelete();
            $table->string('peer_id', 30);
            $table->unsignedBigInteger('last_message_id')->default(0);
            $table->timestamps();
            $table->unique(['telegram_account_id', 'peer_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_account_id')->constrained()->cascadeOnDelete();
            $table->string('chat_id', 30);
            $table->string('chat_title')->nullable();
            $table->string('chat_type', 20)->nullable(); // user | group | supergroup | channel | bot
            $table->unsignedBigInteger('message_id');
            $table->string('sender_id', 30)->nullable();
            $table->string('sender_name')->nullable();
            $table->boolean('is_outgoing')->default(false);
            $table->text('text')->nullable();
            $table->string('media_type', 40)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['telegram_account_id', 'chat_id', 'message_id']);
            $table->index(['user_id', 'sent_at']);
        });

        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('word', 100);
            $table->timestamps();
            $table->unique(['user_id', 'word']);
        });

        // پیام‌های فیلترشده در جدول جدا نگه داشته می‌شوند تا با «حذف همه» از بین نروند.
        Schema::create('filtered_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->unsignedBigInteger('telegram_account_id');
            $table->string('chat_id', 30);
            $table->string('chat_title')->nullable();
            $table->string('chat_type', 20)->nullable();
            $table->unsignedBigInteger('tg_message_id');
            $table->string('sender_name')->nullable();
            $table->boolean('is_outgoing')->default(false);
            $table->text('text')->nullable();
            $table->json('matched_keywords')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['telegram_account_id', 'chat_id', 'tg_message_id'], 'filtered_unique');
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filtered_messages');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('telegram_chat_cursors');
        Schema::dropIfExists('telegram_accounts');
    }
};
