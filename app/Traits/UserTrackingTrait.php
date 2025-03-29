<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait UserTrackingTrait
{
    protected static function bootUserTrackingTrait()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->created_by_username = Auth::user()->name;
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
                $model->updated_by_username = Auth::user()->name;
            }
        });

        static::deleting(function ($model) {
            if (Auth::check()) {
                $model->deleted_by = Auth::id();
                $model->deleted_by_username = Auth::user()->name;
            }
        });
    }
}
