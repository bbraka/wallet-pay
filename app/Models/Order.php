<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     required={"title", "amount", "status", "user_id"},
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="Order ID"
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         maxLength=255,
 *         description="Order title"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="decimal",
 *         description="Order amount"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"pending_payment", "completed", "cancelled", "refunded"},
 *         description="Order status"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         description="Order description"
 *     ),
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         format="int64",
 *         description="ID of the user who owns this order"
 *     ),
 *     @OA\Property(
 *         property="credit_note_number",
 *         type="string",
 *         maxLength=255,
 *         nullable=true,
 *         description="Credit note number for refunded orders"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Creation timestamp"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         nullable=true,
 *         description="Last update timestamp"
 *     ),
 *     @OA\Property(
 *         property="deleted_at",
 *         type="string",
 *         format="date-time",
 *         nullable=true,
 *         description="Soft delete timestamp"
 *     )
 * )
 */
class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'amount',
        'status',
        'order_type',
        'description',
        'user_id',
        'receiver_user_id',
        'top_up_provider_id',
        'provider_reference',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => OrderStatus::class,
            'order_type' => OrderType::class,
        ];
    }

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the receiver user for internal transfers.
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    /**
     * Get the top-up provider for top-up orders.
     */
    public function topUpProvider()
    {
        return $this->belongsTo(TopUpProvider::class);
    }

    /**
     * Get the transactions for the order.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes
    public function scopeByType($query, OrderType $type)
    {
        return $query->where('order_type', $type);
    }

    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', OrderStatus::PENDING_PAYMENT);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', OrderStatus::COMPLETED);
    }

    // Helper methods
    public function isInternalTransfer(): bool
    {
        return $this->order_type === OrderType::INTERNAL_TRANSFER;
    }

    public function isTopUp(): bool
    {
        return $this->order_type->isTopUp();
    }

    public function canBeConfirmed(): bool
    {
        return $this->status->canBeConfirmed();
    }

    public function canBeRejected(): bool
    {
        return $this->status->canBeRejected();
    }

    public function canBeRefunded(): bool
    {
        return $this->status->canBeRefunded();
    }
}
