<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentAccountResource;
use App\Http\Resources\PaymentCardResource;
use App\Http\Resources\PurchasesResource;
use App\Http\Resources\SalesResource;
use App\Http\Resources\UserWithShippingAddressResource;
use App\Http\Service\StripeService;
use App\Models\Order;
use App\Models\Post;
use App\Models\PriceRequest;
use App\Models\UserShippingAddress;
use App\Notifications\PriceRequestAcceptedNotification;
use App\Notifications\PriceRequestDeclinedNotification;
use App\Notifications\PriceRequestNotification;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function addStripeAccount(Request $request) {
        $account = $this->stripeService->createBankAccount($request);

        return response()->json(['data' => new PaymentAccountResource($account)]);
    }

    public function buyPost(Request $request) {
        $order = $this->stripeService->buy($request);
        return response()->json(['data' => new PurchasesResource($order)]);
    }

    public function listAccountPayoutMethods(Request $request) {
        return response()->json(['data' => PaymentAccountResource::collection(auth()->user()->paymentAccounts)]);
    }

    public function removeCard(Request $request) {
        $cards = $this->stripeService->removeCustomerCard($request);

        return response()->json(['data' => PaymentCardResource::collection($cards)]);
    }

    public function setDefaultCard(Request $request) {
        $cards = $this->stripeService->setDefaultCard($request);

        return response()->json(['data' => PaymentCardResource::collection($cards)]);
    }

    public function removePaymentAccount(Request $request) {
        $accounts = $this->stripeService->removePaymentAccount($request);

        return response()->json(['data' => PaymentAccountResource::collection($accounts)]);
    }

    public function setDefaultAccount(Request $request) {
        $accounts = $this->stripeService->setDefaultBankAccount($request);

        return response()->json(['data' => PaymentAccountResource::collection($accounts)]);
    }

    public function listPurchases() {
        return response()->json(['data' => $this->stripeService->listPurchases()]);
    }

    public function listSales() {
        return response()->json(['data' => $this->stripeService->listSales()]);
    }

    public function listTransactions(Request $request) {
        $sales = $this->stripeService->listSales()->toArray($request);
        $purchases = $this->stripeService->listPurchases()->toArray($request);

        return response()->json(['data' => array_merge($sales, $purchases)]);
    }

    public function setShippingAddressByOrderId($id, Request $request) {
        $order = Order::where(['id' => $id])->first();

        $user = auth()->user();
        if ($user->address) {
            $user->address->delete();
        }

        $address = new UserShippingAddress();
        $address->country = $request->get('country');
        $address->state = $request->get('state');
        $address->city = $request->get('city');
        $address->zip = $request->get('zip');
        $address->address = $request->get('address');
        $address->user_id = auth()->user()->id;

        $address->save();

        $order->shipping_address_id = $address->id;
        $order->save();

        return response()->json(['data' => new PurchasesResource($order)]);
    }

    public function setOrderStatus($id, Request $request)
    {
        $order = Order::where(['id' => $id])->first();

        $order->status = $request->get('status');
        if ($request->has('shipping') && $request->get('shipping') != '') {
            $order->shippingMethod = $request->get('shipping');
        }
        if ($request->has('number') && $request->get('number') != '') {
            $order->trackingNumber = $request->get('number');
        }
        $order->save();

        return response()->json(['data' => SalesResource::make($order)]);
    }

    public function getBuyerByOrderId(Order $order) {
        if ($order->seller->id == auth()->user()->id) {
            $buyer = $order->buyer;
            return response()->json(['data' => ['buyer' => UserWithShippingAddressResource::make($buyer)]]);
        } else {
            return response()->json(['error' => 'No access'], 401);
        }

    }

    public function sendPriceRequest($postId)
    {
        $post = Post::where(['id' => $postId])->first();

        $request = new PriceRequest();
        $request->post_id = $post->id;
        $request->user_id = auth()->user()->id;
        $request->status = 'new';
        $request->save();

        $post->owner->notify(new PriceRequestNotification($post, auth()->user(), $request));

        return response()->json(['data' => $request]);
    }

    public function acceptRequest($requestId)
    {
        $request = PriceRequest::where(['id' => $requestId])->first();
        $request->status = 'accepted';
        $request->save();

        $request->requestor->notify(new PriceRequestAcceptedNotification($request->post, auth()->user(), $request));

        return response()->json(['data' => $request]);
    }

    public function declineRequest($requestId)
    {
        $request = PriceRequest::where(['id' => $requestId])->first();
        $request->status = 'declined';
        $request->save();

        $request->requestor->notify(new PriceRequestDeclinedNotification($request->post, auth()->user(), $request));


        return response()->json(['data' => $request]);
    }
}
