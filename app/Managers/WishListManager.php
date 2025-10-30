<?php

namespace App\Managers;

use App\Facades\Cart;
use App\Models\User;
use App\Models\WishList;
use \Illuminate\Contracts\Auth\Authenticatable;
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
    public function add(string $itemType, string $itemId, array $snapshot = [], array $options = []): WishList
    {
        $query = WishList::query();

        if ($this->user) {
            $query->where('user_id', $this->user->id);
        } else {
            $query->where('session_token', $this->sessionToken);
        }

        // Avoid duplicates
        $existing = $query->where('item_id', $itemId)
            ->where('item_type', $itemType)
            ->first();

        if ($existing) {
            return $existing;
        }

        return WishList::create([
            'item_id'       => $itemId,
            'item_type'     => $itemType,
            'item_name'     => $snapshot['name'] ?? null,
            'item_image'    => $snapshot['image'] ?? null,
            'item_price'    => $snapshot['price'] ?? null,
            'options'       => $options,
            'user_id'       => $this->user?->id,
            'session_token' => $this->user ? null : $this->sessionToken,
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
            ->when(!$this->user, fn ($q) => $q->where('session_token', $this->sessionToken))
            ->get();
    }

    /**
     * Move item from wishlist to cart (then remove it)
     */
    public function moveToCart(string $itemType, string $itemId): bool
    {
        $item = WishList::query()
            ->when($this->user, fn ($q) => $q->where('user_id', $this->user->id))
            ->when(!$this->user, fn ($q) => $q->where('session_token', $this->sessionToken))
            ->where('item_id', $itemId)
            ->where('item_type', $itemType)
            ->first();

        if (!$item) {
            return false;
        }

        // Add to cart (assuming Cart facade supports it)
        Cart::add([
            'item_id'   => $item->item_id,
            'item_type' => $item->item_type,
            'name'      => $item->item_name,
            'price'     => $item->item_price,
            'image'     => $item->item_image,
            'options'   => $item->options ?? [],
        ]);

        // Remove from wishlist
        $item->delete();

        return true;
    }

    /**
     * Clear all wishlist items for current user/session
     */
    public function clear(): int
    {
        $query = WishList::query()
            ->when($this->user, fn ($q) => $q->where('user_id', $this->user->id))
            ->when(!$this->user, fn ($q) => $q->where('session_token', $this->sessionToken));

        return $query->delete();
    }
}
