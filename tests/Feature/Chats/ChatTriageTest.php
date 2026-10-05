<?php

namespace Tests\Feature\Chats;

use App\Chats\Models\ChatMessage;
use App\Chats\Models\ChatThread;
use App\Models\Task;
use App\Seo\Services\SeoAi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatTriageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seo_pipeline' => false]);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->boolean('is_admin')->default(false); $t->timestamps();
        });
        foreach (['customers', 'contacts'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->string('first_name'); $t->string('last_name')->nullable(); $t->string('phone')->nullable();
                $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('company_id')->nullable();
                $t->timestamps();
            });
        }
        Schema::create('tasks', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->text('description')->nullable();
            foreach (['type', 'priority', 'status'] as $c) { $t->string($c); }
            $t->decimal('price')->default(0);
            foreach (['customer_id', 'company_id', 'contact_id', 'user_id', 'assignee_id'] as $c) { $t->unsignedBigInteger($c)->nullable(); }
            $t->timestamps(); $t->softDeletes();
        });
        (require database_path('migrations/2026_10_05_100001_create_chat_tables.php'))->up();

        DB::table('users')->insert(['id' => 1, 'name' => 'Veiko', 'is_admin' => true]);
    }

    private function fakeAi(array $answer): void
    {
        $this->app->instance(SeoAi::class, new class($answer) extends SeoAi {
            public int $calls = 0;
            public function __construct(private array $answer) {}
            public function enabled(): bool { return true; }
            public function json(string $system, string $user, int $maxTokens = 900): ?array { $this->calls++; return $this->answer; }
        });
    }

    private function thread(array $attrs = []): ChatThread
    {
        $thread = ChatThread::create($attrs + [
            'network' => 'whatsapp', 'external_id' => '37255512345@s.whatsapp.net', 'name' => 'Mari',
            'is_monitored' => true, 'auto_ai' => true, 'last_message_at' => now()->subMinutes(10),
        ]);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'external_id' => 'm1', 'direction' => 'in',
            'body' => 'Kontaktivorm ei saada kirju', 'sent_at' => now()->subMinutes(10)]);

        return $thread;
    }

    public function test_new_client_wish_creates_one_task_and_is_not_duplicated(): void
    {
        $this->fakeAi(['needs_action' => true, 'summary' => 'Vorm katki', 'task_title' => 'Paranda kontaktivorm',
                       'task_description' => 'Vorm ei saada kirju', 'priority' => 'high', 'reply_draft' => 'Vaatan kohe']);
        $thread = $this->thread();

        $this->artisan('chats:triage')->assertSuccessful();

        $task = Task::sole();
        $this->assertSame('Paranda kontaktivorm', $task->title);
        $this->assertSame('high', $task->priority);
        $this->assertSame(1, $task->user_id);
        $this->assertSame($task->id, ChatMessage::sole()->task_id);
        $this->assertSame('Vaatan kohe', $thread->fresh()->ai_triage['reply_draft']);

        // A follow-up message while the task is still open → no second task.
        ChatMessage::create(['chat_thread_id' => $thread->id, 'external_id' => 'm2', 'direction' => 'in',
            'body' => 'Kas jõuad täna?', 'sent_at' => now()->subMinutes(5)]);
        $thread->update(['last_message_at' => now()->subMinutes(5), 'triaged_at' => now()->subMinutes(6)]);
        $this->artisan('chats:triage')->assertSuccessful();

        $this->assertSame(1, Task::count());
    }

    public function test_already_triaged_and_too_recent_threads_are_skipped(): void
    {
        $this->fakeAi(['needs_action' => true, 'task_title' => 'X']);
        $this->thread(['triaged_at' => now()]);
        $this->thread(['external_id' => 'b@s.whatsapp.net', 'last_message_at' => now()->subMinute()]);

        $this->artisan('chats:triage')->assertSuccessful();

        $this->assertSame(0, app(SeoAi::class)->calls);
        $this->assertSame(0, Task::count());
    }
}
