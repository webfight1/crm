<?php

namespace App\Chats\Services;

use App\Chats\Models\ChatMessage;
use App\Chats\Models\ChatThread;
use App\Models\Task;

/**
 * Creates a CRM task from a chat — either from one message or from the
 * thread's latest AI triage — and links it back to the message.
 */
class ChatTaskFactory
{
    public function fromMessage(ChatMessage $message, int $userId): Task
    {
        $thread = $message->thread;
        $title  = 'WhatsApp: ' . $thread->displayName() . ' — ' . mb_strimwidth(preg_replace('/\s+/', ' ', (string) $message->body), 0, 80, '…');

        return $this->create($thread, $message, $userId, [
            'title'       => mb_substr($title, 0, 255),
            'description' => $message->body,
            'priority'    => 'medium',
        ]);
    }

    public function fromTriage(ChatThread $thread, int $userId): ?Task
    {
        $ai = $thread->ai_triage;
        if (! $ai || empty($ai['task_title'])) {
            return null;
        }

        return $this->create($thread, $thread->messages()->where('direction', 'in')->latest('sent_at')->first(), $userId, [
            'title'       => mb_substr($ai['task_title'], 0, 255),
            'description' => trim(($ai['task_description'] ?? '') . "\n\nKokkuvõte: " . ($ai['summary'] ?? '')),
            'priority'    => in_array($ai['priority'] ?? null, ['low', 'medium', 'high', 'urgent'], true) ? $ai['priority'] : 'medium',
        ]);
    }

    /** An unfinished task already made from this thread (so auto-triage doesn't duplicate). */
    public function openTask(ChatThread $thread): ?Task
    {
        return Task::whereIn('id', $thread->messages()->whereNotNull('task_id')->select('task_id'))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest()
            ->first();
    }

    private function create(ChatThread $thread, ?ChatMessage $message, int $userId, array $fields): Task
    {
        $fields['description'] = trim($fields['description'] . "\n\nVestlus: " . route('chats.show', $thread));

        $task = Task::create($fields + [
            'type'        => 'other',
            'status'      => 'pending',
            'price'       => 0,
            'customer_id' => $thread->customer_id,
            'contact_id'  => $thread->contact_id,
            'company_id'  => $thread->contact?->company_id,
            'user_id'     => $userId,
            'assignee_id' => $userId,
        ]);

        $message?->update(['task_id' => $task->id]);

        return $task;
    }
}
