<?php

namespace Tests\Feature\Chats;

use App\Chats\Models\ChatMessage;
use App\Chats\Models\ChatThread;
use App\Chats\Services\WhatsAppIngest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seo_pipeline' => false, 'services.wuzapi.webhook_secret' => self::SECRET]);

        foreach (['customers', 'contacts'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id(); $t->string('first_name'); $t->string('last_name')->nullable();
                $t->string('phone')->nullable();
                if ($table === 'contacts') {
                    $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('company_id')->nullable();
                }
                $t->timestamps();
            });
        }
        Schema::create('tasks', fn (Blueprint $t) => $t->id());
        (require database_path('migrations/2026_10_05_100001_create_chat_tables.php'))->up();
    }

    private function hook(array $info, array $message = ['conversation' => 'Tere, leht on maas'])
    {
        $info += ['ID' => uniqid(), 'IsFromMe' => false, 'IsGroup' => false, 'PushName' => 'Mari', 'Timestamp' => '2026-10-05T10:00:00+03:00'];

        return $this->postJson('/api/chats/whatsapp/' . self::SECRET, [
            'type' => 'Message', 'userID' => '1', 'event' => ['Info' => $info, 'Message' => $message],
        ]);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $this->postJson('/api/chats/whatsapp/nope', ['type' => 'Message'])->assertNotFound();
    }

    public function test_unknown_sender_keeps_only_thread_metadata(): void
    {
        $this->hook(['Chat' => '37250000000@s.whatsapp.net', 'Sender' => '37250000000@s.whatsapp.net'])->assertOk();

        $thread = ChatThread::sole();
        $this->assertFalse($thread->is_monitored);
        $this->assertSame('Mari', $thread->name);
        $this->assertSame('37250000000', $thread->phone);
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_client_with_lid_addressing_is_linked_and_both_directions_share_a_thread(): void
    {
        DB::table('customers')->insert(['id' => 7, 'first_name' => 'Mari']);
        DB::table('contacts')->insert(['id' => 3, 'first_name' => 'Mari', 'phone' => '+372 5551 2345', 'customer_id' => 7]);

        $this->hook(['Chat' => '1234567@lid', 'Sender' => '1234567@lid', 'SenderAlt' => '37255512345:3@s.whatsapp.net'])->assertOk();
        $this->hook(['Chat' => '1234567@lid', 'Sender' => '37299999999@s.whatsapp.net', 'RecipientAlt' => '37255512345@s.whatsapp.net',
                     'IsFromMe' => true, 'PushName' => 'Veiko'], ['extendedTextMessage' => ['text' => 'Vaatan kohe']])->assertOk();

        $thread = ChatThread::sole();
        $this->assertSame('37255512345@s.whatsapp.net', $thread->external_id);
        $this->assertTrue($thread->is_monitored);
        $this->assertSame(3, $thread->contact_id);
        $this->assertSame(7, $thread->customer_id);
        $this->assertSame('Mari', $thread->name);
        $this->assertSame(['in', 'out'], ChatMessage::orderBy('id')->pluck('direction')->all());
        $this->assertSame('Vaatan kohe', ChatMessage::where('direction', 'out')->value('body'));
    }

    public function test_duplicate_delivery_is_stored_once_and_reactions_are_skipped(): void
    {
        DB::table('customers')->insert(['first_name' => 'Jaan', 'phone' => '5551 0000']);
        $info = ['ID' => 'ABC', 'Chat' => '37255510000@s.whatsapp.net', 'Sender' => '37255510000@s.whatsapp.net'];

        $this->hook($info);
        $this->hook($info);
        $this->hook(['Chat' => '37255510000@s.whatsapp.net'], ['reactionMessage' => ['text' => '👍']]);
        $this->hook(['Chat' => '37255510000@s.whatsapp.net'], ['imageMessage' => ['caption' => 'kuvatõmmis']]);

        $this->assertSame(['Tere, leht on maas', '📷 Pilt: kuvatõmmis'],
            ChatMessage::orderBy('id')->pluck('body')->all());
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('37255512345', WhatsAppIngest::normalizePhone('+372 5551 2345'));
        $this->assertSame('37255512345', WhatsAppIngest::normalizePhone('5551 2345'));
        $this->assertSame('37255512345', WhatsAppIngest::normalizePhone('00372 55512345'));
        $this->assertSame('358401234567', WhatsAppIngest::normalizePhone('+358 40 123 4567'));
        $this->assertNull(WhatsAppIngest::normalizePhone(''));
    }
}
