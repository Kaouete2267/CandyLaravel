<?php

use Illuminate\Support\Facades\Route;
use Modules\Invitations\Http\Controllers\AcceptInvitationController;

Route::middleware('web')->get('/invitation/{token}', AcceptInvitationController::class)
    ->where('token', '[A-Za-z0-9]{20,64}')
    ->name('invitation.accept');
