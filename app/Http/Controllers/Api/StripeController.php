<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StripePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Product;
use Stripe\Price;

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
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

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
                            'price_amount' => $price->unit_amount/100,
                            'currency' => $price->currency
                        ]
                    );
                }
                break;

            case 'price.updated':
                $price = $event->data->object; // Stripe\Price object

                StripePlan::where('stripe_price_id', $price->id)
                    ->update([
                        'price_amount' => $price->unit_amount,
                        'currency' => $price->currency
                    ]);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    public function index()
    {
        $plans = StripePlan::get();

        return response()->json($plans);
    }
}
