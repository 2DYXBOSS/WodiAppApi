<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactMessageRequest;
use App\Models\ContactMessage;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ContactMessageController extends Controller
{
    /** 201 uniquement si le message est réellement enregistré. */
    public function store(ContactMessageRequest $request, Notifier $notifier): JsonResponse
    {
        $data = $request->validated();
        $spam = filled($data['website'] ?? null);

        $message = ContactMessage::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => $spam ? 'spam' : 'new',
            'ip_hash' => hash('sha256', $request->ip().config('app.key')),
        ]);

        if (! $spam) {
            $notifier->toAdmins('contact_message', 'Nouveau message de contact',
                Str::limit("{$message->name} — {$message->subject}", 140), '/admin/contact-messages/'.$message->id, 'contact');
        }

        // Réponse identique pour un robot (pot de miel) : il ne sait pas qu'il a été détecté.
        return response()->json(['id' => $message->id], 201);
    }
}
