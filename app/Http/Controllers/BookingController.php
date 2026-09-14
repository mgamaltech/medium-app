<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Slot;
use App\Models\User;
use App\Services\BookingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Cashier\Exceptions\IncompletePayment;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookingService) {}


    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer instanceof Customer) {
            return response()->json([
                'message' => 'Customer profile not found for this user.',
            ], 404);
        }

        $bookings = Booking::where('customer_id', $customer->id)
            ->with(['slot', 'customer']) 
            ->orderBy('created_at', 'desc')
            ->paginate();

        return response()->json($bookings);
    }
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key');

        if (trim($idempotencyKey) === '') {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
            ], 422);
        }

        $slotId = $request->validated('slot_id');

        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer instanceof Customer) {
            return response()->json([
                'message' => 'Customer profile not found for this user.',
            ], 404);
        }

        $slot = Slot::findOrFail($slotId);

        try {
            $booking = $this->bookingService->createBooking($slot, $customer, $idempotencyKey);

            return response()->json([
                'message' => 'Booking created successfully',
                'data' => $booking->load('slot', 'customer'),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function confirm(ConfirmBookingRequest $request, Booking $booking)
    {
        /** @var User $user */
        $user = $request->user();
        if ($booking->customer_id !== $user->customer?->id) {
            return response()->json([
                'message' => 'You are not authorized to confirm this booking.',
            ], 403);
        }

        try {
            $confirmedBooking = $this->bookingService->confirmBooking(
                $booking,
                $request->validated('payment_method_id')
            );

            return response()->json([
                'message' => 'Booking confirmed successfully!',
                'booking' => $confirmedBooking,
            ]);
        } catch (IncompletePayment $exception) {
            $paymentIntent = $exception->payment->asStripePaymentIntent();

            return response()->json([
                'requires_action' => true,
                'payment_intent' => $paymentIntent,
                /** @phpstan-ignore-next-line */
                'redirect_url' => route('cashier.payment', [$exception->payment->id, 'redirect' => route('bookings.index')]),
            ], 402);
        }
    }

    public function reject(Request $request, Booking $booking): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($booking->customer_id !== $user->customer?->id) {
            return response()->json([
                'message' => 'You are not authorized to reject this booking.',
            ], 403);
        }

        try {
            $booking->update(['status' => 'rejected']);

            return response()->json([
                'message' => 'Booking rejected successfully',
                'booking' => $booking,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
