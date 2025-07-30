<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="TopUpProvider",
 *     type="object",
 *     required={"name", "code", "is_active", "requires_reference"},
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="Provider ID"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=255,
 *         description="Provider name"
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         maxLength=50,
 *         description="Provider code"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         description="Provider description"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         description="Whether the provider is active"
 *     ),
 *     @OA\Property(
 *         property="requires_reference",
 *         type="boolean",
 *         description="Whether this provider requires a reference"
 *     )
 * )
 */
class TopUpProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'requires_reference',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_reference' => 'boolean',
    ];

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    // Helper methods
    public function validateReference(?string $reference): bool
    {
        if ($this->requires_reference && empty($reference)) {
            return false;
        }
        return true;
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }
}