<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Support\Enums\ServiceRequestStatus;
use App\Models\User;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SR — 콘솔 사용자가 올리는 시스템 개선·오류 요청.
 *
 * @property ServiceRequestStatus $status
 */
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'requester_user_id',
        'title',
        'body',
        'status',
        'assignee_user_id',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /** @return HasMany<ServiceRequestReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(ServiceRequestReply::class);
    }
}
