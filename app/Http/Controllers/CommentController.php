<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function markAsRead(Comment $comment)
    {
        $comment->userReads()->updateOrCreate(
            ['user_id' => Auth::id()],
            ['read_at' => now()]
        );

        return response()->json(['success' => true]);
    }

    public function edit(Comment $comment)
    {
        if ($comment->user_id !== Auth::id()) {
            abort(403, 'Sul pole õigust seda kommentaari muuta');
        }

        return view('comments.edit', compact('comment'));
    }

    public function update(Request $request, Comment $comment)
    {
        if ($comment->user_id !== Auth::id()) {
            abort(403, 'Sul pole õigust seda kommentaari muuta');
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $comment->update($validated);

        return redirect()->back()
            ->with('success', 'Kommentaar uuendatud');
    }

    public function destroy(Comment $comment)
    {
        if ($comment->user_id !== Auth::id()) {
            abort(403, 'Sul pole õigust seda kommentaari kustutada');
        }

        $comment->delete();

        return redirect()->back()
            ->with('success', 'Kommentaar kustutatud');
    }

    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $comment = new Comment([
            'content' => $validated['content'],
            'user_id' => Auth::id(),
        ]);

        $task->comments()->save($comment);

        return redirect()->back()->with('success', 'Kommentaar lisatud.');
    }
}
