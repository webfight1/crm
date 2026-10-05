<?php

namespace App\Chats\Services;

use App\Chats\Models\ChatThread;
use App\Seo\Services\SeoAi;

/**
 * Reads the latest messages of a client conversation and asks the AI whether
 * something needs doing: a summary, a ready task (title/description/priority)
 * and a reply draft to paste into WhatsApp. Result is kept on the thread.
 */
class ChatTriage
{
    private const SYSTEM = <<<'TXT'
Sa oled veebiagentuuri Webfight (veebilehed, e-poed, SEO, hooldus) assistent.
Saad kliendi WhatsAppi/Messengeri vestluse viimased sõnumid ("Klient:" = klient, "Mina:" = Veiko).
Otsusta, kas kliendil on pooleli soov, küsimus või probleem, mis vajab Veiko tegevust.
Vasta AINULT JSON-ina:
{"needs_action": true|false,
 "summary": "1–2 lauset, mida klient tahab / mis seis on",
 "task_title": "lühike käskiv pealkiri (kui needs_action), muidu null",
 "task_description": "mida täpselt teha, olulised detailid sõnumitest (kui needs_action), muidu null",
 "priority": "low|medium|high|urgent",
 "reply_draft": "lühike sõbralik vastuse mustand eesti keeles Veiko nimel (või null kui pole vaja vastata)"}
Kui viimane sõnum on Veikolt ja klient pole uut soovi esitanud, siis needs_action=false.
TXT;

    public function __construct(private SeoAi $ai) {}

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    public function run(ChatThread $thread): ?array
    {
        $messages = $thread->messages()->latest('sent_at')->limit(30)->get()->reverse();
        if ($messages->isEmpty()) {
            return null;
        }

        $transcript = $messages->map(fn ($m) => sprintf(
            '[%s] %s: %s',
            $m->sent_at->format('d.m H:i'),
            $m->isInbound() ? 'Klient' : 'Mina',
            $m->body
        ))->implode("\n");

        $result = $this->ai->json(self::SYSTEM, "Klient: {$thread->displayName()} ({$thread->networkLabel()})\n\n{$transcript}");
        if ($result === null) {
            return null;
        }

        $result['at'] = now()->toIso8601String();
        $thread->update(['ai_triage' => $result, 'triaged_at' => now()]);

        return $result;
    }
}
