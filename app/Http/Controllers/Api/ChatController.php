<?php

namespace App\Http\Controllers\Api;

use App\Models\LiveChatSession;
use App\Models\LiveChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends BaseApiController
{
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function createSession(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'user_id' => 'nullable|integer',
        ]);

        $token = $this->generateToken();

        $session = LiveChatSession::create([
            'session_token' => $token,
            'user_id' => $request->user_id,
            'name' => $request->name,
            'email' => $request->email,
            'status' => 'open',
            'last_activity' => now(),
        ]);

        return $this->success([
            'session' => $session->toArray(),
            'messages' => [],
        ], 'Session created');
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'message' => 'nullable|string|max:2000',
        ]);

        $session = LiveChatSession::where('session_token', $request->session_token)->first();

        if (!$session) {
            return $this->error('Session not found', 404);
        }

        if ($session->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Session archived',
                'archived' => true,
            ], 410);
        }

        if ($session->last_activity && now()->diffInMinutes($session->last_activity) >= 5) {
            $session->update(['status' => 'closed']);
            return response()->json([
                'success' => false,
                'message' => 'Session archived',
                'archived' => true,
            ], 410);
        }

        $data = [
            'session_id' => $session->id,
            'sender_type' => 'user',
            'message' => $request->message ?? '',
        ];

        if ($request->filled('attachment')) {
            $data['attachment'] = $request->attachment;
            $data['attachment_type'] = $request->attachment_type;
        }

        $msg = LiveChatMessage::create($data);

        $session->update(['last_activity' => now()]);

        return $this->success($msg->toArray(), 'Message sent');
    }

    public function messages($token)
    {
        $session = LiveChatSession::where('session_token', $token)->first();
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $messages = $session->messages()->orderBy('id')->get();
        return $this->success([
            'session' => ['status' => $session->status],
            'messages' => $messages->toArray(),
        ]);
    }

    private function sendTypingEvents($sessionId, string $forSender)
    {
        $typing = DB::table('livechat_typing')
            ->where('session_id', $sessionId)
            ->where('sender_type', '!=', $forSender)
            ->first();

        if ($typing) {
            $expired = now()->diffInSeconds($typing->updated_at) > 3;
            if ($expired) {
                DB::table('livechat_typing')
                    ->where('session_id', $sessionId)
                    ->where('sender_type', $typing->sender_type)
                    ->delete();
                echo "event: typing\ndata: {\"typing\":false}\n\n";
            } else {
                $data = json_encode([
                    'typing' => true,
                    'sender_type' => $typing->sender_type,
                    'text' => $typing->text,
                ]);
                echo "event: typing\ndata: {$data}\n\n";
            }
        }
    }

    public function sse($token)
    {
        $session = LiveChatSession::where('session_token', $token)->first();
        if (!$session) {
            return response('Session not found', 404);
        }

        $response = new StreamedResponse(function () use ($session) {
            set_time_limit(0);
            ignore_user_abort(true);
            while (ob_get_level()) ob_end_clean();

            $lastId = 0;
            echo "retry: 2000\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            while (true) {
                if (connection_aborted()) break;

                $messages = LiveChatMessage::where('session_id', $session->id)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->get();

                foreach ($messages as $msg) {
                    $data = json_encode([
                        'id' => $msg->id,
                        'session_id' => $msg->session_id,
                        'sender_type' => $msg->sender_type,
                        'message' => $msg->message,
                        'created_at' => $msg->created_at->toISOString(),
                    ]);
                    echo "id: {$msg->id}\nevent: message\ndata: {$data}\n\n";
                    $lastId = $msg->id;
                }

                $this->sendTypingEvents($session->id, 'user');

                if (ob_get_level()) ob_flush();
                flush();
                if (connection_aborted()) break;
                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    public function adminSse($id, Request $request)
    {
        $apiKey = config('app.api_key');
        $requestKey = $request->query('api_key');

        if (!$apiKey || $requestKey !== $apiKey) {
            return response('Unauthorized', 401);
        }

        $session = LiveChatSession::find($id);
        if (!$session) {
            return response('Session not found', 404);
        }

        $response = new StreamedResponse(function () use ($session) {
            set_time_limit(0);
            ignore_user_abort(true);
            while (ob_get_level()) ob_end_clean();

            $lastId = 0;
            echo "retry: 2000\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            while (true) {
                if (connection_aborted()) break;

                $messages = LiveChatMessage::where('session_id', $session->id)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->get();

                foreach ($messages as $msg) {
                    $data = json_encode([
                        'id' => $msg->id,
                        'session_id' => $msg->session_id,
                        'sender_type' => $msg->sender_type,
                        'message' => $msg->message,
                        'created_at' => $msg->created_at->toISOString(),
                    ]);
                    echo "id: {$msg->id}\nevent: message\ndata: {$data}\n\n";
                    $lastId = $msg->id;
                }

                $this->sendTypingEvents($session->id, 'admin');

                if (ob_get_level()) ob_flush();
                flush();
                if (connection_aborted()) break;
                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    public function sessionsCountSse(Request $request)
    {
        $apiKey = config('app.api_key');
        $requestKey = $request->query('api_key');

        if (!$apiKey || $requestKey !== $apiKey) {
            return response('Unauthorized', 401);
        }

        $response = new StreamedResponse(function () {
            set_time_limit(0);
            ignore_user_abort(true);
            while (ob_get_level()) ob_end_clean();

            $lastCount = null;
            echo "retry: 5000\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            while (true) {
                if (connection_aborted()) break;

                $openCount = LiveChatSession::where('status', 'open')->count();

                if ($openCount !== $lastCount) {
                    $lastCount = $openCount;
                    $data = json_encode(['count' => $openCount]);
                    echo "event: sessions\ndata: {$data}\n\n";
                }

                if (ob_get_level()) ob_flush();
                flush();
                if (connection_aborted()) break;
                usleep(800000);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    public function unreadCount(Request $request)
    {
        try {
            $count = DB::table('livechat_messages')
                ->join('livechat_sessions', 'livechat_messages.session_id', '=', 'livechat_sessions.id')
                ->where('livechat_messages.sender_type', 'user')
                ->whereNull('livechat_messages.read_at')
                ->where('livechat_sessions.status', 'open')
                ->count();
        } catch (\Exception $e) {
            $count = 0;
        }

        return response()->json(['count' => $count]);
    }

    public function sessions(Request $request)
    {
        LiveChatSession::where('status', 'open')
            ->where(function ($q) {
                $q->whereNull('last_activity')
                  ->where('created_at', '<', now()->subMinutes(5));
            })
            ->orWhere(function ($q) {
                $q->where('status', 'open')
                  ->where('last_activity', '<', now()->subMinutes(5));
            })
            ->update(['status' => 'closed']);

        $query = LiveChatSession::withCount('messages')
            ->orderBy('last_activity', 'desc');

        if ($request->status && in_array($request->status, ['open', 'closed'])) {
            $query->where('status', $request->status);
        }

        $sessions = $query->get()->map(function ($s) {
            $lastMsg = $s->messages()->orderByDesc('id')->first();
            $unreadCount = $s->messages()->where('sender_type', 'user')->whereNull('read_at')->count();
            return [
                'id' => $s->id,
                'session_token' => $s->session_token,
                'name' => $s->name,
                'email' => $s->email,
                'status' => $s->status,
                'messages_count' => $s->messages_count,
                'unread_count' => $unreadCount,
                'last_activity' => $s->last_activity ? $s->last_activity->toISOString() : null,
                'last_message' => $lastMsg ? $lastMsg->message : null,
                'created_at' => $s->created_at->toISOString(),
                'rating' => $s->rating,
                'assigned_to' => $s->assigned_to,
                'is_offline' => $s->is_offline,
            ];
        });

        return $this->success($sessions->values()->toArray());
    }

    public function adminMessages($id)
    {
        $session = LiveChatSession::find($id);
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        LiveChatMessage::where('session_id', $session->id)
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $session->messages()->orderBy('id')->get();
        return $this->success([
            'session' => $session->toArray(),
            'messages' => $messages->toArray(),
        ]);
    }

    public function reply($id, Request $request)
    {
        $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        $session = LiveChatSession::find($id);
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $data = [
            'session_id' => $session->id,
            'sender_type' => 'admin',
            'message' => $request->message ?? '',
        ];

        if ($request->filled('attachment')) {
            $data['attachment'] = $request->attachment;
            $data['attachment_type'] = $request->attachment_type;
        }

        $msg = LiveChatMessage::create($data);

        $session->update(['last_activity' => now()]);

        return $this->success($msg->toArray(), 'Reply sent');
    }

    public function close($id)
    {
        $session = LiveChatSession::find($id);
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $session->update(['status' => 'closed']);
        return $this->success(null, 'Session closed');
    }

    public function typing(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'text' => 'nullable|string|max:500',
        ]);

        $session = LiveChatSession::where('session_token', $request->session_token)->first();
        if (!$session || $session->status !== 'open') {
            return $this->error('Session not found or closed', 404);
        }

        DB::table('livechat_typing')->updateOrInsert(
            ['session_id' => $session->id, 'sender_type' => 'user'],
            ['text' => $request->text, 'updated_at' => now()]
        );

        return $this->success(null, 'OK');
    }

    public function typingStatus($token)
    {
        $session = LiveChatSession::where('session_token', $token)->first();
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $typing = DB::table('livechat_typing')
            ->where('session_id', $session->id)
            ->where('sender_type', '!=', 'user')
            ->first();

        if (!$typing) {
            return $this->success(['typing' => false]);
        }

        $expired = now()->diffInSeconds($typing->updated_at) > 3;
        if ($expired) {
            DB::table('livechat_typing')
                ->where('session_id', $session->id)
                ->where('sender_type', $typing->sender_type)
                ->delete();
            return $this->success(['typing' => false]);
        }

        return $this->success([
            'typing' => true,
            'sender_type' => $typing->sender_type,
            'text' => $typing->text,
        ]);
    }

    public function adminTypingStatus($id)
    {
        $session = LiveChatSession::find($id);
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $typing = DB::table('livechat_typing')
            ->where('session_id', $session->id)
            ->where('sender_type', '!=', 'admin')
            ->first();

        if (!$typing) {
            return $this->success(['typing' => false]);
        }

        $expired = now()->diffInSeconds($typing->updated_at) > 3;
        if ($expired) {
            DB::table('livechat_typing')
                ->where('session_id', $session->id)
                ->where('sender_type', $typing->sender_type)
                ->delete();
            return $this->success(['typing' => false]);
        }

        return $this->success([
            'typing' => true,
            'sender_type' => $typing->sender_type,
            'text' => $typing->text,
        ]);
    }

    public function openCount()
    {
        try {
            $count = LiveChatSession::where('status', 'open')->count();
        } catch (\Exception $e) {
            $count = 0;
        }

        return response()->json(['count' => $count]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'file' => 'required|file|max:10240',
        ]);

        $session = LiveChatSession::where('session_token', $request->session_token)->first();
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $name = 'chat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $file->storeAs('chat-uploads', $name, 'public');
        $url = url('storage/' . $path);

        $msg = LiveChatMessage::create([
            'session_id' => $session->id,
            'sender_type' => 'user',
            'message' => '',
            'attachment' => $url,
            'attachment_type' => $file->getMimeType(),
        ]);

        $session->update(['last_activity' => now()]);

        return $this->success([
            'id' => $msg->id,
            'url' => $url,
            'message' => '',
            'type' => $file->getMimeType(),
        ], 'File uploaded');
    }

    public function rating(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $session = LiveChatSession::where('session_token', $request->session_token)->first();
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $session->update(['rating' => $request->rating]);

        return $this->success(null, 'Rating saved');
    }

    public function assign(Request $request)
    {
        $request->validate([
            'session_id' => 'required|integer',
            'admin_id' => 'nullable|integer',
        ]);

        $session = LiveChatSession::find($request->session_id);
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $session->update(['assigned_to' => $request->admin_id]);
        return $this->success(null, 'Assigned');
    }

    public function adminUpload($id, Request $request)
    {
        $request->validate(['file' => 'required|file|max:10240']);

        $session = LiveChatSession::find($id);
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $name = 'admin_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $file->storeAs('chat-uploads', $name, 'public');
        $url = url('storage/' . $path);

        $msg = LiveChatMessage::create([
            'session_id' => $session->id,
            'sender_type' => 'admin',
            'message' => '',
            'attachment' => $url,
            'attachment_type' => $file->getMimeType(),
        ]);

        $session->update(['last_activity' => now()]);

        return $this->success([
            'id' => $msg->id,
            'url' => $url,
            'message' => '',
            'type' => $file->getMimeType(),
        ], 'File uploaded');
    }

    public function adminTyping($id, Request $request)
    {
        $session = LiveChatSession::find($id);
        if (!$session) {
            return $this->error('Session not found', 404);
        }

        DB::table('livechat_typing')->updateOrInsert(
            ['session_id' => $session->id, 'sender_type' => 'admin'],
            ['text' => null, 'updated_at' => now()]
        );

        return $this->success(null, 'OK');
    }
}
