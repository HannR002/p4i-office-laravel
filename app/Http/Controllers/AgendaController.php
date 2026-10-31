<?php

namespace App\Http\Controllers;

use App\Models\LetterType;
use App\Models\OrgUnit;
use App\Services\AgendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(
        protected AgendaService $agendaService
    ) {}

    /**
     * Menampilkan Buku Agenda Surat Keluar (NR-11).
     */
    public function outgoing(Request $request): View
    {
        $user = Auth::user();

        $filters = $request->only([
            'letter_type_id',
            'status',
            'classification',
            'date_from',
            'date_to',
            'search',
        ]);

        $letters = $this->agendaService->getOutgoingAgenda($user->organization, $filters);
        $letterTypes = LetterType::where('organization_id', $user->organization_id)->get();

        return view('agenda.outgoing', compact('letters', 'letterTypes', 'filters'));
    }

    /**
     * Menampilkan Buku Agenda Surat Masuk (NR-12).
     */
    public function incoming(Request $request): View
    {
        $user = Auth::user();

        $filters = $request->only([
            'classification',
            'date_from',
            'date_to',
            'search',
        ]);

        $incomingLetters = $this->agendaService->getIncomingAgenda($user->organization, $filters);
        $orgUnits = OrgUnit::where('organization_id', $user->organization_id)->where('active', true)->get();

        return view('agenda.incoming', compact('incomingLetters', 'orgUnits', 'filters'));
    }

    /**
     * Menampilkan formulir registrasi surat masuk baru.
     */
    public function createIncoming(): View
    {
        $user = Auth::user();
        $orgUnits = OrgUnit::where('organization_id', $user->organization_id)->where('active', true)->get();

        return view('agenda.create_incoming', compact('orgUnits'));
    }

    /**
     * Menyimpan surat masuk baru dengan nomor agenda internal P4I otomatis (NR-12).
     */
    public function storeIncoming(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'sender_name' => 'required|string|max:255',
            'sender_letter_number' => 'required|string|max:128',
            'letter_date' => 'required|date',
            'received_date' => 'required|date',
            'subject' => 'required|string|max:1000',
            'classification' => 'required|in:INTERNAL,CONFIDENTIAL,PUBLIC',
            'recipient_unit_id' => 'nullable|uuid',
            'notes' => 'nullable|string|max:500',
        ]);

        $incoming = $this->agendaService->registerIncomingLetter($user, $validated);

        return redirect()->route('agenda.incoming')
            ->with('status', "Surat masuk berhasil diregistrasi dengan Nomor Agenda Internal: {$incoming->internal_agenda_number}");
    }
}
