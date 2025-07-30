<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Transaction",
 *     type="object",
 *     required={"user_id", "type", "amount", "status", "created_by"},
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="Transaction ID"
 *     ),
 *     @OA\Property(
 *         property="user_id",
 *         type="integer",
 *         format="int64",
 *         description="ID of the user this transaction belongs to"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"credit", "debit"},
 *         description="Transaction type"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="decimal",
 *         description="Transaction amount"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"active", "cancelled"},
 *         description="Transaction status"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         description="Transaction description"
 *     ),
 *     @OA\Property(
 *         property="created_by",
 *         type="integer",
 *         format="int64",
 *         description="ID of the user who created this transaction"
 *     ),
 *     @OA\Property(
 *         property="order_id",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         description="ID of the related order"
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
 *     )
 * )
 */
class Transaction extends Model
{
    use HasFactory;
    use CrudTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'status',
        'description',
        'created_by',
        'order_id',
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
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
        ];
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created this transaction.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias for createdBy relationship to match CRUD controller expectations
     */
    public function creator()
    {
        return $this->createdBy();
    }

    /**
     * Get the order that this transaction belongs to.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', TransactionStatus::ACTIVE);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', TransactionStatus::CANCELLED);
    }

    public function scopeByType($query, TransactionType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCredits($query)
    {
        return $query->where('type', TransactionType::CREDIT);
    }

    public function scopeDebits($query)
    {
        return $query->where('type', TransactionType::DEBIT);
    }

    // Helper methods
    public function isCredit(): bool
    {
        return $this->type === TransactionType::CREDIT;
    }

    public function isDebit(): bool
    {
        return $this->type === TransactionType::DEBIT;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function cancel(): void
    {
        $this->update(['status' => TransactionStatus::CANCELLED]);
    }

    public function getDeleteButton()
    {
        // Only show buttons for manual transactions (created by admin)
        if (!$this->created_by || $this->order_id) {
            return '<a class="btn btn-sm btn-link" href="' . url(config('backpack.base.route_prefix', 'admin') . '/transaction/' . $this->id) . '" data-style="zoom-in"><i class="la la-eye"></i> Show</a>';
        }

        return '<a class="btn btn-sm btn-link" href="' . url(config('backpack.base.route_prefix', 'admin') . '/transaction/' . $this->id) . '" data-style="zoom-in"><i class="la la-eye"></i> Show</a>
                <a class="btn btn-sm btn-link" href="' . url(config('backpack.base.route_prefix', 'admin') . '/transaction/' . $this->id . '/edit') . '" data-style="zoom-in"><i class="la la-edit"></i> Edit</a>
                <a class="btn btn-sm btn-link" href="' . url(config('backpack.base.route_prefix', 'admin') . '/transaction/' . $this->id) . '" data-button-type="delete"><i class="la la-trash"></i> Delete</a>';
    }
}
