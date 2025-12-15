<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Controllers\Portal;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $tickets = AffiliateSupportTicket::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'tickets' => $tickets,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'category' => 'nullable|string|in:general,payout,technical,commission,other',
            'priority' => 'nullable|string|in:low,normal,high',
        ]);

        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $ticket = AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'] ?? 'general',
            'priority' => $validated['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        $ticket->messages()->create([
            'affiliate_id' => $affiliate->id,
            'message' => $validated['message'],
            'is_staff_reply' => false,
        ]);

        return response()->json([
            'ticket' => $ticket->load('messages'),
            'message' => 'Support ticket created successfully.',
        ], 201);
    }

    public function show(Request $request, string $ticketId): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $ticket = AffiliateSupportTicket::query()
            ->where('affiliate_id', $affiliate->id)
            ->with('messages')
            ->findOrFail($ticketId);

        return response()->json([
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, string $ticketId): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $ticket = AffiliateSupportTicket::query()
            ->where('affiliate_id', $affiliate->id)
            ->findOrFail($ticketId);

        if ($ticket->status === 'closed') {
            return response()->json([
                'error' => 'Cannot reply to a closed ticket.',
            ], 422);
        }

        $ticket->messages()->create([
            'affiliate_id' => $affiliate->id,
            'message' => $validated['message'],
            'is_staff_reply' => false,
        ]);

        $ticket->update(['status' => 'awaiting_response']);

        return response()->json([
            'ticket' => $ticket->fresh()->load('messages'),
            'message' => 'Reply added successfully.',
        ]);
    }

    public function close(Request $request, string $ticketId): JsonResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->attributes->get('affiliate') ?? $request->user();

        $ticket = AffiliateSupportTicket::query()
            ->where('affiliate_id', $affiliate->id)
            ->findOrFail($ticketId);

        $ticket->update(['status' => 'closed']);

        return response()->json([
            'ticket' => $ticket,
            'message' => 'Ticket closed successfully.',
        ]);
    }
}
