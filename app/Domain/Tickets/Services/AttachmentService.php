<?php

namespace App\Domain\Tickets\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class AttachmentService
{
    public function __construct(private TicketService $tickets) {}

    public function upload(User $actor, Ticket $ticket, UploadedFile $file): TicketAttachment
    {
        Gate::forUser($actor)->authorize('comment', $ticket);
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,txt', 'extensions:pdf,png,jpg,jpeg,txt']])->validate();
        $path = $file->store('tickets/'.$ticket->public_id, 'local');
        try {
            return DB::transaction(function () use ($actor, $ticket, $file, $path) {
                $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
                Gate::forUser($actor)->authorize('comment', $locked);
                $attachment = $locked->attachments()->create(['public_id' => (string) Str::ulid(), 'user_id' => $actor->id, 'path' => $path, 'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
                $this->tickets->record($locked, $actor, 'attached', [], [], ['attachment_id' => $attachment->public_id]);

                return $attachment;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }
}
