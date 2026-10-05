<?php

namespace App\Http\Controllers\Chats;

use App\Chats\Models\ChatMessage;
use App\Chats\Models\ChatThread;
use App\Chats\Services\ChatTaskFactory;
use App\Chats\Services\ChatTriage;
use App\Chats\Services\MessengerBridge;
use App\Chats\Services\WuzApi;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Vestlused — read-only mirror of the personal WhatsApp: monitored client
 * threads, linking to customers/contacts, tasks from messages, AI triage
 * and the QR connect page.
 */
class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $monitored = ChatThread::with(['customer', 'contact'])
            ->where('is_monitored', true)
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('direction', 'in')->whereNull('read_at')])
            ->with('lastMessage')
            ->orderByDesc('last_message_at')
            ->get();

        $others = ChatThread::where('is_monitored', false)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%' . $request->q . '%')
                ->orWhere('phone', 'like', '%' . preg_replace('/\D/', '', $request->q) . '%')))
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        return view('chats.index', compact('monitored', 'others'));
    }

    public function show(ChatThread $thread): View
    {
        $thread->load(['customer', 'contact']);
        $messages = $thread->messages()->with('task')->latest('sent_at')->limit(300)->get()->reverse();

        $thread->messages()->where('direction', 'in')->whereNull('read_at')->update(['read_at' => now()]);

        return view('chats.show', [
            'thread'    => $thread,
            'messages'  => $messages,
            'customers' => Customer::orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'contacts'  => Contact::orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'customer_id']),
            'aiEnabled' => app(ChatTriage::class)->enabled(),
        ]);
    }

    public function update(Request $request, ChatThread $thread): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'contact_id'  => 'nullable|exists:contacts,id',
        ]);

        // A contact already knows its customer.
        if (! empty($data['contact_id']) && empty($data['customer_id'])) {
            $data['customer_id'] = Contact::find($data['contact_id'])->customer_id;
        }

        $thread->update($data + [
            'is_monitored' => $request->boolean('is_monitored'),
            'auto_ai'      => $request->boolean('auto_ai'),
        ]);

        return back()->with('success', 'Salvestatud.');
    }

    public function monitor(ChatThread $thread): RedirectResponse
    {
        $thread->update(['is_monitored' => true]);

        return redirect()->route('chats.show', $thread)
            ->with('success', 'Jälgin seda vestlust — uued sõnumid salvestatakse siit edasi.');
    }

    public function triage(ChatThread $thread, ChatTriage $triage): RedirectResponse
    {
        return $triage->run($thread)
            ? back()->with('success', 'AI analüüs valmis.')
            : back()->with('error', 'AI analüüs ebaõnnestus (pole sõnumeid või OpenAI ei vastanud).');
    }

    public function taskFromMessage(ChatMessage $message, ChatTaskFactory $factory): RedirectResponse
    {
        $task = $factory->fromMessage($message, Auth::id());

        return redirect()->route('tasks.edit', $task)->with('success', 'Ülesanne loodud sõnumist.');
    }

    public function taskFromTriage(ChatThread $thread, ChatTaskFactory $factory): RedirectResponse
    {
        $task = $factory->fromTriage($thread, Auth::id());

        return $task
            ? redirect()->route('tasks.edit', $task)->with('success', 'Ülesanne loodud AI ettepanekust.')
            : back()->with('error', 'AI ei pakkunud ülesannet.');
    }

    public function connect(WuzApi $wuz, MessengerBridge $messenger): View
    {
        return view('chats.connect', [
            'enabled'          => $wuz->enabled(),
            'messengerEnabled' => $messenger->enabled(),
            'messenger'        => $messenger->enabled() ? $messenger->status() : null,
        ]);
    }

    public function messengerLogin(Request $request, MessengerBridge $messenger): RedirectResponse
    {
        $site = $request->input('site') === 'messenger' ? 'messenger' : 'facebook';
        $cookies = $request->validate([
            'c_user' => 'required|string|max:64',
            'xs'     => 'required|string|max:512',
            'datr'   => 'required|string|max:128',
        ]);
        // Tolerate a pasted "name=value" or stray quotes/spaces.
        $cookies = array_map(fn ($v) => trim(preg_replace('/^(c_user|xs|datr)=/', '', trim($v)), " \t\"';"), $cookies);

        $error = $messenger->login($cookies, $site);

        return $error === null
            ? back()->with('success', 'Messenger ühendatud. Vestlused ilmuvad paari minuti jooksul.')
            : back()->with('error', 'Messenger: ' . $error);
    }

    public function messengerLogout(MessengerBridge $messenger): RedirectResponse
    {
        $messenger->logout();

        return back()->with('success', 'Messenger lahti ühendatud.');
    }

    public function connectStart(WuzApi $wuz): RedirectResponse
    {
        return $wuz->connect() !== null
            ? back()->with('success', 'Ühendan… skaneeri QR-kood telefoniga (WhatsApp → Lingitud seadmed).')
            : back()->with('error', 'WuzAPI ei vastanud.');
    }

    public function connectStatus(WuzApi $wuz): JsonResponse
    {
        $status = $wuz->status();

        return response()->json([
            'reachable' => $status !== null,
            'connected' => $status['connected'] ?? false,
            'loggedIn'  => $status['loggedIn'] ?? false,
            'qr'        => ($status['loggedIn'] ?? true) ? null : $status['qr'],
        ]);
    }

    public function logout(WuzApi $wuz): RedirectResponse
    {
        $wuz->logout();

        return back()->with('success', 'WhatsApp lahti ühendatud.');
    }
}
