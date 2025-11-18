<?php

namespace App\Managers;

use App\Facades\Cart;
use App\Models\User;
use App\Models\WishList;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class WishListManager
{
    protected ?string $sessionToken;

    protected Authenticatable|null|User $user;

    public function __construct()
    {
        $this->user = auth()->user() ?? auth()->guard('sanctum')->user();
        $this->sessionToken = request()->header('x-session-token') ?? Str::uuid()->toString();
    }

    /**
     * Get current token (for guests)
     */
    public function token(): string
    {
        return $this->sessionToken;
    }

    /**
     * Add an item to wishlist
     */
    public function add( string $itemId, string $itemType, $name, $price, array $options = []): WishList
    {
        $query = WishList::query();

        $data = [
            'item_id' => $itemId,
            'item_type' => $itemType,
            'name' => $name,
            'price' => $price,
            'options' => $options,
            'session_token' => $this->sessionToken,
            'user_id' => $this->user?->id,
        ];

        if ($this->user) {
            $query->where('user_id', $this->user->id);
        } else {
            $query->where('session_token', $this->sessionToken);
        }

        // Avoid duplicates
        $wishList = $query->where('item_id', $itemId)
            ->where('item_type', $itemType)
            ->first();

        if ($wishList) {
            $wishList->update($data);
            return $wishList;
        }

        return WishList::create([
            'item_id' => $itemId,
            'item_type' => $itemType,
            'name' => $name,
            'price' => $price,
            'options' => $options,
            'session_token' => $this->sessionToken,
            'user_id' => $this->user?->id,
        ]);
    }

    /**
     * Remove item from wishlist
     */
    public function remove(string $itemType, string $itemId): bool
    {
        $query = WishList::query()
            ->where('item_id', $itemId)
            ->where('item_type', $itemType);

        if ($this->user) {
            $query->where('user_id', $this->user->id);
        } else {
            $query->where('session_token', $this->sessionToken);
        }

        return (bool) $query->delete();
    }

    /**
     * List all items in current user/session wishlist
     */
    public function all()
    {
        return WishList::query()
            ->when($this->user, fn ($q) => $q->where('user_id', $this->user->id))
            ->when(! $this->user, fn ($q) => $q->where('session_token', $this->sessionToken))
            ->get();
    }

    /**
     * Clear all wishlist items for current user/session
     */
    public function clear(): int
    {
        $query = WishList::query()
            ->when($this->user, fn ($q) => $q->where('user_id', $this->user->id))
            ->when(! $this->user, fn ($q) => $q->where('session_token', $this->sessionToken));

        return $query->delete();
    }
}
