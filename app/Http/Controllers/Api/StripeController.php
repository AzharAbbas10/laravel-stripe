<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StripePlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Stripe\Customer;
use Stripe\Webhook;
use Stripe\Price;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $secret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // ✅ Set Stripe API key for Price fetching
        Stripe::setApiKey(config('services.stripe.secret'));

        switch ($event->type) {
            case 'product.created':
            case 'product.updated':
                $product = $event->data->object; // Stripe\Product object
                $prices = Price::all([
                    'product' => $product->id,
                    'limit' => 1
                ]);

                if (!empty($prices->data)) {
                    $price = $prices->data[0];

                    StripePlan::updateOrCreate(
                        ['stripe_product_id' => $product->id],
                        [
                            'name' => $product->name,
                            'description' => $product->description ?? '',
                            'stripe_price_id' => $price->id,
                            'price_amount' => $price->unit_amount / 100,
                            'currency' => $price->currency
                        ]
                    );
                }
                break;

            case 'price.updated':
                $price = $event->data->object;

                StripePlan::where('stripe_price_id', $price->id)
                    ->update([
                        'price_amount' => $price->unit_amount,
                        'currency' => $price->currency
                    ]);
                break;
            case 'customer.subscription.created':
                $this->syncSubscription($event->data->object);
                break;

            case 'customer.subscription.updated':
                $this->syncSubscription($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->cancelSubscription($event->data->object);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    public function index()
    {
        $plans = StripePlan::get();

        return response()->json($plans);
    }

    public function create(Request $request)
    {
        $request->validate([
            'price_id' => 'required|string',
        ]);

        $user = auth()->user();

        Stripe::setApiKey(config('services.stripe.secret'));

        if (!$user->stripe_id) {
            $customer = Customer::create([
                'email' => $user->email,
                'name'  => $user->name ?? '',
            ]);

            $user->stripe_id = $customer->id;
            $user->save();
        }

        $checkoutSession = Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $request->price_id,
                'quantity' => 1,
            ]],
            'success_url' => config('app.frontend_url') . '/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url') . '/cancel',
            'customer' => $user->stripe_id, // Link checkout to this customer
        ]);

        return response()->json(['url' => $checkoutSession->url]);
    }

    private function syncSubscription($subscription)
    {

        $user = User::where('stripe_id', $subscription->customer)->first();

        if (! $user) {
            return;
        }

        $cashierSub = $user->subscriptions()
            ->where('stripe_id', $subscription->id)
            ->first();

        if ($cashierSub) {
            // Update existing subscription
            $cashierSub->update([
                'stripe_status' => $subscription->status,
                'ends_at'       => $subscription->cancel_at ? date('Y-m-d H:i:s', $subscription->cancel_at) : null,
            ]);
        } else {
            // Create subscription record
            Subscription::create([
                'user_id'       => $user->id,
                'stripe_id'     => $subscription->id,
                'type'          => $subscription->items->data[0]->price->type,
                'stripe_status' => $subscription->status,
                'stripe_price'  => $subscription->items->data[0]->price->id,
                'quantity'      => $subscription->items->data[0]->quantity ?? 1,
                'ends_at'       => $subscription->cancel_at ? date('Y-m-d H:i:s', $subscription->cancel_at) : null,
            ]);
        }
    }

    private function cancelSubscription($subscription)
    {
        $user = User::where('stripe_id', $subscription->customer)->first();

        if (! $user) {
            return;
        }

        $cashierSub = $user->subscriptions()
            ->where('stripe_id', $subscription->id)
            ->first();

        if ($cashierSub) {
            $cashierSub->update([
                'stripe_status' => 'canceled',
                'ends_at'       => now(),
            ]);
        }
    }
}
