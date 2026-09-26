<?php

namespace Modules\Invitations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Lunar\Admin\Models\Staff;

#[Fillable(['label', 'token', 'expires_at', 'max_uses', 'uses_count', 'last_used_at', 'revoked_at', 'created_by'])]
class Invitation extends Model
{
    protected $table = 'invitations';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation) {
            $invitation->token ??= Str::random(48);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /** active | expired | revoked | exhausted */
    public function getStatusAttribute(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            $this->max_uses !== null && $this->uses_count >= $this->max_uses => 'exhausted',
            default => 'active',
        };
    }

    /** Une invitation déjà ouverte reste utilisable par sa session tant qu'elle n'est ni expirée ni révoquée. */
    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** Peut-on ouvrir le lien pour la première fois (démarrer une nouvelle session) ? */
    public function canBeOpened(): bool
    {
        return $this->status === 'active';
    }

    public function getUrlAttribute(): string
    {
        return route('invitation.accept', $this->token);
    }
}
