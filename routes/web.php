<?php

use App\Http\Controllers\AttachmentDownloadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::get('/attachments/{attachment}', AttachmentDownloadController::class)->middleware(['auth', 'throttle:60,1'])->name('attachments.download');
