<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUpdateType;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    /**
     * Buat tiket baru dalam transaksi DB.
     * Nomor tiket TKT-YYYYMM-{id 6 digit} digenerate tanpa meninggalkan nilai sementara.
     */
    public function createTicket(array $data, User $creator): Ticket
    {
        return DB::transaction(function () use ($data, $creator) {
            $customerId = $data['customer_id'] ?? null;
            $customer = $customerId ? Customer::find($customerId) : null;

            $reporterName = $customer ? $customer->name : ($data['reporter_name'] ?? '');
            $reporterPhone = $customer ? ($customer->phone ?? '') : ($data['reporter_phone'] ?? '');
            $popId = $customer ? $customer->pop_id : ($data['pop_id'] ?? null);

            $ticket = Ticket::createWithNumber([
                'customer_id'       => $customer?->id,
                'pop_id'            => $popId,
                'reporter_name'     => $reporterName,
                'reporter_phone'    => $reporterPhone,
                'category'          => $data['category'],
                'channel'           => $data['channel'],
                'priority'          => $data['priority'] ?? TicketPriority::NORMAL->value,
                'subject'           => $data['subject'],
                'description'       => $data['description'],
                'status'            => TicketStatus::OPEN,
                'created_by'        => $creator->id,
                'assigned_to'       => $data['assigned_to'] ?? null,
                'opened_at'         => now(),
                'first_response_at' => null,
                'resolved_at'       => null,
                'closed_at'         => null,
                'resolution_note'   => null,
            ]);

            // Jika langsung ada penugasan awal, catat di updates
            if (! empty($data['assigned_to'])) {
                $assignee = User::find($data['assigned_to']);
                TicketUpdate::create([
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $creator->id,
                    'type'        => TicketUpdateType::ASSIGNMENT,
                    'body'        => 'Tiket ditugaskan ke ' . ($assignee?->name ?? 'staf'),
                    'created_at'  => now(),
                ]);
            }

            ActivityLogger::log('CREATE_TICKET', 'Tiket baru dibuat: ' . $ticket->ticket_number, $ticket, $ticket->pop_id, $creator->id);

            return $ticket;
        });
    }

    /**
     * Perbarui data umum tiket (subject, priority, category, channel, dll).
     */
    public function updateTicket(Ticket $ticket, array $data, User $user): Ticket
    {
        if ($ticket->status === TicketStatus::CLOSED) {
            throw ValidationException::withMessages([
                'status' => 'Tiket yang sudah ditutup (CLOSED) tidak dapat diubah.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $data, $user) {
            $ticket->update([
                'category'    => $data['category'] ?? $ticket->category,
                'channel'     => $data['channel'] ?? $ticket->channel,
                'priority'    => $data['priority'] ?? $ticket->priority,
                'subject'     => $data['subject'] ?? $ticket->subject,
                'description' => $data['description'] ?? $ticket->description,
            ]);

            ActivityLogger::log('UPDATE_TICKET', 'Informasi tiket diperbarui', $ticket, $ticket->pop_id, $user->id);

            return $ticket;
        });
    }

    /**
     * Transisi status tiket.
     */
    public function changeStatus(Ticket $ticket, TicketStatus|string $targetStatus, ?string $note, User $user): Ticket
    {
        $target = $targetStatus instanceof TicketStatus ? $targetStatus : TicketStatus::from($targetStatus);
        $current = $ticket->status;

        if ($current === TicketStatus::CLOSED) {
            throw ValidationException::withMessages([
                'status' => 'Tiket telah ditutup (CLOSED) secara final dan tidak dapat diubah.',
            ]);
        }

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Transisi status dari %s ke %s tidak diizinkan.', $current->label(), $target->label()),
            ]);
        }

        if ($target === TicketStatus::RESOLVED && empty(trim((string) $note))) {
            throw ValidationException::withMessages([
                'resolution_note' => 'Catatan penyelesaian wajib diisi saat menyelesaikan tiket.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $current, $target, $note, $user) {
            $updates = [
                'status' => $target,
            ];

            // Set first_response_at saat pertama kali diproses oleh NOC (jika masih null)
            if ($ticket->first_response_at === null && in_array($target, [TicketStatus::IN_PROGRESS, TicketStatus::WAITING_CUSTOMER, TicketStatus::FIELD_VISIT, TicketStatus::RESOLVED], true)) {
                $updates['first_response_at'] = now();
            }

            if ($target === TicketStatus::RESOLVED) {
                $updates['resolved_at'] = now();
                $updates['resolution_note'] = $note;
            } elseif ($target === TicketStatus::CLOSED) {
                $updates['closed_at'] = now();
            } elseif ($target === TicketStatus::IN_PROGRESS && $current === TicketStatus::RESOLVED) {
                // Reopen
                $updates['resolved_at'] = null;
            }

            $ticket->update($updates);

            TicketUpdate::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $user->id,
                'type'        => TicketUpdateType::STATUS_CHANGE,
                'from_status' => $current,
                'to_status'   => $target,
                'body'        => $note,
                'created_at'  => now(),
            ]);

            ActivityLogger::log('CHANGE_TICKET_STATUS', sprintf('Status diubah dari %s ke %s', $current->value, $target->value), $ticket, $ticket->pop_id, $user->id);

            return $ticket;
        });
    }

    /**
     * Tugaskan tiket ke staf NOC.
     */
    public function assignTicket(Ticket $ticket, ?int $assigneeId, User $user): Ticket
    {
        if ($ticket->status === TicketStatus::CLOSED) {
            throw ValidationException::withMessages([
                'status' => 'Tiket yang sudah ditutup tidak dapat ditugaskan ulang.',
            ]);
        }

        $assignee = $assigneeId ? User::find($assigneeId) : null;

        return DB::transaction(function () use ($ticket, $assignee, $user) {
            $ticket->update([
                'assigned_to' => $assignee?->id,
            ]);

            $body = $assignee
                ? 'Tiket ditugaskan kepada ' . $assignee->name
                : 'Penugasan tiket dihapus';

            TicketUpdate::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $user->id,
                'type'        => TicketUpdateType::ASSIGNMENT,
                'body'        => $body,
                'created_at'  => now(),
            ]);

            ActivityLogger::log('ASSIGN_TICKET', 'Penugasan tiket diperbarui', $ticket, $ticket->pop_id, $user->id);

            return $ticket;
        });
    }

    /**
     * Tambahkan catatan internal ke tiket.
     */
    public function addNote(Ticket $ticket, string $body, User $user): TicketUpdate
    {
        if ($ticket->status === TicketStatus::CLOSED) {
            throw ValidationException::withMessages([
                'status' => 'Tidak dapat menambahkan catatan pada tiket yang sudah ditutup.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $body, $user) {
            $update = TicketUpdate::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $user->id,
                'type'        => TicketUpdateType::NOTE,
                'body'        => $body,
                'created_at'  => now(),
            ]);

            ActivityLogger::log('ADD_TICKET_NOTE', 'Catatan internal ditambahkan', $ticket, $ticket->pop_id, $user->id);

            return $update;
        });
    }
}

