<?php

namespace App\Managers;

use Hnooz\LaravelCart\CartManager as BaseCartManager;
use Hnooz\LaravelCart\Models\CartItem;
use Illuminate\Support\Facades\Auth;

class CartManager extends BaseCartManager
{
    /**
     * Prefer a header token if present; otherwise fall back to cookie session id.
     */
    protected function sessionId(): string
    {
        $header = request()->header('x-session-token');

        return blank($header) ? $this->session->getId() : $header;
    }

    /**
     * Check that a user is authenticated either by auth web:sanctum
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
            return $this->getDatabaseItems()->toArray();
        }

        // For 'both' driver, prefer database if user is authenticated
        if ($this->authCheck()) {
            return $this->getDatabaseItems()->toArray();
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
        $whereConditions = ['item_id' => $id];

        if ($this->authCheck()) {
            // For authenticated users, use user_id
            $whereConditions['user_id'] = $this->authId();
        } else {

            // For guests, use session_id and null user_id
            $whereConditions['session_id'] = $this->sessionId();
            $whereConditions['user_id'] = null;
        }

        $item = CartItem::where($whereConditions)->first();

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
        $whereConditions = ['item_id' => $item['id']];

        $existingItem = $this->cartItemQuery()
            ->where($whereConditions)
            ->first();

        if ($existingItem) {
            // Update existing item - increase quantity
            $existingItem->increment('quantity', $item['quantity']);
            $existingItem->update([
                'name' => $item['name'],
                'price' => $item['price'],
                'options' => $item['options'],
            ]);
        } else {
            // Create new item
            CartItem::create([
                'session_id' => $this->authCheck() ? null : $this->sessionId(),
                'user_id' => $this->authId(),
                'item_id' => $item['id'],
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
        CartItem::when($this->authCheck(), function ($query) {
            $query->where('user_id', $this->authId());
        }, function ($query) {
            $query->where('user_id', $this->authId())
                ->where('session_id', $this->sessionId());
        })
            ->where('item_id', $id)
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
        $this->cartItemQuery()
            ->where('item_id', $id)
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
        $item = $this->cartItemQuery()
            ->where('item_id', $id)
            ->first();
        if ($item) {
            $newQuantity = max(1, $item->quantity - $quantity);
            $item->update(['quantity' => $newQuantity]);
        }
    }

    protected function clearDatabase(): void
    {
        $this->cartItemQuery()->delete();
    }

    protected function clearAllDatabase(): void
    {
        CartItem::query()->delete();
    }

    protected function getDatabaseItems()
    {
        return $this->cartItemQuery()
            ->get()
            ->map(fn ($item) => [
                'id' => $item->item_id,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'options' => $item->options,
            ]);
    }

    protected function mergeDatabaseItems(): void
    {
        $guestItems = CartItem::where('session_id', $this->sessionId())
            ->whereNull('user_id')
            ->get();

        foreach ($guestItems as $guestItem) {
            $userItem = CartItem::where('item_id', $guestItem->item_id)
                ->where('user_id', $this->authId())
                ->first();

            if ($userItem) {
                $userItem->quantity += $guestItem->quantity;
                $userItem->save();
                $guestItem->delete();
            } else {
                $guestItem->user_id = $this->authId();
                $guestItem->session_id = null; // optional, since it’s now a user row
                $guestItem->save();
            }
        }
    }

    public function clearAll(): void
    {

        if ($this->shouldUseSession()) {
            $this->session->forget($this->sessionKey);
        }

        if ($this->shouldUseDatabase()) {
            CartItem::query()->delete();
        }
    }

    private function cartItemQuery()
    {
        return CartItem::when($this->authCheck(), function ($query) {
            $query->where('user_id', $this->authId())
                ->whereNull('session_id');
        }, function ($query) {
            $query->whereNull('user_id')
                ->where('session_id', $this->sessionId());
        });
    }
}
