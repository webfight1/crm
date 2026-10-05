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

    public function test_login_form_sends_cleaned_cookies_to_the_bridge(): void
    {
        config(['services.messenger.provision_secret' => 'p', 'services.messenger.provision_url' => 'http://bridge']);
        \Illuminate\Support\Facades\Http::fake([
            'bridge/v3/login/start/*' => ['login_id' => 'L1', 'step_id' => 'S1', 'type' => 'cookies'],
            'bridge/v3/login/step/*'  => ['login_id' => 'L1', 'type' => 'complete'],
            'bridge/v3/whoami*'       => ['logins' => [['id' => '999', 'name' => 'Veiko']]],
        ]);
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class);

        $this->from('/chats/connect')->post('/chats/connect/messenger', ['c_user' => ' 100 ', 'xs' => 'xs=48%3Aabc;', 'datr' => '"D1"'])
            ->assertRedirect('/chats/connect')->assertSessionHas('success');

        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), '/v3/login/start/facebook'));
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), '/v3/login/step/L1/S1/cookies')
            && $r->data() === ['c_user' => '100', 'xs' => '48%3Aabc', 'datr' => 'D1']);
    }

    public function test_member_events_before_our_ghost_is_known_do_not_name_or_link_the_thread(): void
    {
        Cache::forget('chats.messenger.self_id');
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['logins' => []])]);
        DB::table('contacts')->insert(['id' => 1, 'first_name' => 'Veiko', 'last_name' => 'Teekel']);

        $this->txn([
            ['type' => 'm.room.member', 'room_id' => self::ROOM, 'sender' => self::ME, 'state_key' => self::ME, 'content' => ['membership' => 'join', 'displayname' => 'Veiko Teekel']],
        ])->assertOk();

        $this->assertSame(0, ChatThread::count());
    }

    public function test_resync_fixes_name_group_flag_and_undoes_self_link(): void
    {
        Cache::forever('chats.messenger.self_name', 'Veiko Teekel');
        config(['services.messenger.as_token' => 'as', 'services.messenger.homeserver_url' => 'http://hs', 'services.messenger.bot' => '@facebookbot:wf.local']);
        DB::table('contacts')->insert([['id' => 1, 'first_name' => 'Veiko', 'last_name' => 'Teekel', 'customer_id' => null],
                                       ['id' => 2, 'first_name' => 'Taaniel', 'last_name' => 'Vardja', 'customer_id' => null]]);
        $thread = ChatThread::create(['network' => 'messenger', 'external_id' => self::ROOM, 'name' => 'Veiko Teekel',
            'is_group' => true, 'contact_id' => 1, 'is_monitored' => true, 'auto_ai' => true]);

        \Illuminate\Support\Facades\Http::fake([
            'hs/_matrix/client/v3/rooms/*/joined_members*' => ['joined' => [
                self::ME => ['display_name' => 'Veiko Teekel'],
                '@facebook_200:wf.local' => ['display_name' => 'Taaniel Vardja'],
                '@facebookbot:wf.local' => ['display_name' => 'Facebook bridge bot'],
                '@veiko:wf.local' => ['display_name' => 'veiko'],
            ]],
            'hs/_matrix/client/v3/rooms/*/state/m.room.name/*' => \Illuminate\Support\Facades\Http::response(['errcode' => 'M_NOT_FOUND'], 404),
            'bridge/v3/whoami*' => ['logins' => [['id' => '999', 'name' => 'Veiko Teekel']]],
        ]);
        config(['services.messenger.provision_url' => 'http://bridge']);

        $this->artisan('chats:messenger-resync')->assertSuccessful();

        $thread->refresh();
        $this->assertSame('Taaniel Vardja', $thread->name);
        $this->assertFalse($thread->is_group);
        $this->assertSame(2, $thread->contact_id);       // re-linked to the real person
        $this->assertTrue($thread->is_monitored);
        $this->assertFalse($thread->auto_ai);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r['user_id'] === '@facebookbot:wf.local' && $r->hasHeader('Authorization', 'Bearer as'));
    }
}
