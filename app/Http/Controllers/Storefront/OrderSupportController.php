<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Domain\Orders\Actions\CreateOrderSupportRequest;
use App\Http\Controllers\Controller;
use App\Mail\OrderSupportAcknowledgementMail;
use App\Models\Order;
use App\Support\GuestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class OrderSupportController extends Controller
{
    public function create(Request $request, GuestContext $guest, RecordAnalyticsEvent $analytics): View
    {
        $orders = null;
        $selectedOrderNumber = null;
        $order = null;

        if ($request->user()) {
            $orders = $request->user()->orders()->with('items')->latest('placed_at')->latest('created_at')->get();
            $requested = $request->query('order');
            if ($requested) {
                $order = $orders->firstWhere('number', $requested);
            } elseif ($orders->count() === 1) {
                $order = $orders->first();
            }
            $selectedOrderNumber = $order?->number;
        } else {
            $requested = $request->query('order');
            if ($requested) {
                $candidate = Order::query()->where('number', $requested)->first();
                if ($candidate && $guest->owns($candidate->access_token_hash, $request)) {
                    $order = $candidate;
                    $selectedOrderNumber = $candidate->number;
                }
            }
        }

        if ($order) {
            $analytics->handle('order_support_opened', $order);
        }

        return view('storefront.order-support.create', compact('orders', 'selectedOrderNumber'));
    }

    public function store(Request $request, GuestContext $guest, CreateOrderSupportRequest $create): RedirectResponse
    {
        $isGuest = ! $request->user();

        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:64'],
            'email' => [$isGuest ? 'required' : 'sometimes', 'nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'photo' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($isGuest) {
            $order = Order::query()->where('number', $data['order_number'])->first();
            $ownsViaGuestToken = $order && $guest->owns($order->access_token_hash, $request);
            $ownsViaEmail = $order && Str::lower(trim($order->email)) === Str::lower(trim($data['email']));
            if (! $order || (! $ownsViaGuestToken && ! $ownsViaEmail)) {
                return back()->withInput()->withErrors([
                    'order_details' => "We couldn't match those order details. Please check your order number and email address.",
                ]);
            }
            $contactEmail = $data['email'];
            $user = null;
        } else {
            $user = $request->user();
            $order = Order::query()->where('user_id', $user->id)->where('number', $data['order_number'])->first();
            if (! $order) {
                return back()->withInput()->withErrors([
                    'order_number' => "We couldn't find that order on your account.",
                ]);
            }
            $contactEmail = $user->email;
        }

        $photo = null;
        if ($request->hasFile('photo')) {
            $bytes = file_get_contents($request->file('photo')->getRealPath());
            $dimensions = @getimagesizefromstring($bytes);
            $mime = $dimensions['mime'] ?? null;
            if ($dimensions === false || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return back()->withInput()->withErrors([
                    'photo' => 'The uploaded photo is not a supported image. Please use JPEG, PNG or WebP.',
                ]);
            }
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            };
            $photo = ['bytes' => $bytes, 'mime' => $mime, 'extension' => $extension];
        }

        try {
            $supportRequest = $create->handle($order, $user, $contactEmail, $data['message'], $photo);
        } catch (RuntimeException) {
            return back()->withInput()->withErrors([
                'photo' => "We couldn't process your submission. Please try again.",
            ]);
        }

        // Acknowledge the request by email; a mail hiccup must not fail the submission.
        if ($contactEmail) {
            try {
                Mail::to($contactEmail)->queue(new OrderSupportAcknowledgementMail($supportRequest));
            } catch (Throwable $e) {
                report($e);
            }
        }

        $request->session()->put('order_support_reference', $supportRequest->reference);

        return redirect()->route('order-support.submitted');
    }

    public function submitted(Request $request): View|RedirectResponse
    {
        $reference = $request->session()->get('order_support_reference');
        if (! $reference) {
            return redirect()->route('order-support.create');
        }

        return view('storefront.order-support.submitted', compact('reference'));
    }
}
