<?php

namespace App\Managers;

use Hnooz\LaravelCart\CartManager as BaseCartManager;
use Hnooz\LaravelCart\Models\CartProduct;
use Illuminate\Support\Facades\Auth;

class CartManager extends BaseCartManager
{
    /**
     * Prefer a header token if present; otherwise fall back to cookie session id.
     */
    protected function sessionId(): string
    {
        $header = request()->header('x-cart-token');

        return blank($header) ? $this->session->getId() : $header;
    }

    /**
     * Check that a user is authenticad either by auth web:santum
     */
    protected function authCheck(): bool
    {
        return Auth::check() || Auth::guard('sanctum')->check();
    }

    protected function authId(): ?string
    {
        return Auth::id() ?? Auth::guard('sanctum')->id();
    }

    public function all(): array
    {
        if ($this->driver === 'session') {
            return $this->session->get($this->sessionKey, []);
        }

        if ($this->driver === 'database') {
            return $this->getDatabaseProducts()->toArray();
        }

        // For 'both' driver, prefer database if user is authenticated
        if ($this->authCheck()) {
            return $this->getDatabaseProducts()->toArray();
        }

        return $this->session->get($this->sessionKey, []);
    }

    public function update(string $id, array $data): void
    {
        if ($this->shouldUseSession()) {
            $this->updateToSession($id, $data);
        }

        if ($this->shouldUseDatabase()) {
            $this->updateToDatabase($id, $data);
        }

    }

    protected function updateToSession(string $id, array $data): void
    {
        $cart = $this->session->get($this->sessionKey, []);

        if (! isset($cart[$id])) {
            return;
        }
        $item = $cart[$id];
        $item['price'] = $data['price'] ?? data_get($item, 'price', 0);
        $item['quantity'] = $data['quantity'] ?? data_get($item['quantity'], 0);
        $item['options'] = $data['options'] ?? data_get($item, 'options', []);
        $cart[$id] = $item;

        $this->session->put($this->sessionKey, $cart);
    }

    protected function updateToDatabase(string $id, array $data): void
    {
        // Build the where clause based on authentication status
        $whereConditions = ['product_id' => $id];

        if ($this->authCheck()) {
            // For authenticated users, use user_id
            $whereConditions['user_id'] = $this->authId();
        } else {

            // For guests, use session_id and null user_id
            $whereConditions['session_id'] = $this->sessionId();
            $whereConditions['user_id'] = null;
        }

        $item = CartProduct::where($whereConditions)->first();

        if (! $item) {
            return;
        }

        $item->options = $data['options'] ?? $item->options;
        $item->quantity = $data['quantity'] ?? $item->quantity;
        $item->price = $data['price'] ?? $item->price;

        $item->save();
    }

    protected function addToDatabase(array $item): void
    {
        // Build the where clause based on authentication status
        $whereConditions = ['product_id' => $item['id']];

        if ($this->authCheck()) {
            // For authenticated users, use user_id
            $whereConditions['user_id'] = $this->authId();
        } else {

            // For guests, use session_id and null user_id
            $whereConditions['session_id'] = $this->sessionId();
            $whereConditions['user_id'] = null;
        }

        $existingProduct = CartProduct::where($whereConditions)->first();

        if ($existingProduct) {
            // Update existing item - increase quantity
            $existingProduct->increment('quantity', $item['quantity']);
            $existingProduct->update([
                'name' => $item['name'],
                'price' => $item['price'],
                'options' => $item['options'],
            ]);
        } else {
            // Create new item
            CartProduct::create([
                'session_id' => $this->authCheck() ? null : $this->sessionId(),
                'user_id' => $this->authId(),
                'product_id' => $item['id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'options' => $item['options'],
            ]);
        }
    }

    protected function removeFromSession(string $id): void
    {
        $cart = $this->session->get($this->sessionKey, []);
        unset($cart[$id]);
        $this->session->put($this->sessionKey, $cart);
    }

    protected function removeFromDatabase(string $id): void
    {
        CartProduct::when($this->authCheck(), function ($query) {
            $query->where('user_id', $this->authId());
        }, function ($query) {
            $query->where('user_id', $this->authId())
                ->where('session_id', $this->sessionId());
        })
            ->where('product_id', $id)
            ->delete();
    }

    protected function increaseInSession(string $id, int $quantity): void
    {
        $cart = $this->session->get($this->sessionKey, []);

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] += $quantity;
            $this->session->put($this->sessionKey, $cart);
        }
    }

    protected function increaseInDatabase(string $id, int $quantity): void
    {
        CartProduct::when($this->authCheck(), function ($query) {
            $query->where('user_id', $this->authId());
        }, function ($query) {
            $query->where('user_id', $this->authId())
                ->where('session_id', $this->sessionId());
        })
            ->where('product_id', $id)
            ->increment('quantity', $quantity);
    }

    protected function decreaseInSession(string $id, int $quantity): void
    {
        $cart = $this->session->get($this->sessionKey, []);

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = max(1, $cart[$id]['quantity'] - $quantity);
            $this->session->put($this->sessionKey, $cart);
        }
    }

    protected function decreaseInDatabase(string $id, int $quantity): void
    {
        $item = CartProduct::when($this->authCheck(), function ($query) {
            $query->where('user_id', $this->authId());
        }, function ($query) {
            $query->where('user_id', $this->authId())
                ->where('session_id', $this->sessionId());
        })
            ->where('product_id', $id)
            ->first();

        if ($item) {
            $newQuantity = max(1, $item->quantity - $quantity);
            $item->update(['quantity' => $newQuantity]);
        }
    }

    protected function clearDatabase(): void
    {
        CartProduct::when($this->authCheck(), function ($query) {
            $query->where('user_id', $this->authId());
        }, function ($query) {
            $query->where('user_id', $this->authId())
                ->where('session_id', $this->sessionId());
        })
            ->delete();
    }

    protected function clearAllDatabase(): void
    {
        CartProduct::query()->delete();
    }

    protected function getDatabaseProducts()
    {
        return CartProduct::when($this->authCheck(), function ($query) {
            $query->where('user_id', $this->authId());
        }, function ($query) {
            $query->where('user_id', $this->authId())
                ->where('session_id', $this->sessionId());
        })
            ->get()
            ->map(fn ($item) => [
                'id' => $item->product_id,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'options' => $item->options,
            ]);
    }

    protected function mergeDatabaseProducts(): void
    {
        $guestProducts = CartProduct::where('session_id', $this->sessionId())
            ->whereNull('user_id')
            ->get();

        foreach ($guestProducts as $guestProduct) {
            $userProduct = CartProduct::where('product_id', $guestProduct->product_id)
                ->where('user_id', $this->authId())
                ->first();

            if ($userProduct) {
                $userProduct->quantity += $guestProduct->quantity;
                $userProduct->save();
                $guestProduct->delete();
            } else {
                $guestProduct->user_id = $this->authId();
                $guestProduct->session_id = null; // optional, since it’s now a user row
                $guestProduct->save();
            }
        }
    }

    public function clearAll(): void
    {

        if ($this->shouldUseSession()) {
            $this->session->forget($this->sessionKey);
        }

        if ($this->shouldUseDatabase()) {
            CartProduct::query()->delete();
        }
    }
}
