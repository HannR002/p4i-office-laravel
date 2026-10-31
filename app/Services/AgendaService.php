<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\IncomingLetter;
use App\Models\Letter;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AgendaService
{
    /**
     * Mengambil daftar Buku Agenda Surat Keluar dengan filter pencarian (NR-11).
     */
    public function getOutgoingAgenda(Organization $org, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Letter::with(['letterType', 'author', 'unit'])
            ->where('organization_id', $org->id);

        if (! empty($filters['letter_type_id'])) {
            $query->where('letter_type_id', $filters['letter_type_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['classification'])) {
            $query->where('classification', $filters['classification']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('planned_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('planned_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim($filters['search']).'%';
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', $search)
                    ->orWhere('recipient_name', 'like', $search);
            });
        }

        return $query->orderBy('planned_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Mendaftarkan surat masuk ke Buku Agenda dengan penomoran agenda internal terpisah (NR-12).
     */
    public function registerIncomingLetter(User $user, array $data): IncomingLetter
    {
        return DB::transaction(function () use ($user, $data) {
            $year = now()->format('Y');

            // Hitung nomor urut agenda internal untuk tahun berjalan dengan lock baris
            $lastCount = IncomingLetter::whereYear('received_date', $year)
                ->lockForUpdate()
                ->count();

            $nextSeq = $lastCount + 1;
            $agendaNumber = sprintf('AG-IN-%s-%04d', $year, $nextSeq);

            $incoming = IncomingLetter::create([
                'organization_id' => $user->organization_id,
                'sender_name' => $data['sender_name'],
                'sender_letter_number' => $data['sender_letter_number'], // Nomor asli pengirim
                'internal_agenda_number' => $agendaNumber, // Nomor agenda internal P4I
                'letter_date' => $data['letter_date'],
                'received_date' => $data['received_date'] ?? now()->format('Y-m-d'),
                'subject' => $data['subject'],
                'classification' => $data['classification'] ?? 'INTERNAL',
                'recipient_unit_id' => $data['recipient_unit_id'] ?? null,
                'recorded_by' => $user->id,
                'status' => 'REGISTERED',
                'notes' => $data['notes'] ?? null,
            ]);

            // Catat audit event (NR-13)
            AuditEvent::create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'event_type' => 'INCOMING_LETTER_REGISTERED',
                'entity_type' => 'incoming_letters',
                'entity_id' => $incoming->id,
                'payload' => [
                    'sender_name' => $incoming->sender_name,
                    'sender_letter_number' => $incoming->sender_letter_number,
                    'internal_agenda_number' => $incoming->internal_agenda_number,
                    'subject' => $incoming->subject,
                ],
            ]);

            return $incoming;
        });
    }

    /**
     * Mengambil daftar Buku Agenda Surat Masuk dengan filter pencarian (NR-12).
     */
    public function getIncomingAgenda(Organization $org, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = IncomingLetter::with(['recipientUnit', 'recorder'])
            ->where('organization_id', $org->id);

        if (! empty($filters['classification'])) {
            $query->where('classification', $filters['classification']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('received_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('received_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim($filters['search']).'%';
            $query->where(function ($q) use ($search) {
                $q->where('sender_name', 'like', $search)
                    ->orWhere('sender_letter_number', 'like', $search)
                    ->orWhere('internal_agenda_number', 'like', $search)
                    ->orWhere('subject', 'like', $search);
            });
        }

        return $query->orderBy('received_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
