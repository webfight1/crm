<?php

namespace Tests\Feature\Chats;

use App\Chats\Models\ChatMessage;
use App\Chats\Models\ChatThread;
use App\Chats\Services\MessengerBridge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessengerTest extends TestCase
{
    private const HS = 'hs-secret';
    private const ROOM = '!dm:wf.local';
    private const MARI = '@facebook_100:wf.local';
    private const ME = '@facebook_999:wf.local';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seo_pipeline' => false, 'cache.default' => 'array',
                'services.messenger.hs_token' => self::HS, 'services.messenger.matrix_user' => '@veiko:wf.local']);
        Cache::forever('chats.messenger.self_id', '999');

        foreach (['customers', 'contacts'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->string('first_name'); $t->string('last_name')->nullable(); $t->string('phone')->nullable();
                $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('company_id')->nullable();
                $t->timestamps();
            });
        }
        Schema::create('tasks', fn (Blueprint $t) => $t->id());
        (require database_path('migrations/2026_10_05_100001_create_chat_tables.php'))->up();
    }

    private function txn(array $events, string $token = self::HS)
    {
        return $this->withToken($token)->putJson('/api/chats/matrix/_matrix/app/v1/transactions/' . uniqid(), ['events' => $events]);
    }

    private function msg(string $sender, string $body, array $extra = []): array
    {
        return ['type' => 'm.room.message', 'room_id' => self::ROOM, 'sender' => $sender, 'event_id' => '$' . uniqid(),
                'origin_server_ts' => 1759658400000, 'content' => ['msgtype' => 'm.text', 'body' => $body] + $extra];
    }

    public function test_rejects_wrong_hs_token(): void
    {
        $this->txn([], 'nope')->assertForbidden();
    }

    public function test_portal_matched_by_name_stores_both_directions(): void
    {
        DB::table('customers')->insert(['id' => 5, 'first_name' => 'Mari', 'last_name' => 'Maasikas']);

        $this->txn([
            ['type' => 'm.room.name', 'room_id' => self::ROOM, 'sender' => '@facebookbot:wf.local', 'state_key' => '', 'content' => ['name' => 'Mari Maasikas']],
            ['type' => 'm.room.member', 'room_id' => self::ROOM, 'sender' => self::MARI, 'state_key' => self::MARI, 'content' => ['membership' => 'join', 'displayname' => 'Mari Maasikas']],
            ['type' => 'm.room.member', 'room_id' => self::ROOM, 'sender' => self::ME, 'state_key' => self::ME, 'content' => ['membership' => 'join', 'displayname' => 'Veiko']],
            $this->msg(self::MARI, 'Tere! Kas saate e-poe lisada?'),
            $this->msg(self::ME, 'Jah, saadan pakkumise'),
            $this->msg('@facebookbot:wf.local', 'bridge notice'),
            ['type' => 'm.reaction', 'room_id' => self::ROOM, 'sender' => self::MARI, 'event_id' => '$r', 'content' => []],
        ])->assertOk()->assertExactJson([]);

        $thread = ChatThread::sole();
        $this->assertSame('messenger', $thread->network);
        $this->assertSame('Mari Maasikas', $thread->name);
        $this->assertSame(5, $thread->customer_id);
        $this->assertTrue($thread->is_monitored);
        $this->assertFalse($thread->is_group);
        $this->assertSame([['in', 'Tere! Kas saate e-poe lisada?', 'Mari Maasikas'], ['out', 'Jah, saadan pakkumise', null]],
            ChatMessage::orderBy('id')->get()->map(fn ($m) => [$m->direction, $m->body, $m->sender_name])->all());
    }

    public function test_unknown_person_keeps_only_metadata_and_media_is_labelled(): void
    {
        $this->txn([
            ['type' => 'm.room.name', 'room_id' => self::ROOM, 'state_key' => '', 'sender' => '@facebookbot:wf.local', 'content' => ['name' => 'Võõras']],
            $this->msg(self::MARI, 'hei'),
        ])->assertOk();

        $thread = ChatThread::sole();
        $this->assertFalse($thread->is_monitored);
        $this->assertNotNull($thread->last_message_at);
        $this->assertSame(0, ChatMessage::count());

        $thread->update(['is_monitored' => true]);
        $this->txn([
            ['type' => 'm.room.message', 'room_id' => self::ROOM, 'sender' => self::MARI, 'event_id' => '$img',
             'origin_server_ts' => 1759658400000, 'content' => ['msgtype' => 'm.image', 'body' => 'screenshot.png']],
        ]);
        $this->assertSame('📷 Pilt: screenshot.png', ChatMessage::sole()->body);
    }

    public function test_parse_cookies_from_curl_header_and_json(): void
    {
        $curl = "curl 'https://www.messenger.com/' -H 'accept: */*' -H 'cookie: datr=D1; sb=S1; c_user=100; xs=X%3A1; wd=1x1' --compressed";
        $this->assertSame(['datr' => 'D1', 'sb' => 'S1', 'c_user' => '100', 'xs' => 'X%3A1'], MessengerBridge::parseCookies($curl));
        $this->assertSame(['c_user' => '1', 'xs' => '2', 'datr' => '3'], MessengerBridge::parseCookies('c_user=1; xs=2; datr=3; presence=x'));
        $this->assertSame(['c_user' => '1', 'xs' => '2'], MessengerBridge::parseCookies('{"c_user":"1","xs":"2","other":"z"}'));
    }
}
